<?php

namespace App\Jobs;

use App\Models\QrScan;
use App\Services\Countries;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Optional IP -> country lookup, queued so it never delays a redirect.
 *
 * Disabled unless QR_GEO_LOOKUP=true. When the app sits behind Cloudflare the
 * CF-IPCountry header already supplies this for free and the job never runs.
 */
class ResolveScanLocation implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 15;

    public function __construct(
        public readonly int $scanId,
        public readonly ?string $ip,
    ) {}

    public function handle(): void
    {
        if (! config('qr.tracking.geo_lookup') || blank($this->ip)) {
            return;
        }

        $scan = QrScan::find($this->scanId);

        if (! $scan || filled($scan->country_code)) {
            return;
        }

        $endpoint = str_replace('{ip}', urlencode($this->ip), (string) config('qr.tracking.geo_endpoint'));

        try {
            $response = Http::timeout(8)->acceptJson()->get($endpoint);
        } catch (\Throwable $e) {
            Log::info('Geo lookup failed', ['error' => $e->getMessage()]);

            return;
        }

        if (! $response->successful() || $response->json('status') === 'fail') {
            return;
        }

        $countryCode = $response->json('countryCode');

        if (blank($countryCode)) {
            return;
        }

        $scan->forceFill([
            'country_code' => strtoupper($countryCode),
            'country_name' => $response->json('country') ?: Countries::name($countryCode),
            'city' => $response->json('city'),
        ])->save();
    }
}
