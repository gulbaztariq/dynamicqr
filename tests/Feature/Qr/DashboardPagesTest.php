<?php

namespace Tests\Feature\Qr;

use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Every page renders with data present, not just on an empty database. */
class DashboardPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private QrCode $qrCode;

    private QrBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->customer = User::factory()->create();
        $this->batch = QrBatch::factory()->create(['created_by' => $this->admin->id]);

        $this->qrCode = QrCode::factory()
            ->for($this->customer, 'owner')
            ->for($this->batch, 'batch')
            ->create(['target_url' => 'https://example.com', 'label' => 'Reception standee']);

        QrScan::factory()->count(20)->create([
            'qr_code_id' => $this->qrCode->id,
            'user_id' => $this->customer->id,
            'scanned_at' => now()->subDays(rand(0, 20)),
        ]);

        QrScan::factory()->bot()->create([
            'qr_code_id' => $this->qrCode->id,
            'user_id' => $this->customer->id,
            'scanned_at' => now(),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public static function customerPages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'qr code list' => ['qr-codes.index'],
            'analytics' => ['analytics'],
            'profile' => ['profile.edit'],
        ];
    }

    #[DataProvider('customerPages')]
    public function test_customer_pages_render(string $route): void
    {
        $this->actingAs($this->customer)->get(route($route))->assertOk();
    }

    public function test_customer_qr_detail_renders(): void
    {
        $this->actingAs($this->customer)
            ->get(route('qr-codes.show', $this->qrCode))
            ->assertOk()
            ->assertSee($this->qrCode->code);
    }

    /** @return array<string, array<int, string>> */
    public static function adminPages(): array
    {
        return [
            'overview' => ['admin.dashboard'],
            'qr codes' => ['admin.qr-codes.index'],
            'new qr code' => ['admin.qr-codes.create'],
            'batches' => ['admin.batches.index'],
            'generate batch' => ['admin.batches.create'],
            'customers' => ['admin.users.index'],
            'new customer' => ['admin.users.create'],
            'analytics' => ['admin.analytics'],
            'activity log' => ['admin.activity'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_admin_pages_render(string $route): void
    {
        $this->actingAs($this->admin)->get(route($route))->assertOk();
    }

    public function test_admin_detail_pages_render(): void
    {
        $this->actingAs($this->admin)->get(route('admin.qr-codes.show', $this->qrCode))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.users.show', $this->customer))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->customer))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.batches.show', $this->batch))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.batches.print', $this->batch))->assertOk();
    }

    public function test_the_landing_and_login_pages_render(): void
    {
        $this->get('/')->assertOk();
        $this->get(route('login'))->assertOk();
    }

    public function test_a_signed_in_user_is_sent_to_the_right_dashboard(): void
    {
        $this->actingAs($this->customer)->get('/')->assertRedirect(route('dashboard'));
        $this->actingAs($this->admin)->get('/')->assertRedirect(route('admin.dashboard'));
    }

    /** Analytics must never count the link-preview bot as a visitor. */
    public function test_bot_scans_are_excluded_from_reported_totals(): void
    {
        $summary = app(AnalyticsService::class)
            ->summary(app(AnalyticsService::class)->forUser($this->customer));

        $this->assertSame(20, $summary['total']);
        $this->assertSame(21, QrScan::count());
    }
}
