<?php

namespace Tests\Feature\Qr;

use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private User $admin;

    private User $customer;

    private QrCode $qrCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $this->customer = User::factory()->create(['name' => 'Bilal Ahmed']);

        $this->qrCode = QrCode::factory()->for($this->customer, 'owner')->create([
            'code' => 'AB12CD3',
            'label' => 'Reception standee',
            'target_url' => 'https://g.page/r/example/review',
        ]);

        QrScan::factory()->count(3)->create([
            'qr_code_id' => $this->qrCode->id,
            'user_id' => $this->customer->id,
        ]);
    }

    /** @return array<string, array<int, string>> */
    public static function exportRoutes(): array
    {
        return [
            'qr code urls' => ['admin.qr-codes.export'],
            'customers' => ['admin.users.export'],
            'all scans' => ['admin.analytics.export'],
        ];
    }

    #[DataProvider('exportRoutes')]
    public function test_admin_exports_download_as_excel(string $route): void
    {
        $this->actingAs($this->admin)
            ->get(route($route, ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', self::XLSX_MIME);
    }

    #[DataProvider('exportRoutes')]
    public function test_admin_exports_download_as_csv(string $route): void
    {
        $this->actingAs($this->admin)
            ->get(route($route, ['format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** Excel is the default because it is what most people actually want. */
    public function test_exports_default_to_excel_when_no_format_is_given(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.qr-codes.export'))
            ->assertOk()
            ->assertHeader('content-type', self::XLSX_MIME);
    }

    public function test_an_unknown_format_falls_back_to_excel_rather_than_erroring(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.qr-codes.export', ['format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', self::XLSX_MIME);
    }

    public function test_the_url_export_contains_the_printed_link_and_its_destination(): void
    {
        $csv = $this->actingAs($this->admin)
            ->get(route('admin.qr-codes.export', ['format' => 'csv']))
            ->streamedContent();

        $this->assertStringContainsString('AB12CD3', $csv);
        $this->assertStringContainsString($this->qrCode->short_url, $csv);
        $this->assertStringContainsString('https://g.page/r/example/review', $csv);
        $this->assertStringContainsString('Bilal Ahmed', $csv);
    }

    public function test_the_excel_export_is_a_readable_workbook_containing_the_links(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.qr-codes.export'));
        $response->assertOk();

        $sheet = $this->readFirstSheet($response);

        $this->assertStringContainsString('AB12CD3', $sheet);
        $this->assertStringContainsString('g.page/r/example/review', $sheet);
    }

    public function test_the_url_export_respects_the_filters_on_screen(): void
    {
        $other = QrCode::factory()->unassigned()->create(['code' => 'ZZ99YY1']);

        $csv = $this->actingAs($this->admin)
            ->get(route('admin.qr-codes.export', ['format' => 'csv', 'user_id' => $this->customer->id]))
            ->streamedContent();

        $this->assertStringContainsString('AB12CD3', $csv);
        $this->assertStringNotContainsString($other->code, $csv);
    }

    public function test_a_customer_can_export_their_own_links(): void
    {
        $csv = $this->actingAs($this->customer)
            ->get(route('qr-codes.export', ['format' => 'csv']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('AB12CD3', $csv);
        $this->assertStringContainsString($this->qrCode->short_url, $csv);
    }

    /** A customer's export must never leak another customer's codes. */
    public function test_a_customer_export_only_contains_their_own_codes(): void
    {
        $theirs = QrCode::factory()->for(User::factory()->create(), 'owner')->create(['code' => 'XX55WW2']);

        $csv = $this->actingAs($this->customer)
            ->get(route('qr-codes.export', ['format' => 'csv']))
            ->streamedContent();

        $this->assertStringContainsString('AB12CD3', $csv);
        $this->assertStringNotContainsString($theirs->code, $csv);
    }

    public function test_a_customer_can_export_their_scans_as_excel(): void
    {
        $this->actingAs($this->customer)
            ->get(route('analytics.export', ['format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', self::XLSX_MIME);
    }

    public function test_guests_cannot_export(): void
    {
        $this->get(route('qr-codes.export'))->assertRedirect(route('login'));
        $this->get(route('admin.qr-codes.export'))->assertRedirect(route('login'));
    }

    /** Regression: this once returned a 500 from a wrong return type. */
    public function test_the_qr_artwork_zip_downloads(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.qr-codes.download'));

        $response->assertOk()->assertHeader('content-type', 'application/zip');
        $this->assertGreaterThan(0, strlen($this->contentOf($response)));
    }

    public function test_a_batch_artwork_zip_downloads(): void
    {
        $batch = QrBatch::factory()->create();
        QrCode::factory()->count(2)->for($batch, 'batch')->create();

        $this->actingAs($this->admin)
            ->get(route('admin.batches.download', $batch))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');
    }

    private function readFirstSheet(TestResponse $response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsxtest');
        file_put_contents($path, $this->contentOf($response));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'The export is not a readable zip archive.');

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($sheet, 'The workbook has no first worksheet.');

        return $sheet;
    }

    private function contentOf(TestResponse $response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return (string) ob_get_clean();
    }
}
