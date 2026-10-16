<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->with(['role', 'branch.restaurant'])
            ->where('email', $request->validated('email'))
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->active) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive. Contact a manager.'],
            ]);
        }

        $token = $user->createToken($request->validated('device_name') ?: 'web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): UserResource
    {
        $request->user()->load(['role', 'branch.restaurant']);

        return new UserResource($request->user());
    }

    public function switchBranch(Request $request): UserResource
    {
        abort_unless($request->user()->canManageBranches(), 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $branchId = (int) $data['branch_id'];
        abort_unless($request->user()->canOperateBranch($branchId), 403);

        $branch = Branch::query()->findOrFail($branchId);
        abort_unless($branch->active, 422, 'That branch is inactive.');

        $request->user()->update(['branch_id' => $branch->id]);

        return new UserResource($request->user()->fresh(['role', 'branch.restaurant']));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password updated.']);
    }
}
