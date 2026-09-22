<?php

namespace Tests\Feature\Qr;

use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\User;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_live_code_redirects_to_its_destination(): void
    {
        $qrCode = QrCode::factory()->create(['target_url' => 'https://example.com/menu']);

        $this->get('/q/'.$qrCode->code)
            ->assertRedirect('https://example.com/menu');
    }

    /**
     * A 301 would be cached by the visitor's browser and the customer's next
     * destination change would silently never reach them.
     */
    public function test_the_redirect_is_temporary_and_not_cacheable(): void
    {
        $qrCode = QrCode::factory()->create(['target_url' => 'https://example.com']);

        $response = $this->get('/q/'.$qrCode->code);

        $response->assertStatus(302);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_changing_the_destination_changes_where_the_same_code_goes(): void
    {
        $qrCode = QrCode::factory()->create(['target_url' => 'https://old-site.example.com']);

        $this->get('/q/'.$qrCode->code)->assertRedirect('https://old-site.example.com');

        app(QrCodeService::class)->updateTargetUrl($qrCode, 'https://new-site.example.com');

        $this->get('/q/'.$qrCode->code)->assertRedirect('https://new-site.example.com');
    }

    public function test_codes_are_matched_regardless_of_case(): void
    {
        $qrCode = QrCode::factory()->create(['target_url' => 'https://example.com']);

        $this->get('/q/'.strtolower($qrCode->code))->assertRedirect('https://example.com');
    }

    public function test_an_unknown_code_shows_a_not_found_page(): void
    {
        $this->get('/q/ZZZZZZZ')->assertNotFound();
    }

    public function test_a_code_with_no_destination_shows_a_holding_page_instead_of_erroring(): void
    {
        $qrCode = QrCode::factory()->unconfigured()->create();

        $this->get('/q/'.$qrCode->code)
            ->assertOk()
            ->assertSee('not been set up', false);
    }

    public function test_a_paused_code_does_not_redirect(): void
    {
        $qrCode = QrCode::factory()->paused()->create(['target_url' => 'https://example.com']);

        $response = $this->get('/q/'.$qrCode->code);

        $response->assertStatus(404);
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_a_scan_is_recorded_with_device_details(): void
    {
        $qrCode = QrCode::factory()->create(['target_url' => 'https://example.com']);

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            'CF-IPCountry' => 'PK',
        ])->get('/q/'.$qrCode->code);

        $scan = QrScan::sole();

        $this->assertSame('mobile', $scan->device_type);
        $this->assertSame('iOS', $scan->os);
        $this->assertSame('Safari', $scan->browser);
        $this->assertSame('PK', $scan->country_code);
        $this->assertSame('Pakistan', $scan->country_name);
        $this->assertTrue($scan->is_unique);
        $this->assertSame(1, $qrCode->fresh()->scan_count);
    }

    /** Raw IPs must never reach the database. */
    public function test_the_visitor_ip_is_stored_only_as_a_salted_hash(): void
    {
        $qrCode = QrCode::factory()->create(['target_url' => 'https://example.com']);

        $this->get('/q/'.$qrCode->code);

        $scan = QrScan::sole();

        $this->assertNotNull($scan->ip_hash);
        $this->assertSame(64, strlen($scan->ip_hash));
        $this->assertStringNotContainsString('127.0.0.1', json_encode($scan->toArray()));
    }

    /** WhatsApp/Facebook link previews must not inflate a customer's numbers. */
    public function test_bot_traffic_is_recorded_but_excluded_from_the_counters(): void
    {
        $qrCode = QrCode::factory()->create(['target_url' => 'https://example.com']);

        $this->withHeaders(['User-Agent' => 'WhatsApp/2.23.20.0'])->get('/q/'.$qrCode->code);

        $this->assertTrue(QrScan::sole()->is_bot);
        $this->assertSame(0, $qrCode->fresh()->scan_count);
    }

    public function test_a_repeat_scan_from_the_same_visitor_is_not_counted_as_unique(): void
    {
        $qrCode = QrCode::factory()->create(['target_url' => 'https://example.com']);

        $this->get('/q/'.$qrCode->code);
        $this->get('/q/'.$qrCode->code);

        $qrCode->refresh();

        $this->assertSame(2, $qrCode->scan_count);
        $this->assertSame(1, $qrCode->unique_scan_count);
    }

    public function test_scans_stay_attributed_to_the_owner_of_the_code(): void
    {
        $user = User::factory()->create();
        $qrCode = QrCode::factory()->for($user, 'owner')->create(['target_url' => 'https://example.com']);

        $this->get('/q/'.$qrCode->code);

        $this->assertSame($user->id, QrScan::sole()->user_id);
    }
}
