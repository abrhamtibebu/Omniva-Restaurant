<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Branch;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:160'],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $restaurant = Restaurant::query()->create([
                'name' => $data['restaurant_name'],
                'phone' => $data['phone'] ?? null,
                'currency' => 'ETB',
                'timezone' => 'Africa/Addis_Ababa',
                'active' => true,
            ]);

            $branch = Branch::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => 'Main',
                'phone' => $data['phone'] ?? null,
                'currency' => 'ETB',
                'timezone' => 'Africa/Addis_Ababa',
                'tax_rate' => '0',
                'active' => true,
            ]);

            $admin = Role::query()->where('slug', RoleSlug::Admin->value)->firstOrFail();

            $owner = User::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role_id' => $admin->id,
                'branch_id' => $branch->id,
                'active' => true,
                'is_platform' => false,
            ]);

            $this->subscriptions->startTrial($restaurant);

            return $owner;
        });

        $token = $user->createToken('web')->plainTextToken;
        $payload = (new UserResource($user->load(['role', 'branch.restaurant'])))->resolve();
        $payload['bono_control'] = $this->subscriptions->summary($user->restaurantId());
        $payload['is_platform'] = false;

        return response()->json([
            'token' => $token,
            'user' => $payload,
        ], 201);
    }
}
