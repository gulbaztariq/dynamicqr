<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_single_super_admin(): void
    {
        $this->artisan('dqr:install', [
            '--name' => 'Gulbaz Tariq',
            '--email' => 'Owner@Example.com',
            '--password' => 'SuperSecret123',
            '--skip-migrations' => true,
        ])->assertSuccessful();

        $user = User::sole();

        $this->assertSame('Gulbaz Tariq', $user->name);
        // Emails are lower-cased so sign-in is not case sensitive.
        $this->assertSame('owner@example.com', $user->email);
        $this->assertSame(UserRole::SuperAdmin, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('SuperSecret123', $user->password));
    }

    /** The install path must never create the demo accounts. */
    public function test_it_does_not_create_demo_customers(): void
    {
        $this->artisan('dqr:install', [
            '--name' => 'Owner',
            '--email' => 'owner@example.com',
            '--password' => 'SuperSecret123',
            '--skip-migrations' => true,
        ])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(0, User::customers()->count());
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->artisan('dqr:install', [
            '--name' => 'Owner',
            '--email' => 'owner@example.com',
            '--password' => 'short',
            '--skip-migrations' => true,
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_it_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('dqr:install', [
            '--name' => 'Owner',
            '--email' => 'taken@example.com',
            '--password' => 'SuperSecret123',
            '--skip-migrations' => true,
        ])->assertFailed();

        $this->assertSame(1, User::count());
    }
}
