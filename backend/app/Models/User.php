<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\RoleSlug;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role_id',
        'branch_id',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'waiter_id');
    }

    public function hasPermission(Permission $permission): bool
    {
        if (! $this->active) {
            return false;
        }

        return Permissions::roleHas($this->role?->slug ?? '', $permission);
    }

    public function isAdmin(): bool
    {
        return $this->role?->slug === RoleSlug::Admin->value;
    }

    public function isManager(): bool
    {
        return $this->role?->slug === RoleSlug::Manager->value;
    }

    /**
     * @return list<string>
     */
    public function permissionValues(): array
    {
        return Permissions::forRole($this->role?->slug);
    }

    public function restaurantId(): ?int
    {
        $this->loadMissing('branch');

        return $this->branch?->restaurant_id;
    }

    public function canManageBranches(): bool
    {
        return $this->isAdmin() || $this->hasPermission(Permission::BranchesManage);
    }

    /**
     * Operate means POS, kitchen, inventory, and reports for this location.
     * Admin (owner) may work any branch of their restaurant; staff stay on theirs.
     */
    public function canOperateBranch(int $branchId): bool
    {
        if ($this->canManageBranches()) {
            return $this->branchBelongsToRestaurant($branchId);
        }

        return (int) $this->branch_id === $branchId;
    }

    public function branchBelongsToRestaurant(int $branchId): bool
    {
        $restaurantId = $this->restaurantId();

        if (! $restaurantId) {
            return (int) $this->branch_id === $branchId;
        }

        return Branch::query()
            ->whereKey($branchId)
            ->where('restaurant_id', $restaurantId)
            ->exists();
    }

    /**
     * Branches the user may list (admin and manager see the whole restaurant).
     *
     * @return EloquentCollection<int, Branch>
     */
    public function viewableBranches(): EloquentCollection
    {
        $restaurantId = $this->restaurantId();

        if ($restaurantId && (
            $this->isAdmin()
            || $this->hasPermission(Permission::BranchesView)
            || $this->hasPermission(Permission::BranchesManage)
        )) {
            return Branch::query()
                ->where('restaurant_id', $restaurantId)
                ->orderBy('name')
                ->get();
        }

        $this->loadMissing('branch');

        return new EloquentCollection(array_values(array_filter([$this->branch])));
    }
}
