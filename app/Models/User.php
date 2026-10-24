<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<UserPermissionOverride, $this>
     */
    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function isActive(): bool
    {
        return $this->status === Permissions::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === Permissions::STATUS_SUSPENDED;
    }

    public function isBlocked(): bool
    {
        return $this->status === Permissions::STATUS_BLOCKED;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === Permissions::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === Permissions::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === Permissions::ROLE_STAFF;
    }

    public function isCustomer(): bool
    {
        return $this->role === Permissions::ROLE_CUSTOMER;
    }

    /**
     * Resolve a permission for this account.
     *
     * Precedence:
     *  1. Inactive accounts never hold any permission.
     *  2. An active superadmin bypasses role/override checks for catalogue keys.
     *  3. An explicit per-user override wins over the role default.
     *  4. Otherwise the role default applies.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive() || ! Permissions::isValid($permission)) {
            return false;
        }

        if ($this->isSuperadmin()) {
            return true;
        }

        $override = $this->permissionOverrides()
            ->where('permission', $permission)
            ->first();

        if ($override !== null) {
            return $override->granted;
        }

        return in_array($permission, Permissions::roleDefaults((string) $this->role), true);
    }

    /**
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        if (! $this->isActive()) {
            return [];
        }

        if ($this->isSuperadmin()) {
            return Permissions::all();
        }

        $permissions = Permissions::roleDefaults((string) $this->role);

        foreach ($this->permissionOverrides()->get() as $override) {
            if (! Permissions::isValid($override->permission)) {
                continue;
            }

            $key = array_search($override->permission, $permissions, true);

            if ($override->granted) {
                if ($key === false) {
                    $permissions[] = $override->permission;
                }

                continue;
            }

            if ($key !== false) {
                unset($permissions[$key]);
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', Permissions::STATUS_ACTIVE);
    }
}
