<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleSlug;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function slugEnum(): RoleSlug
    {
        return RoleSlug::from($this->slug);
    }

    public function isAdmin(): bool
    {
        return $this->slug === RoleSlug::Admin->value;
    }
}
