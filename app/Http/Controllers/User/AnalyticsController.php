<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\QrScan;
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
        $user = $request->user();
        $days = $this->range($request);
        $selected = $this->selectedQrCode($request);

        $scans = $this->scoped($request);

        return view('user.analytics', [
            'days' => $days,
            'selected' => $selected,
            'qrCodes' => QrCode::ownedBy($user)->orderBy('label')->get(['id', 'uuid', 'code', 'label']),
            'summary' => $this->analytics->summary(clone $scans, $days),
            'series' => $this->analytics->timeSeries(clone $scans, $days),
            'devices' => $this->analytics->breakdown(clone $scans, 'device_type', 5, $days),
            'browsers' => $this->analytics->breakdown(clone $scans, 'browser', 8, $days),
            'operatingSystems' => $this->analytics->breakdown(clone $scans, 'os', 6, $days),
            'countries' => $this->analytics->breakdown(clone $scans, 'country_code', 10, $days),
            'referrers' => $this->analytics->breakdown(clone $scans, 'referrer_host', 8, $days),
            'hours' => $this->analytics->hourOfDay(clone $scans, $days),
            'topQrCodes' => $this->analytics->topQrCodes($user, 10, $days),
        ]);
    }

    /** Raw scan rows for the selected filters, for the customer's own reporting. */
    public function export(Request $request): StreamedResponse
    {
        $days = $this->range($request);

        $scans = $this->scoped($request)
            ->where('scanned_at', '>=', now()->startOfDay()->subDays($days - 1))
            ->with('qrCode:id,code,label')
            ->latest('scanned_at')
            ->limit(20000);

        $rows = $scans->cursor()->map(fn (QrScan $scan) => [
            $scan->scanned_at?->format('Y-m-d H:i:s'),
            $scan->qrCode?->code,
            $scan->qrCode?->label,
            $scan->device_type,
            $scan->os,
            $scan->browser,
            $scan->country_name ?: $scan->country_code,
            $scan->city,
            $scan->referrer_host ?: 'Direct',
            $scan->target_url,
            $scan->is_unique ? 'Yes' : 'No',
        ]);

        return CsvExporter::stream(
            'qr-scans-'.now()->format('Y-m-d').'.csv',
            ['Scanned at', 'Code', 'Label', 'Device', 'OS', 'Browser', 'Country', 'City', 'Source', 'Sent to', 'First-time visitor'],
            $rows,
        );
    }

    /** Scans for this customer, optionally narrowed to one of their codes. */
    private function scoped(Request $request): Builder
    {
        $scans = $this->analytics->forUser($request->user());

        if ($selected = $this->selectedQrCode($request)) {
            $scans->where('qr_code_id', $selected->id);
        }

        return $scans;
    }

    private function selectedQrCode(Request $request): ?QrCode
    {
        if (! $request->filled('qr')) {
            return null;
        }

        return QrCode::ownedBy($request->user())
            ->where('uuid', $request->string('qr')->toString())
            ->first();
    }

    private function range(Request $request): int
    {
        $days = (int) $request->integer('range', 30);

        return in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    }
}
