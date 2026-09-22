<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\QrCode\UpdateQrCodeRequest;
use App\Models\QrCode;
use App\Services\AnalyticsService;
use App\Services\QrCodeService;
use App\Services\QrImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QrCodeController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodes,
        private readonly AnalyticsService $analytics,
        private readonly QrImageService $images,
    ) {}

    public function index(Request $request): View
    {
        $qrCodes = QrCode::ownedBy($request->user())
            ->search($request->string('q')->toString())
            ->status($request->string('status')->toString())
            ->orderByRaw('CASE WHEN target_url IS NULL THEN 0 ELSE 1 END') // Unconfigured codes first — they need attention.
            ->orderByDesc('scan_count')
            ->paginate(15)
            ->withQueryString();

        return view('user.qr-codes.index', [
            'qrCodes' => $qrCodes,
            'filters' => $request->only('q', 'status'),
            'counts' => [
                'all' => QrCode::ownedBy($request->user())->count(),
                'live' => QrCode::ownedBy($request->user())->status('live')->count(),
                'paused' => QrCode::ownedBy($request->user())->where('is_active', false)->count(),
                'unconfigured' => QrCode::ownedBy($request->user())->whereNull('target_url')->count(),
            ],
        ]);
    }

    public function show(Request $request, QrCode $qrCode): View
    {
        $this->authorize('view', $qrCode);

        $days = $this->range($request);
        $scans = $this->analytics->forQrCode($qrCode);

        return view('user.qr-codes.show', [
            'qrCode' => $qrCode->load('owner', 'batch'),
            'days' => $days,
            'summary' => $this->analytics->summary(clone $scans, $days),
            'series' => $this->analytics->timeSeries(clone $scans, $days),
            'devices' => $this->analytics->breakdown(clone $scans, 'device_type', 5, $days),
            'browsers' => $this->analytics->breakdown(clone $scans, 'browser', 6, $days),
            'countries' => $this->analytics->breakdown(clone $scans, 'country_code', 8, $days),
            'referrers' => $this->analytics->breakdown(clone $scans, 'referrer_host', 6, $days),
            'hours' => $this->analytics->hourOfDay(clone $scans, $days),
            'recentScans' => (clone $scans)->latest('scanned_at')->limit(10)->get(),
            'history' => $qrCode->activities()->with('actor:id,name')->limit(15)->get(),
            'preview' => $this->images->dataUri($qrCode, ['size' => 420]),
            'canEdit' => $request->user()->can('updateTargetUrl', $qrCode),
        ]);
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode): RedirectResponse
    {
        $data = $request->validated();

        $this->qrCodes->updateTargetUrl($qrCode, $data['target_url'] ?? null, $request->user());

        $qrCode->fill([
            'label' => $data['label'] ?? $qrCode->label,
            'notes' => $data['notes'] ?? $qrCode->notes,
        ]);

        if ($qrCode->isDirty()) {
            $qrCode->save();
        }

        return back()->with('status', 'Destination updated. Every printed copy of this QR now points to the new link.');
    }

    /** Pause or resume a code without changing its destination. */
    public function toggle(Request $request, QrCode $qrCode): RedirectResponse
    {
        $this->authorize('togglePause', $qrCode);

        $this->qrCodes->setActive([$qrCode], ! $qrCode->is_active, $request->user());

        return back()->with('status', $qrCode->fresh()->is_active
            ? 'QR code resumed — it is redirecting again.'
            : 'QR code paused — scans will see a "temporarily unavailable" page.');
    }

    private function range(Request $request): int
    {
        $days = (int) $request->integer('range', 30);

        return in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    }
}
