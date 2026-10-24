<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_user_can_login_and_the_session_is_regenerated(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com']);
        $before = session()->getId();

        Livewire::test(Login::class)
            ->set('email', 'STAFF@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'staff@example.com']);

        Livewire::test(Login::class)
            ->set('email', 'staff@example.com')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_guests_cannot_reach_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authentication_pages_and_the_dashboard_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/example-token')->assertOk();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee($user->role)
            ->assertSee($user->status);
    }

    public function test_suspended_and_blocked_users_cannot_reach_protected_routes(): void
    {
        foreach (['suspended', 'blocked'] as $status) {
            $user = User::factory()->{$status}()->create();
            $this->actingAs($user);

            $this->get(route('dashboard'))
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_suspended_and_blocked_users_cannot_log_in_even_with_the_correct_password(): void
    {
        User::factory()->suspended()->create(['email' => 'suspended@example.com']);
        User::factory()->blocked()->create(['email' => 'blocked@example.com']);

        foreach (['suspended', 'blocked'] as $status) {
            Livewire::test(Login::class)
                ->set('email', $status.'@example.com')
                ->set('password', 'password')
                ->call('login')
                ->assertHasErrors(['email']);

            $this->assertGuest();
        }
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'throttle@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            Livewire::test(Login::class)
                ->set('email', 'throttle@example.com')
                ->set('password', 'wrong-password')
                ->call('login')
                ->assertHasErrors(['email']);
        }

        Livewire::test(Login::class)
            ->set('email', 'throttle@example.com')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }
}
