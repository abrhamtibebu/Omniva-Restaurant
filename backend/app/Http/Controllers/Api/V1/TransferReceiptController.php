<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\PaymentEvidence;
use App\Services\ReceiptOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TransferReceiptController extends Controller
{
    use ResolvesBranch;

    public function __construct(private ReceiptOcrService $ocr) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosReconcile) || $request->user()->isAdmin(), 403);

        $rows = PaymentEvidence::query()
            ->with('uploader:id,name')
            ->where('branch_id', $this->branchId())
            ->whereNotNull('file_hash')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosReconcile) || $request->user()->isAdmin(), 403);

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
                'file' => ['This receipt was already uploaded for this branch.'],
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
            'uploaded_by' => $request->user()->id,
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

        return response()->json(['data' => $evidence->load('uploader:id,name')], 201);
    }

    public function update(Request $request, PaymentEvidence $paymentEvidence): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::BonosReconcile) || $request->user()->isAdmin(), 403);
        abort_unless((int) $paymentEvidence->branch_id === $this->branchId(), 404);

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'payee_name' => ['nullable', 'string', 'max:160'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
            'method' => ['sometimes', 'string', 'max:40'],
        ]);

        if (array_key_exists('transaction_reference', $data) && filled($data['transaction_reference'])) {
            $this->assertUniqueFt(
                $this->branchId(),
                (string) $data['transaction_reference'],
                $paymentEvidence->id,
            );
            $data['reference'] = $data['transaction_reference'];
        }

        $paymentEvidence->update($data);

        return response()->json(['data' => $paymentEvidence->fresh('uploader:id,name')]);
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
                'transaction_reference' => ['This FT / transaction reference was already uploaded for this branch.'],
            ]);
        }
    }
}
