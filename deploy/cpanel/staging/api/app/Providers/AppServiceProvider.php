<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            if (! $user->active) {
                return false;
            }

            if ($user->isAdmin()) {
                return true;
            }

            return null;
        });

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, function (User $user) use ($permission) {
                return $user->hasPermission($permission);
            });
        }

        Route::bind(
            'table',
            fn (string $value) => RestaurantTable::query()->findOrFail($value),
        );
        Route::bind(
            'inventory',
            fn (string $value) => InventoryItem::query()->findOrFail($value),
        );

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                mb_strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
