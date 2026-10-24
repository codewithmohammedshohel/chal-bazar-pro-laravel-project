<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProvisionOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'Str0ng-Passw0rd!';

    public function test_it_provisions_a_superadmin_owner_from_the_cli(): void
    {
        $this->artisan('app:provision-owner', ['email' => 'owner@example.com'])
            ->expectsQuestion('Owner name', 'Store Owner')
            ->expectsQuestion('Password (min 12 chars, upper/lowercase, number, symbol)', self::STRONG_PASSWORD)
            ->expectsQuestion('Confirm password', self::STRONG_PASSWORD)
            ->assertExitCode(0);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame('superadmin', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_it_refuses_to_overwrite_an_existing_account_without_force(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('app:provision-owner', ['email' => 'owner@example.com'])
            ->assertExitCode(1);
    }

    public function test_it_can_promote_an_existing_account_with_force(): void
    {
        User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('app:provision-owner', ['email' => 'owner@example.com', '--force' => true])
            ->expectsQuestion('Owner name', 'Promoted Owner')
            ->expectsQuestion('Password (min 12 chars, upper/lowercase, number, symbol)', self::STRONG_PASSWORD)
            ->expectsQuestion('Confirm password', self::STRONG_PASSWORD)
            ->assertExitCode(0);

        $this->assertSame('superadmin', User::query()->where('email', 'owner@example.com')->value('role'));
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->artisan('app:provision-owner', ['email' => 'owner@example.com'])
            ->expectsQuestion('Owner name', 'Store Owner')
            ->expectsQuestion('Password (min 12 chars, upper/lowercase, number, symbol)', 'weak')
            ->expectsQuestion('Confirm password', 'weak')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'owner@example.com']);
    }
}
