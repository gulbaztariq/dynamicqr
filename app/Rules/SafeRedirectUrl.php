<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Destination URLs are typed by customers and then served to the public, so they
 * are restricted to http/https and may not be pointed back at this dashboard
 * (which would turn every printed product into an open redirect).
 */
class SafeRedirectUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $value = trim((string) $value);

        if (mb_strlen($value) > 2000) {
            $fail('The destination link is too long.');

            return;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            $fail('Enter a full link including https:// — for example https://your-menu.com');

            return;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $fail('Only http and https links are allowed.');

            return;
        }

        $host = strtolower($parts['host']);

        $blocked = array_map('strtolower', array_filter(array_merge(
            (array) config('qr.blocked_redirect_hosts', []),
            [parse_url((string) config('app.url'), PHP_URL_HOST)],
        )));

        if (in_array($host, $blocked, true)) {
            $fail('A QR code cannot redirect back to this dashboard. Enter your own website or profile link.');
        }
    }
}
