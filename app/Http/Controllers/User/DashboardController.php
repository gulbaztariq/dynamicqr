<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $days = $this->range($request);

        $scans = $this->analytics->forUser($user);

        return view('user.dashboard', [
            'days' => $days,
            'summary' => $this->analytics->summary(clone $scans, $days),
            'series' => $this->analytics->timeSeries(clone $scans, $days),
            'devices' => $this->analytics->breakdown(clone $scans, 'device_type', 5, $days),
            'countries' => $this->analytics->breakdown(clone $scans, 'country_code', 6, $days),
            'topQrCodes' => $this->analytics->topQrCodes($user, 5, $days),
            'recentScans' => (clone $scans)->with('qrCode:id,code,label')->latest('scanned_at')->limit(8)->get(),
            'counts' => [
                'total' => QrCode::ownedBy($user)->count(),
                'live' => QrCode::ownedBy($user)->status('live')->count(),
                'paused' => QrCode::ownedBy($user)->where('is_active', false)->count(),
                'unconfigured' => QrCode::ownedBy($user)->whereNull('target_url')->count(),
            ],
            'needsSetup' => QrCode::ownedBy($user)->whereNull('target_url')->limit(5)->get(),
        ]);
    }

    private function range(Request $request): int
    {
        $days = (int) $request->integer('range', 30);

        return in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    }
}
