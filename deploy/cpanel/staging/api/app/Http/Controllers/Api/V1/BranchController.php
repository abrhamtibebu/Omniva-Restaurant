<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Branches\CloneBranchMenuAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()->hasPermission(Permission::BranchesView)
            || $request->user()->hasPermission(Permission::BranchesManage),
            403,
        );

        return BranchResource::collection($request->user()->viewableBranches()->load('restaurant'));
    }

    public function store(Request $request, CloneBranchMenuAction $cloneMenu): BranchResource
    {
        abort_unless($request->user()->hasPermission(Permission::BranchesManage), 403);

        $restaurantId = $request->user()->restaurantId();
        abort_unless($restaurantId, 422, 'Your account is not assigned to a restaurant.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'tax_identification_number' => ['nullable', 'string', 'max:80'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'clone_from_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $sourceId = isset($data['clone_from_branch_id']) ? (int) $data['clone_from_branch_id'] : null;
        if ($sourceId) {
            abort_unless($request->user()->branchBelongsToRestaurant($sourceId), 403);
        }

        $home = $request->user()->branch;

        $branch = Branch::query()->create([
            'restaurant_id' => $restaurantId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'tax_identification_number' => $data['tax_identification_number'] ?? $home?->tax_identification_number,
            'currency' => $data['currency'] ?? $home?->currency ?? 'ETB',
            'timezone' => $data['timezone'] ?? $home?->timezone ?? 'Africa/Addis_Ababa',
            'tax_rate' => $data['tax_rate'] ?? $home?->tax_rate ?? '15.00',
            'active' => true,
        ]);

        if ($sourceId) {
            $cloneMenu->execute($sourceId, (int) $branch->id);
        }

        $this->audit->record(
            'branch.created',
            $branch,
            null,
            $branch->only(['name', 'restaurant_id', 'active']),
            $request->user(),
        );

        return new BranchResource($branch->load('restaurant'));
    }

    public function update(Request $request, Branch $branch): BranchResource
    {
        abort_unless($request->user()->hasPermission(Permission::BranchesManage)
            || $request->user()->hasPermission(Permission::SettingsManage), 403);
        abort_unless($request->user()->branchBelongsToRestaurant((int) $branch->id), 403);

        $canEditOthers = $request->user()->canManageBranches();
        if (! $canEditOthers && (int) $branch->id !== (int) $request->user()->branch_id) {
            abort(403, 'You can only edit your assigned branch.');
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string'],
            'tax_identification_number' => ['nullable', 'string', 'max:80'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'restaurant_name' => ['sometimes', 'string', 'max:160'],
        ]);

        if (array_key_exists('active', $data) && ! $data['active']) {
            abort_unless($request->user()->canManageBranches(), 403);

            if ((int) $request->user()->branch_id === (int) $branch->id) {
                abort(422, 'Switch to another branch before deactivating this one.');
            }

            $anotherActive = Branch::query()
                ->where('restaurant_id', $branch->restaurant_id)
                ->where('active', true)
                ->whereKeyNot($branch->id)
                ->exists();

            abort_unless($anotherActive, 422, 'Keep at least one active branch.');
        }

        $old = $branch->only(['name', 'phone', 'address', 'tax_rate', 'active']);
        $branch->update(collect($data)->except('restaurant_name')->all());

        if ($request->filled('restaurant_name') && $branch->restaurant && $request->user()->canManageBranches()) {
            $branch->restaurant->update(['name' => $request->string('restaurant_name')->toString()]);
        }

        $this->audit->record(
            'branch.updated',
            $branch,
            $old,
            $branch->only(['name', 'phone', 'address', 'tax_rate', 'active']),
            $request->user(),
        );

        return new BranchResource($branch->fresh('restaurant'));
    }
}
