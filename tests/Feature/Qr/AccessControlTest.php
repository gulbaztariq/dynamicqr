<?php

namespace Tests\Feature\Qr;

use App\Models\QrCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_only_sees_their_own_codes(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $myCode = QrCode::factory()->for($mine, 'owner')->create(['label' => 'My standee']);
        $theirCode = QrCode::factory()->for($theirs, 'owner')->create(['label' => 'Their standee']);

        $this->actingAs($mine)
            ->get(route('qr-codes.index'))
            ->assertOk()
            ->assertSee($myCode->code)
            ->assertDontSee($theirCode->code);
    }

    public function test_a_customer_cannot_open_another_customers_code(): void
    {
        $intruder = User::factory()->create();
        $qrCode = QrCode::factory()->for(User::factory()->create(), 'owner')->create();

        $this->actingAs($intruder)
            ->get(route('qr-codes.show', $qrCode))
            ->assertForbidden();
    }

    public function test_a_customer_cannot_repoint_another_customers_code(): void
    {
        $intruder = User::factory()->create();
        $qrCode = QrCode::factory()->for(User::factory()->create(), 'owner')
            ->create(['target_url' => 'https://original.example.com']);

        $this->actingAs($intruder)
            ->patch(route('qr-codes.update', $qrCode), ['target_url' => 'https://hijacked.example.com'])
            ->assertForbidden();

        $this->assertSame('https://original.example.com', $qrCode->fresh()->target_url);
    }

    public function test_a_customer_can_repoint_their_own_code(): void
    {
        $user = User::factory()->create();
        $qrCode = QrCode::factory()->for($user, 'owner')->create(['target_url' => 'https://old.example.com']);

        $this->actingAs($user)
            ->patch(route('qr-codes.update', $qrCode), ['target_url' => 'https://new.example.com'])
            ->assertRedirect();

        $this->assertSame('https://new.example.com', $qrCode->fresh()->target_url);
    }

    /** A super admin can lock an individual code so the customer cannot move it. */
    public function test_a_locked_code_cannot_be_changed_by_its_owner_but_can_by_an_admin(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->superAdmin()->create();
        $qrCode = QrCode::factory()->locked()->for($user, 'owner')->create(['target_url' => 'https://fixed.example.com']);

        $this->actingAs($user)
            ->patch(route('qr-codes.update', $qrCode), ['target_url' => 'https://nope.example.com'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.qr-codes.update', $qrCode), ['target_url' => 'https://admin-set.example.com'])
            ->assertRedirect();

        $this->assertSame('https://admin-set.example.com', $qrCode->fresh()->target_url);
    }

    public function test_customers_cannot_reach_the_admin_area(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_a_plain_admin_cannot_delete_qr_codes(): void
    {
        $admin = User::factory()->admin()->create();
        $qrCode = QrCode::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.qr-codes.destroy', $qrCode))
            ->assertForbidden();

        $this->assertDatabaseHas('qr_codes', ['id' => $qrCode->id, 'deleted_at' => null]);
    }

    /** Suspending an account must not break products already in the field. */
    public function test_a_suspended_customer_is_logged_out_but_their_codes_keep_redirecting(): void
    {
        $user = User::factory()->suspended()->create();
        $qrCode = QrCode::factory()->for($user, 'owner')->create(['target_url' => 'https://example.com']);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

        $this->get('/q/'.$qrCode->code)->assertRedirect('https://example.com');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_public_registration_is_disabled_by_default(): void
    {
        $this->assertFalse(config('app.allow_registration'));
        $this->get('/register')->assertNotFound();
    }

    /** A customer must not be able to turn a printed product into an open redirect. */
    public function test_a_destination_cannot_point_back_at_the_dashboard(): void
    {
        $user = User::factory()->create();
        $qrCode = QrCode::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->patch(route('qr-codes.update', $qrCode), ['target_url' => config('app.url').'/admin'])
            ->assertSessionHasErrors('target_url');
    }

    public function test_javascript_urls_are_rejected(): void
    {
        $user = User::factory()->create();
        $qrCode = QrCode::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->patch(route('qr-codes.update', $qrCode), ['target_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('target_url');
    }
}
