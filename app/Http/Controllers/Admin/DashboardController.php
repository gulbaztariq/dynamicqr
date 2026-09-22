<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\QrBatch;
use App\Models\QrCode;
use App\Models\QrCodeActivity;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function __invoke(Request $request): View
    {
        $days = $this->range($request);
        $scans = $this->analytics->base();

        return view('admin.dashboard', [
            'days' => $days,
            'summary' => $this->analytics->summary(clone $scans, $days),
            'series' => $this->analytics->timeSeries(clone $scans, $days),
            'devices' => $this->analytics->breakdown(clone $scans, 'device_type', 5, $days),
            'countries' => $this->analytics->breakdown(clone $scans, 'country_code', 6, $days),
            'topQrCodes' => $this->analytics->topQrCodes(null, 6, $days),
            'inventory' => [
                'total' => QrCode::count(),
                'assigned' => QrCode::whereNotNull('user_id')->count(),
                'unassigned' => QrCode::whereNull('user_id')->count(),
                'live' => QrCode::status('live')->count(),
                'unconfigured' => QrCode::whereNull('target_url')->count(),
                'paused' => QrCode::where('is_active', false)->count(),
                'batches' => QrBatch::count(),
            ],
            'customers' => [
                'total' => User::customers()->count(),
                'active' => User::customers()->where('is_active', true)->count(),
                'new_this_month' => User::customers()->where('created_at', '>=', now()->startOfMonth())->count(),
                'staff' => User::where('role', '!=', UserRole::User->value)->count(),
            ],
            // Customers holding codes that still point nowhere: the follow-up list.
            'awaitingSetup' => User::customers()
                ->withCount(['qrCodes as pending_count' => fn ($q) => $q->whereNull('target_url')])
                // whereHas rather than having(): withCount builds a correlated
                // subquery, not an aggregate, so HAVING is invalid without a GROUP BY.
                ->whereHas('qrCodes', fn ($q) => $q->whereNull('target_url'))
                ->orderByDesc('pending_count')
                ->limit(5)
                ->get(),
            'recentActivity' => QrCodeActivity::with(['actor:id,name', 'qrCode:id,uuid,code,label'])
                ->latest()
                ->limit(10)
                ->get(),
            'recentCustomers' => User::customers()->withCount('qrCodes')->latest()->limit(5)->get(),
        ]);
    }

    private function range(Request $request): int
    {
        $days = (int) $request->integer('range', 30);

        return in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    }
}
