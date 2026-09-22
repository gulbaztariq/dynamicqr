<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QrScan;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Support\CsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $days = $this->range($request);
        $scans = $this->scoped($request);

        return view('admin.analytics', [
            'days' => $days,
            'filters' => $request->only('user_id'),
            'customers' => User::customers()->orderBy('name')->get(['id', 'name', 'company']),
            'summary' => $this->analytics->summary(clone $scans, $days),
            'series' => $this->analytics->timeSeries(clone $scans, $days),
            'devices' => $this->analytics->breakdown(clone $scans, 'device_type', 5, $days),
            'browsers' => $this->analytics->breakdown(clone $scans, 'browser', 8, $days),
            'operatingSystems' => $this->analytics->breakdown(clone $scans, 'os', 6, $days),
            'countries' => $this->analytics->breakdown(clone $scans, 'country_code', 10, $days),
            'referrers' => $this->analytics->breakdown(clone $scans, 'referrer_host', 8, $days),
            'hours' => $this->analytics->hourOfDay(clone $scans, $days),
            'topQrCodes' => $this->analytics->topQrCodes(null, 10, $days),
            'topCustomers' => $this->topCustomers($days),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $days = $this->range($request);

        $rows = $this->scoped($request)
            ->where('scanned_at', '>=', now()->startOfDay()->subDays($days - 1))
            ->with(['qrCode:id,code,label', 'owner:id,name'])
            ->latest('scanned_at')
            ->limit(50000)
            ->cursor()
            ->map(fn (QrScan $scan) => [
                $scan->scanned_at?->format('Y-m-d H:i:s'),
                $scan->qrCode?->code,
                $scan->qrCode?->label,
                $scan->owner?->name,
                $scan->device_type,
                $scan->os,
                $scan->browser,
                $scan->country_name ?: $scan->country_code,
                $scan->referrer_host ?: 'Direct',
                $scan->target_url,
                $scan->is_unique ? 'Yes' : 'No',
            ]);

        return CsvExporter::stream(
            'all-scans-'.now()->format('Y-m-d').'.csv',
            ['Scanned at', 'Code', 'Label', 'Customer', 'Device', 'OS', 'Browser', 'Country', 'Source', 'Sent to', 'First-time visitor'],
            $rows,
        );
    }

    /** Which customers are actually getting scans — the retention signal. */
    private function topCustomers(int $days)
    {
        return User::customers()
            ->withCount([
                'scans as period_scans' => fn ($q) => $q
                    ->where('is_bot', false)
                    ->where('scanned_at', '>=', now()->startOfDay()->subDays($days - 1)),
            ])
            ->withCount('qrCodes')
            ->orderByDesc('period_scans')
            ->limit(10)
            ->get();
    }

    private function scoped(Request $request): Builder
    {
        return $this->analytics->base()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')));
    }

    private function range(Request $request): int
    {
        $days = (int) $request->integer('range', 30);

        return in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    }
}
