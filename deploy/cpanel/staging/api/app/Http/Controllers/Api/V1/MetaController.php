<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Api\Concerns\ResolvesBranch;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaController extends Controller
{
    use ResolvesBranch;

    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::UsersView), 403);

        return response()->json(['data' => Role::query()->orderBy('id')->get(['id', 'name', 'slug'])]);
    }

    public function settings(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(Permission::SettingsManage)
            || $request->user()->hasPermission(Permission::BranchesView),
            403,
        );

        $branch = Branch::query()->with('restaurant')->findOrFail($this->branchId());

        return response()->json(['data' => $branch]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::SettingsManage), 403);

        $branch = Branch::query()->findOrFail($this->branchId());

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'tax_identification_number' => ['nullable', 'string', 'max:80'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $branch->update($data);

        if ($request->filled('restaurant_name') && $branch->restaurant) {
            $branch->restaurant->update(['name' => $request->string('restaurant_name')]);
        }

        return response()->json(['data' => $branch->fresh('restaurant')]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Permission::AuditLogsView), 403);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->latest('created_at')
            ->paginate(min($request->integer('per_page', 30), 100));

        return response()->json($logs);
    }
}
