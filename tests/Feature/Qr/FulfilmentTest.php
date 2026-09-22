<?php

namespace Tests\Feature\Qr;

use App\Models\QrCode;
use App\Models\QrCodeActivity;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The two ways stock reaches a customer. */
class FulfilmentTest extends TestCase
{
    use RefreshDatabase;

    /** Path 1: "this person bought 10 standees, create their account and codes." */
    public function test_an_admin_can_create_a_customer_with_their_codes_in_one_step(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Bilal Ahmed',
            'email' => 'bilal@example.com',
            'role' => 'user',
            'is_active' => true,
            'qr_quantity' => 10,
            'qr_label_prefix' => 'Standee',
        ])->assertRedirect();

        $customer = User::where('email', 'bilal@example.com')->sole();

        $this->assertCount(10, $customer->qrCodes);
        $this->assertSame('Standee 1', $customer->qrCodes->first()->label);
        $this->assertTrue($customer->qrCodes->every(fn (QrCode $c) => $c->user_id === $customer->id));
    }

    /** Path 2: print 100 up front, hand out 5 when somebody walks in. */
    public function test_an_admin_can_assign_a_slice_of_the_unassigned_pool_to_a_customer(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $customer = User::factory()->create();

        $pool = QrCode::factory()->count(100)->unassigned()->unconfigured()->create();
        $picked = $pool->take(5);

        $this->actingAs($admin)->post(route('admin.qr-codes.bulk'), [
            'action' => 'assign',
            'ids' => $picked->pluck('id')->all(),
            'user_id' => $customer->id,
        ])->assertRedirect();

        $this->assertSame(5, $customer->qrCodes()->count());
        $this->assertSame(95, QrCode::unassigned()->count());

        // They must now be visible in that customer's own dashboard.
        $this->actingAs($customer)
            ->get(route('qr-codes.index'))
            ->assertOk()
            ->assertSee($picked->first()->code);
    }

    public function test_generating_a_batch_can_assign_every_code_to_a_customer_immediately(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.batches.store'), [
            'name' => 'Cafe Aroma order',
            'quantity' => 12,
            'label_prefix' => 'Table',
            'user_id' => $customer->id,
        ])->assertRedirect();

        $this->assertSame(12, $customer->qrCodes()->count());
        $this->assertSame('Table 12', $customer->qrCodes()->orderByDesc('id')->first()->label);
    }

    public function test_generated_codes_are_unique(): void
    {
        $admin = User::factory()->superAdmin()->create();

        app(QrCodeService::class)->generateBatch(['name' => 'Pool', 'quantity' => 250], $admin);

        $this->assertSame(250, QrCode::count());
        $this->assertSame(250, QrCode::distinct()->count('code'));
    }

    public function test_unassigning_returns_codes_to_the_pool_without_losing_their_history(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $customer = User::factory()->create();
        $qrCode = QrCode::factory()->for($customer, 'owner')->create(['scan_count' => 42]);

        app(QrCodeService::class)->unassign([$qrCode], $admin);

        $qrCode->refresh();

        $this->assertNull($qrCode->user_id);
        $this->assertSame(42, $qrCode->scan_count);
    }

    public function test_deleting_a_customer_returns_their_codes_to_the_pool(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $customer = User::factory()->create();
        QrCode::factory()->count(3)->for($customer, 'owner')->create();

        $this->actingAs($admin)->delete(route('admin.users.destroy', $customer))->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertSame(3, QrCode::unassigned()->count());
    }

    /** A printed code must always have an answer to "who changed this, and when?" */
    public function test_every_destination_change_is_written_to_the_audit_log(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $qrCode = QrCode::factory()->create(['target_url' => 'https://first.example.com']);

        app(QrCodeService::class)->updateTargetUrl($qrCode, 'https://second.example.com', $admin);

        $entry = QrCodeActivity::where('type', QrCodeActivity::TYPE_URL_CHANGED)->sole();

        $this->assertSame('https://first.example.com', $entry->meta['from']);
        $this->assertSame('https://second.example.com', $entry->meta['to']);
        $this->assertSame($admin->id, $entry->actor_id);
    }

    public function test_the_batch_size_limit_is_enforced(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.batches.store'), ['quantity' => config('qr.max_batch_quantity') + 1])
            ->assertSessionHasErrors('quantity');
    }
}
