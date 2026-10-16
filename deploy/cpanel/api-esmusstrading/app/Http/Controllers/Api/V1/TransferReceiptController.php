<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\PaymentEvidence;
use App\Models\User;
use App\Services\ReceiptOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransferReceiptController extends Controller
{
    use ResolvesBranch;

    public function __construct(private ReceiptOcrService $ocr) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($user), 403);

        $query = PaymentEvidence::query()
            ->with('uploader:id,name')
            ->where('branch_id', $this->branchId())
            ->whereNotNull('file_hash');

        if (! $this->canReview($user)) {
            $query->where('uploaded_by', $user->id);
        }

        $rows = $query
            ->orderByRaw("CASE verification_status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (PaymentEvidence $row) => $this->serialize($row));

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canUpload($user), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'method' => ['sometimes', 'string', 'max:40'],
        ]);

        $file = $data['file'];
        $bytes = file_get_contents($file->getRealPath()) ?: '';
        $hash = hash('sha256', $bytes);
        $branchId = $this->branchId();

        if (PaymentEvidence::query()->where('branch_id', $branchId)->where('file_hash', $hash)->exists()) {
            throw ValidationException::withMessages([
                'file' => [__('messages.operations.receipt_already_uploaded')],
            ]);
        }

        $path = $file->store("receipts/{$branchId}", 'local');
        $absolute = Storage::disk('local')->path($path);
        $parsed = $this->ocr->parse($absolute);

        if (! empty($parsed['transaction_reference'])) {
            $this->assertUniqueFt($branchId, $parsed['transaction_reference']);
        }

        $evidence = PaymentEvidence::query()->create([
            'branch_id' => $branchId,
            'uploaded_by' => $user->id,
            'reconciliation_id' => null,
            'file_path' => $path,
            'file_hash' => $hash,
            'method' => $data['method'] ?? 'mobile_money',
            'amount' => $parsed['amount'] ?? '0.00',
            'reference' => $parsed['transaction_reference'],
            'payee_name' => $parsed['payee_name'],
            'account_number' => $parsed['account_number'],
            'phone' => $parsed['phone'],
            'transaction_reference' => $parsed['transaction_reference'],
            'ocr_raw_text' => $parsed['ocr_raw_text'],
            'ocr_status' => $parsed['ocr_status'],
            'verification_status' => 'pending',
        ]);

        return response()->json(['data' => $this->serialize($evidence->load('uploader:id,name'))], 201);
    }

    public function update(Request $request, PaymentEvidence $paymentEvidence): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($user), 403);
        abort_unless((int) $paymentEvidence->branch_id === $this->branchId(), 404);
        $this->assertCanTouch($user, $paymentEvidence);

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'payee_name' => ['nullable', 'string', 'max:160'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
            'method' => ['sometimes', 'string', 'max:40'],
        ]);

        // Waiters may only edit while still pending.
        if (! $this->canReview($user) && $paymentEvidence->verification_status !== 'pending') {
            abort(422, 'This receipt was already reviewed and can no longer be edited.');
        }

        if (array_key_exists('transaction_reference', $data) && filled($data['transaction_reference'])) {
            $this->assertUniqueFt(
                $this->branchId(),
                (string) $data['transaction_reference'],
                $paymentEvidence->id,
            );
            $data['reference'] = $data['transaction_reference'];
        }

        $paymentEvidence->update($data);

        return response()->json(['data' => $this->serialize($paymentEvidence->fresh('uploader:id,name'))]);
    }

    public function verify(Request $request, PaymentEvidence $paymentEvidence): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canReview($user), 403);
        abort_unless((int) $paymentEvidence->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'verification_status' => ['required', 'in:verified,rejected'],
        ]);

        $paymentEvidence->update([
            'verification_status' => $data['verification_status'],
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        return response()->json(['data' => $this->serialize($paymentEvidence->fresh('uploader:id,name'))]);
    }

    public function file(Request $request, PaymentEvidence $paymentEvidence): StreamedResponse
    {
        $user = $request->user();
        abort_unless($this->canAccess($user), 403);
        abort_unless((int) $paymentEvidence->branch_id === $this->branchId(), 404);
        $this->assertCanTouch($user, $paymentEvidence, allowReviewed: true);

        abort_unless($paymentEvidence->file_path && Storage::disk('local')->exists($paymentEvidence->file_path), 404);

        $mime = Storage::disk('local')->mimeType($paymentEvidence->file_path) ?: 'application/octet-stream';

        return Storage::disk('local')->response(
            $paymentEvidence->file_path,
            basename($paymentEvidence->file_path),
            ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=300'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PaymentEvidence $row): array
    {
        return [
            ...$row->toArray(),
            'file_url' => "/bono/transfer-receipts/{$row->id}/file",
        ];
    }

    private function canAccess(User $user): bool
    {
        return $this->canUpload($user) || $this->canReview($user);
    }

    private function canUpload(User $user): bool
    {
        return $user->hasPermission(Permission::BonosSubmit)
            || $user->hasPermission(Permission::BonosReconcile)
            || $user->isAdmin();
    }

    private function canReview(User $user): bool
    {
        return $user->hasPermission(Permission::BonosReconcile)
            || $user->isAdmin()
            || $user->isManager();
    }

    private function assertCanTouch(User $user, PaymentEvidence $paymentEvidence, bool $allowReviewed = false): void
    {
        if ($this->canReview($user)) {
            return;
        }

        abort_unless((int) $paymentEvidence->uploaded_by === (int) $user->id, 403);

        if (! $allowReviewed && $paymentEvidence->verification_status !== 'pending') {
            abort(403);
        }
    }

    private function assertUniqueFt(int $branchId, string $ft, ?int $ignoreId = null): void
    {
        $exists = PaymentEvidence::query()
            ->where('branch_id', $branchId)
            ->where('transaction_reference', $ft)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'transaction_reference' => [__('messages.operations.ft_already_uploaded')],
            ]);
        }
    }
}
