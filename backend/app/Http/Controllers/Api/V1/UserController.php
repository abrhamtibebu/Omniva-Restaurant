<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $branchIds = $actor->canManageBranches()
            ? $actor->viewableBranches()->pluck('id')
            : collect([$actor->branch_id])->filter();

        $users = User::query()
            ->with(['role', 'branch'])
            ->whereIn('branch_id', $branchIds)
            ->when($request->integer('branch_id'), function ($q, $id) use ($branchIds) {
                if ($branchIds->contains($id)) {
                    $q->where('branch_id', $id);
                }
            })
            ->when($request->integer('role_id'), fn ($q, $id) => $q->where('role_id', $id))
            ->when($request->filled('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 20), 100));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $user = User::query()->create($request->validated());

        $this->audit->record('user.created', $user, null, $user->only(['name', 'email', 'role_id', 'active']), $request->user());

        return new UserResource($user->load(['role', 'branch']));
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['role', 'branch.restaurant']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $old = $user->only(['name', 'email', 'phone', 'role_id', 'branch_id', 'active']);
        $user->update($request->validated());

        $this->audit->record('user.updated', $user, $old, $user->only(['name', 'email', 'phone', 'role_id', 'branch_id', 'active']), $request->user());

        return new UserResource($user->fresh(['role', 'branch']));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->update(['active' => false]);
        $user->tokens()->delete();

        $this->audit->record('user.deactivated', $user, ['active' => true], ['active' => false], $request->user());

        return response()->json(['message' => 'Staff account deactivated.']);
    }
}
