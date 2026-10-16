<?php

namespace Tests\Feature;

use App\Enums\RoleSlug;
use App\Models\PaymentEvidence;
use App\Services\ReceiptOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferReceiptTest extends TestCase
{
    use RefreshDatabase;

    /** Minimal 1x1 PNG (no GD required). */
    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==') ?: '';
    }

    private function pngUpload(string $name = 'slip.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pngBytes());
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->app->instance(ReceiptOcrService::class, new class extends ReceiptOcrService
        {
            public function parse(string $absolutePath): array
            {
                return [
                    'amount' => '500.00',
                    'payee_name' => 'Betedesta Bar',
                    'account_number' => '1000123456789',
                    'phone' => '0911223344',
                    'transaction_reference' => 'FT123456789',
                    'ocr_raw_text' => 'Amount ETB 500.00 FT123456789',
                    'ocr_status' => 'parsed',
                ];
            }
        });
    }

    public function test_cashier_can_upload_receipt_and_edit_fields(): void
    {
        $this->actingAsRole(RoleSlug::Cashier);

        $created = $this->post('/api/v1/bono/transfer-receipts', [
            'file' => $this->pngUpload('slip.png'),
            'method' => 'mobile_money',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '500.00')
            ->assertJsonPath('data.transaction_reference', 'FT123456789')
            ->json('data');

        $this->patchJson('/api/v1/bono/transfer-receipts/'.$created['id'], [
            'amount' => '505.00',
            'payee_name' => 'Corrected Payee',
        ])->assertOk()
            ->assertJsonPath('data.amount', '505.00')
            ->assertJsonPath('data.payee_name', 'Corrected Payee');

        $this->getJson('/api/v1/bono/transfer-receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_waiter_can_upload_and_cashier_sees_and_validates(): void
    {
        $waiter = $this->user(RoleSlug::Waiter);
        $cashier = $this->user(RoleSlug::Cashier);

        Sanctum::actingAs($waiter);
        $created = $this->post('/api/v1/bono/transfer-receipts', [
            'file' => $this->pngUpload('waiter-slip.png'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.uploaded_by', $waiter->id)
            ->json('data');

        $this->getJson('/api/v1/bono/transfer-receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->get('/api/v1/bono/transfer-receipts/'.$created['id'].'/file')
            ->assertOk();

        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/bono/transfer-receipts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uploader.name', $waiter->name);

        $this->postJson('/api/v1/bono/transfer-receipts/'.$created['id'].'/verify', [
            'verification_status' => 'verified',
        ])->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');
    }

    public function test_waiter_cannot_see_another_waiters_receipt(): void
    {
        $waiterA = $this->user(RoleSlug::Waiter, ['email' => 'wa@omniva.test']);
        $waiterB = $this->user(RoleSlug::Waiter, ['email' => 'wb@omniva.test']);

        Sanctum::actingAs($waiterA);
        $this->post('/api/v1/bono/transfer-receipts', [
            'file' => $this->pngUpload('a.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        Sanctum::actingAs($waiterB);
        $this->getJson('/api/v1/bono/transfer-receipts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_duplicate_file_hash_is_blocked(): void
    {
        $this->actingAsRole(RoleSlug::Cashier);
        $bytes = $this->pngBytes();

        $this->post('/api/v1/bono/transfer-receipts', [
            'file' => UploadedFile::fake()->createWithContent('same-slip.png', $bytes),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->post('/api/v1/bono/transfer-receipts', [
            'file' => UploadedFile::fake()->createWithContent('copy-slip.png', $bytes),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_duplicate_ft_reference_is_blocked(): void
    {
        $this->actingAsRole(RoleSlug::Cashier);

        PaymentEvidence::query()->create([
            'branch_id' => $this->branch->id,
            'method' => 'mobile_money',
            'amount' => '100.00',
            'file_hash' => str_repeat('a', 64),
            'transaction_reference' => 'FT123456789',
            'reference' => 'FT123456789',
            'ocr_status' => 'parsed',
            'verification_status' => 'pending',
        ]);

        $this->post('/api/v1/bono/transfer-receipts', [
            'file' => $this->pngUpload('other.png'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_reference']);
    }
}
