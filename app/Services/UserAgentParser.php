<?php

namespace App\Services;

/**
 * A small, dependency-free user agent parser.
 *
 * QR traffic is overwhelmingly phone camera apps and in-app browsers, so this
 * deliberately covers that long tail (WhatsApp, Instagram, Facebook, WeChat)
 * rather than every desktop browser ever shipped. Anything unrecognised is
 * reported as "Other" instead of being silently dropped.
 */
class UserAgentParser
{
    /** Preview fetchers and crawlers. These inflate scan counts if counted as people. */
    private const BOT_PATTERNS = [
        'bot', 'crawler', 'spider', 'crawling', 'facebookexternalhit', 'facebookcatalog',
        'whatsapp', 'telegrambot', 'slackbot', 'twitterbot', 'discordbot', 'linkedinbot',
        'pinterest', 'redditbot', 'embedly', 'quora link preview', 'skypeuripreview',
        'applebot', 'googlebot', 'bingbot', 'yandex', 'duckduckbot', 'baiduspider',
        'curl/', 'wget/', 'python-requests', 'okhttp', 'go-http-client', 'headlesschrome',
        'phantomjs', 'lighthouse', 'pingdom', 'uptimerobot', 'axios/', 'postman',
    ];

    /** @return array{device_type: string, os: string, browser: string, is_bot: bool} */
    public function parse(?string $userAgent): array
    {
        $ua = strtolower(trim((string) $userAgent));

        if ($ua === '') {
            return [
                'device_type' => 'unknown',
                'os' => 'Unknown',
                'browser' => 'Unknown',
                'is_bot' => true,
            ];
        }

        if ($this->isBot($ua)) {
            return [
                'device_type' => 'bot',
                'os' => $this->detectOs($ua),
                'browser' => 'Link preview / bot',
                'is_bot' => true,
            ];
        }

        return [
            'device_type' => $this->detectDeviceType($ua),
            'os' => $this->detectOs($ua),
            'browser' => $this->detectBrowser($ua),
            'is_bot' => false,
        ];
    }

    public function isBot(string $ua): bool
    {
        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function detectDeviceType(string $ua): string
    {
        if (str_contains($ua, 'ipad') || (str_contains($ua, 'android') && ! str_contains($ua, 'mobile'))) {
            return 'tablet';
        }

        if (str_contains($ua, 'tablet') || str_contains($ua, 'kindle') || str_contains($ua, 'playbook')) {
            return 'tablet';
        }

        $mobileHints = ['mobile', 'iphone', 'ipod', 'android', 'windows phone', 'blackberry', 'opera mini', 'iemobile'];
        foreach ($mobileHints as $hint) {
            if (str_contains($ua, $hint)) {
                return 'mobile';
            }
        }

        return 'desktop';
    }

    private function detectOs(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'windows nt') => 'Windows',
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod') => 'iOS',
            str_contains($ua, 'mac os x') || str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'harmonyos') => 'HarmonyOS',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'cros') => 'ChromeOS',
            str_contains($ua, 'ubuntu') => 'Ubuntu',
            str_contains($ua, 'linux') => 'Linux',
            default => 'Other',
        };
    }

    private function detectBrowser(string $ua): string
    {
        // In-app browsers must be checked first: they all still say "Safari" or
        // "Chrome" further along the user agent string.
        return match (true) {
            str_contains($ua, 'instagram') => 'Instagram',
            str_contains($ua, 'fban') || str_contains($ua, 'fbav') || str_contains($ua, 'fb_iab') => 'Facebook',
            str_contains($ua, 'micromessenger') => 'WeChat',
            str_contains($ua, 'line/') => 'LINE',
            str_contains($ua, 'snapchat') => 'Snapchat',
            str_contains($ua, 'tiktok') || str_contains($ua, 'musical_ly') => 'TikTok',
            str_contains($ua, 'gsa/') => 'Google App',
            str_contains($ua, 'opr/') || str_contains($ua, 'opera') => 'Opera',
            str_contains($ua, 'samsungbrowser') => 'Samsung Internet',
            str_contains($ua, 'miuibrowser') => 'MIUI Browser',
            str_contains($ua, 'ucbrowser') => 'UC Browser',
            str_contains($ua, 'edg/') || str_contains($ua, 'edga') || str_contains($ua, 'edgios') => 'Edge',
            str_contains($ua, 'firefox') || str_contains($ua, 'fxios') => 'Firefox',
            str_contains($ua, 'crios') => 'Chrome',
            str_contains($ua, 'chrome') => 'Chrome',
            str_contains($ua, 'safari') => 'Safari',
            default => 'Other',
        };
    }
}
