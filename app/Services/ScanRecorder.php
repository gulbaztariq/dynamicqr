<?php

namespace App\Services;

use App\Jobs\ResolveScanLocation;
use App\Models\QrCode;
use App\Models\QrScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes one row per scan and keeps the denormalised counters on qr_codes fresh.
 *
 * This runs inside the redirect request, so it must stay cheap and must never
 * be able to break the redirect itself — a visitor standing in front of a
 * standee should always land somewhere, even if analytics fails.
 */
class ScanRecorder
{
    public function __construct(private readonly UserAgentParser $agents) {}

    public function record(QrCode $qrCode, Request $request): ?QrScan
    {
        try {
            return $this->write($qrCode, $request);
        } catch (\Throwable $e) {
            Log::warning('Failed to record QR scan', [
                'qr_code' => $qrCode->code,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function write(QrCode $qrCode, Request $request): QrScan
    {
        $agent = $this->agents->parse($request->userAgent());
        $ipHash = $this->hashIp($request->ip());
        $referrer = $request->headers->get('referer');

        $scan = new QrScan([
            'qr_code_id' => $qrCode->id,
            'user_id' => $qrCode->user_id,
            'ip_hash' => $ipHash,
            'country_code' => $this->countryFromHeaders($request),
            'device_type' => $agent['device_type'],
            'os' => $agent['os'],
            'browser' => $agent['browser'],
            'is_bot' => $agent['is_bot'],
            'is_unique' => ! $agent['is_bot'] && $this->isFirstVisit($qrCode, $ipHash),
            'referrer_host' => $referrer ? parse_url($referrer, PHP_URL_HOST) : null,
            'referrer_url' => $referrer ? mb_substr($referrer, 0, 2000) : null,
            'target_url' => $qrCode->target_url,
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'scanned_at' => now(),
        ]);

        $scan->country_name = $scan->country_code
            ? Countries::name($scan->country_code)
            : null;

        $scan->save();

        // Bots never move the numbers the customer is shown.
        if (! $scan->is_bot) {
            $this->bumpCounters($qrCode, $scan->is_unique);
        }

        if ($this->shouldLookUpLocation($scan)) {
            ResolveScanLocation::dispatch($scan->id, $request->ip());
        }

        return $scan;
    }

    private function bumpCounters(QrCode $qrCode, bool $isUnique): void
    {
        // Raw increments so concurrent scans of the same code cannot lose a count.
        DB::table('qr_codes')->where('id', $qrCode->id)->update([
            'scan_count' => DB::raw('scan_count + 1'),
            'unique_scan_count' => DB::raw('unique_scan_count + '.($isUnique ? 1 : 0)),
            'last_scanned_at' => now(),
        ]);
    }

    private function isFirstVisit(QrCode $qrCode, ?string $ipHash): bool
    {
        if ($ipHash === null) {
            return true;
        }

        $window = (int) config('qr.tracking.unique_window_hours', 24);

        return ! QrScan::query()
            ->where('qr_code_id', $qrCode->id)
            ->where('ip_hash', $ipHash)
            ->where('scanned_at', '>=', now()->subHours($window))
            ->exists();
    }

    /**
     * Raw IPs are deliberately not stored. The hash exists only so a repeat scan
     * from the same visitor can be recognised, and it is salted so the table is
     * not reversible with a rainbow table of the IPv4 space.
     */
    private function hashIp(?string $ip): ?string
    {
        if (blank($ip)) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('qr.tracking.ip_salt'));
    }

    /** Free country data when the site sits behind Cloudflare or a similar proxy. */
    private function countryFromHeaders(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Vercel-IP-Country', 'X-Country-Code', 'X-Geo-Country'] as $header) {
            $value = $request->headers->get($header);

            if (filled($value) && strlen($value) === 2 && ctype_alpha($value) && strtoupper($value) !== 'XX') {
                return strtoupper($value);
            }
        }

        return null;
    }

    private function shouldLookUpLocation(QrScan $scan): bool
    {
        return config('qr.tracking.geo_lookup')
            && ! $scan->is_bot
            && blank($scan->country_code);
    }
}
