<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public sign-up is off by default: customers get an account when they buy
 * hardware. These tests cover the flag in both positions.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_unavailable_by_default(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registration_works_when_the_flag_is_enabled(): void
    {
        $this->enableRegistration();

        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    private function enableRegistration(): void
    {
        config(['app.allow_registration' => true]);
    }
}
