<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_always_creates_an_active_customer(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Nadia Customer')
            ->set('email', 'Nadia@Example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'nadia@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('customer', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_component_exposes_no_privileged_role_or_status_input(): void
    {
        $properties = collect((new ReflectionClass(Register::class))->getProperties(ReflectionProperty::IS_PUBLIC))
            ->pluck('name');

        $this->assertFalse($properties->contains('role'));
        $this->assertFalse($properties->contains('status'));
    }

    public function test_registration_requires_a_unique_email_and_hashes_the_password(): void
    {
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        Livewire::test(Register::class)
            ->set('name', 'Another User')
            ->set('email', $existing->email)
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasErrors(['email']);

        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());

        Livewire::test(Register::class)
            ->set('name', 'Fresh User')
            ->set('email', 'fresh@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasNoErrors();

        $fresh = User::query()->where('email', 'fresh@example.com')->firstOrFail();

        $this->assertNotSame('secret-password', $fresh->password);
        $this->assertTrue(password_verify('secret-password', $fresh->password));
    }
}
