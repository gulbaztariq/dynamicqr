<?php

namespace App\Services;

use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every number shown on the dashboards.
 *
 * Each method takes a base query so the same code powers the global admin view,
 * a single customer's view and a single QR code's view. Bots are excluded
 * everywhere — a WhatsApp link preview is not a customer.
 *
 * Dates are grouped in the application timezone (config/app.php), which is what
 * makes "scans today" mean today where the business actually operates.
 */
class AnalyticsService
{
    /** Scans for everything. */
    public function base(): Builder
    {
        return QrScan::query()->humans();
    }

    public function forUser(User $user): Builder
    {
        return $this->base()->where('user_id', $user->id);
    }

    public function forQrCode(QrCode $qrCode): Builder
    {
        return $this->base()->where('qr_code_id', $qrCode->id);
    }

    /**
     * Headline numbers with a like-for-like comparison against the preceding
     * period, so "+18% vs previous 30 days" is an honest statement.
     *
     * @return array<string, mixed>
     */
    public function summary(Builder $scans, int $days = 30): array
    {
        $today = CarbonImmutable::today();
        $periodStart = $today->subDays($days - 1)->startOfDay();
        $previousStart = $periodStart->subDays($days);

        $total = (clone $scans)->count();
        $unique = (clone $scans)->where('is_unique', true)->count();

        $current = (clone $scans)->where('scanned_at', '>=', $periodStart)->count();
        $previous = (clone $scans)
            ->whereBetween('scanned_at', [$previousStart, $periodStart->subSecond()])
            ->count();

        return [
            'total' => $total,
            'unique' => $unique,
            'today' => (clone $scans)->where('scanned_at', '>=', $today->startOfDay())->count(),
            'yesterday' => (clone $scans)->whereBetween('scanned_at', [
                $today->subDay()->startOfDay(),
                $today->subDay()->endOfDay(),
            ])->count(),
            'last_7_days' => (clone $scans)->where('scanned_at', '>=', $today->subDays(6)->startOfDay())->count(),
            'period' => $current,
            'previous_period' => $previous,
            'change_percent' => $this->percentageChange($current, $previous),
            'days' => $days,
        ];
    }

    /**
     * Daily scan counts with empty days filled in, so a chart never draws a
     * misleading straight line across a gap.
     *
     * @return array{labels: array<int, string>, dates: array<int, string>, scans: array<int, int>, unique: array<int, int>}
     */
    public function timeSeries(Builder $scans, int $days = 30): array
    {
        $end = CarbonImmutable::today();
        $start = $end->subDays(max(1, $days) - 1);

        $rows = (clone $scans)
            ->where('scanned_at', '>=', $start->startOfDay())
            ->where('scanned_at', '<=', $end->endOfDay())
            ->selectRaw($this->dateExpression('scanned_at').' as day')
            ->selectRaw('count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();

        $uniqueRows = (clone $scans)
            ->where('scanned_at', '>=', $start->startOfDay())
            ->where('is_unique', true)
            ->selectRaw($this->dateExpression('scanned_at').' as day')
            ->selectRaw('count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();

        $labels = $dates = $totals = $uniques = [];

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $key = $date->format('Y-m-d');
            $dates[] = $key;
            $labels[] = $date->format($days > 90 ? 'M Y' : 'd M');
            $totals[] = (int) ($rows[$key] ?? 0);
            $uniques[] = (int) ($uniqueRows[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'dates' => $dates,
            'scans' => $totals,
            'unique' => $uniques,
        ];
    }

    /**
     * Top values for a dimension (device, country, browser, referrer...) with
     * each row's share of the total.
     *
     * @return Collection<int, array{value: string, label: string, count: int, percent: float}>
     */
    public function breakdown(Builder $scans, string $column, int $limit = 8, ?int $days = null): Collection
    {
        $query = clone $scans;

        if ($days) {
            $query->where('scanned_at', '>=', CarbonImmutable::today()->subDays($days - 1)->startOfDay());
        }

        $rows = $query
            ->select($column, DB::raw('count(*) as aggregate'))
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();

        $total = max(1, (int) $rows->sum('aggregate'));

        return $rows->map(fn ($row) => [
            'value' => (string) ($row->{$column} ?? ''),
            'label' => $this->humanise($column, $row->{$column}),
            'count' => (int) $row->aggregate,
            'percent' => round(($row->aggregate / $total) * 100, 1),
        ]);
    }

    /**
     * Scans by hour of day — tells an NFC customer when their table traffic peaks.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public function hourOfDay(Builder $scans, int $days = 30): array
    {
        $rows = (clone $scans)
            ->where('scanned_at', '>=', CarbonImmutable::today()->subDays($days - 1)->startOfDay())
            ->selectRaw($this->hourExpression('scanned_at').' as hour')
            ->selectRaw('count(*) as total')
            ->groupBy('hour')
            ->pluck('total', 'hour')
            ->all();

        $labels = $values = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $labels[] = str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00';
            $values[] = (int) ($rows[$hour] ?? $rows[str_pad((string) $hour, 2, '0', STR_PAD_LEFT)] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Best performing codes.
     *
     * @return Collection<int, QrCode>
     */
    public function topQrCodes(?User $user = null, int $limit = 5, int $days = 30): Collection
    {
        $since = CarbonImmutable::today()->subDays($days - 1)->startOfDay();

        return QrCode::query()
            ->when($user, fn (Builder $q) => $q->where('user_id', $user->id))
            ->withCount([
                'scans as period_scans' => fn ($q) => $q->where('is_bot', false)->where('scanned_at', '>=', $since),
            ])
            ->with('owner:id,name')
            ->orderByDesc('period_scans')
            ->orderByDesc('scan_count')
            ->limit($limit)
            ->get();
    }

    private function percentageChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /** DATE() is portable across SQLite, MySQL and PostgreSQL. */
    private function dateExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlsrv' => "CONVERT(date, {$column})",
            default => "DATE({$column})",
        };
    }

    private function hourExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            'pgsql' => "EXTRACT(HOUR FROM {$column})",
            'sqlsrv' => "DATEPART(hour, {$column})",
            default => "HOUR({$column})",
        };
    }

    private function humanise(string $column, mixed $value): string
    {
        if (blank($value)) {
            return match ($column) {
                'referrer_host' => 'Direct scan',
                'country_code' => 'Unknown',
                default => 'Unknown',
            };
        }

        return match ($column) {
            'country_code' => Countries::flag($value).' '.Countries::name($value),
            'device_type' => ucfirst((string) $value),
            default => (string) $value,
        };
    }
}
