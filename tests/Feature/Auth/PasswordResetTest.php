<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_notification_is_sent_using_fake_mail(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        Livewire::test(ForgotPassword::class)
            ->set('email', 'reset@example.com')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('email', '');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'reset@example.com']);
    }

    public function test_unknown_email_gets_the_same_generic_response_and_no_notification(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'missing@example.com')
            ->call('send')
            ->assertHasNoErrors();

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'missing@example.com']);
    }

    public function test_forgot_password_requests_are_rate_limited(): void
    {
        Notification::fake();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            Livewire::test(ForgotPassword::class)
                ->set('email', 'missing@example.com')
                ->call('send')
                ->assertHasNoErrors();
        }

        Livewire::test(ForgotPassword::class)
            ->set('email', 'missing@example.com')
            ->call('send')
            ->assertHasErrors(['email']);
    }

    public function test_a_valid_token_resets_the_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', 'reset@example.com')
            ->set('password', 'brand-new-password')
            ->set('password_confirmation', 'brand-new-password')
            ->call('performReset')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);

        Livewire::test(ResetPassword::class, ['token' => 'invalid-token'])
            ->set('email', 'reset@example.com')
            ->set('password', 'brand-new-password')
            ->set('password_confirmation', 'brand-new-password')
            ->call('performReset')
            ->assertHasErrors(['email']);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
