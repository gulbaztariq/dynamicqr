<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\QrCode;
use App\Models\QrScan;
use App\Models\User;
use App\Services\Countries;
use App\Services\QrCodeService;
use App\Services\UserAgentParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A working demo of both fulfilment paths, with enough scan history that the
 * charts show real shapes rather than flat lines.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(QrCodeService::class);

        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::SuperAdmin,
                'company' => 'NFC Review Products',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $customers = collect([
            ['name' => 'Bilal Ahmed', 'email' => 'bilal@example.com', 'company' => 'Cafe Aroma', 'qty' => 10],
            ['name' => 'Ayesha Khan', 'email' => 'ayesha@example.com', 'company' => 'Glow Salon', 'qty' => 5],
            ['name' => 'Hassan Raza', 'email' => 'hassan@example.com', 'company' => 'Raza Motors', 'qty' => 3],
        ])->map(function (array $row) use ($superAdmin, $service) {
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'password' => Hash::make('password'),
                    'role' => UserRole::User,
                    'company' => $row['company'],
                    'phone' => '+92 3'.random_int(10, 99).' '.random_int(1000000, 9999999),
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'created_by' => $superAdmin->id,
                ],
            );

            // Path 1: somebody bought N standees — mint their codes onto them.
            if ($user->qrCodes()->doesntExist()) {
                $service->generateBatch([
                    'name' => $row['company'].' — initial order',
                    'quantity' => $row['qty'],
                    'label_prefix' => 'Standee',
                    'user_id' => $user->id,
                ], $superAdmin);
            }

            return $user;
        });

        // Path 2: a printed pool of stock waiting to be handed out.
        if (QrCode::unassigned()->doesntExist()) {
            $service->generateBatch([
                'name' => 'Warehouse stock — October print run',
                'quantity' => 100,
                'label_prefix' => 'Stock',
                'notes' => 'Pre-printed standees. Assign to customers as they are sold.',
            ], $superAdmin);
        }

        $this->giveDestinations($customers, $superAdmin, $service);
        $this->seedScanHistory();
    }

    private function giveDestinations($customers, User $actor, QrCodeService $service): void
    {
        $destinations = [
            'https://g.page/r/cafe-aroma-reviews/review',
            'https://www.instagram.com/cafearoma',
            'https://wa.me/923001234567',
            'https://cafearoma.example.com/menu',
            'https://g.page/r/glow-salon/review',
            'https://linktr.ee/glowsalon',
            'https://g.page/r/raza-motors/review',
        ];

        foreach ($customers as $customer) {
            // Leave a couple unconfigured so the "needs setup" prompts are visible.
            $codes = QrCode::ownedBy($customer)->orderBy('id')->get()->slice(0, -2);

            foreach ($codes as $index => $qrCode) {
                if (blank($qrCode->target_url)) {
                    $service->updateTargetUrl($qrCode, $destinations[$index % count($destinations)], $actor);
                }
            }
        }
    }

    /** Realistic-looking traffic: weekday-weighted, daytime-weighted, mobile-heavy. */
    private function seedScanHistory(): void
    {
        if (QrScan::exists()) {
            return;
        }

        $parser = new UserAgentParser;
        $qrCodes = QrCode::whereNotNull('user_id')->whereNotNull('target_url')->get();

        if ($qrCodes->isEmpty()) {
            return;
        }

        $agents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Instagram 302.0.0',
            'Mozilla/5.0 (Linux; Android 13; RMX3085) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
            'Mozilla/5.0 (iPad; CPU OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
        ];

        $countries = ['PK', 'PK', 'PK', 'PK', 'AE', 'AE', 'GB', 'US', 'SA', 'CA'];
        $referrers = [null, null, null, null, null, 'https://www.google.com/', 'https://www.instagram.com/'];

        $rows = [];
        $counters = [];

        foreach (range(89, 0) as $daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);

            // Traffic grows over time and dips at weekends.
            $base = (int) round(6 + (89 - $daysAgo) * 0.22);
            $volume = max(0, (int) round($base * ($date->isWeekend() ? 0.55 : 1.0) * random_int(60, 145) / 100));

            for ($i = 0; $i < $volume; $i++) {
                $qrCode = $qrCodes->random();
                $agent = $parser->parse($userAgent = $agents[array_rand($agents)]);

                // Daytime-weighted: most scans happen between 11:00 and 21:00.
                $hours = [9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 20, 21, 22];

                // Today's scans must not land in the future.
                if ($daysAgo === 0) {
                    $hours = array_values(array_filter($hours, fn (int $h) => $h <= now()->hour));

                    // Seeding early in the morning: put today's scans in the
                    // current hour rather than leaving today empty.
                    $hours = $hours ?: [now()->hour];
                }

                $hour = $hours[array_rand($hours)];

                $ipHash = hash('sha256', 'demo-visitor-'.random_int(1, 420));
                $key = $qrCode->id.':'.$ipHash;
                $isUnique = ! isset($counters[$key]);
                $counters[$key] = true;

                $referrer = $referrers[array_rand($referrers)];
                $scannedAt = $date->copy()->setTime($hour, random_int(0, 59), random_int(0, 59));

                // A scan can never have happened later than right now.
                if ($scannedAt->isFuture()) {
                    $scannedAt = now()->subMinutes(random_int(0, 45));
                }

                $rows[] = [
                    'qr_code_id' => $qrCode->id,
                    'user_id' => $qrCode->user_id,
                    'ip_hash' => $ipHash,
                    'country_code' => $country = $countries[array_rand($countries)],
                    'country_name' => Countries::name($country),
                    'city' => null,
                    'device_type' => $agent['device_type'],
                    'os' => $agent['os'],
                    'browser' => $agent['browser'],
                    'is_bot' => false,
                    'is_unique' => $isUnique,
                    'referrer_host' => $referrer ? parse_url($referrer, PHP_URL_HOST) : null,
                    'referrer_url' => $referrer,
                    'target_url' => $qrCode->target_url,
                    'user_agent' => $userAgent,
                    'scanned_at' => $scannedAt,
                    'created_at' => $scannedAt,
                    'updated_at' => $scannedAt,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            QrScan::insert($chunk);
        }

        $this->refreshCounters();
    }

    /** Keep the denormalised counters on qr_codes in step with the seeded rows. */
    private function refreshCounters(): void
    {
        foreach (QrCode::has('scans')->get() as $qrCode) {
            $qrCode->forceFill([
                'scan_count' => $qrCode->scans()->where('is_bot', false)->count(),
                'unique_scan_count' => $qrCode->scans()->where('is_bot', false)->where('is_unique', true)->count(),
                'last_scanned_at' => $qrCode->scans()->max('scanned_at'),
            ])->save();
        }
    }
}
