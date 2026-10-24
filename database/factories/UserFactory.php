<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Permissions::ROLE_CUSTOMER,
            'status' => Permissions::STATUS_ACTIVE,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Permissions::ROLE_SUPERADMIN,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Permissions::ROLE_ADMIN,
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Permissions::ROLE_STAFF,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Permissions::ROLE_CUSTOMER,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Permissions::STATUS_ACTIVE,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Permissions::STATUS_SUSPENDED,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Permissions::STATUS_BLOCKED,
        ]);
    }

    /**
     * Grant a permission explicitly to the created user.
     */
    public function withPermission(string $permission): static
    {
        return $this->afterCreating(function (User $user) use ($permission): void {
            $user->permissionOverrides()->updateOrCreate(
                ['permission' => $permission],
                ['granted' => true],
            );
        });
    }

    /**
     * Explicitly deny a permission to the created user.
     */
    public function withoutPermission(string $permission): static
    {
        return $this->afterCreating(function (User $user) use ($permission): void {
            $user->permissionOverrides()->updateOrCreate(
                ['permission' => $permission],
                ['granted' => false],
            );
        });
    }
}
