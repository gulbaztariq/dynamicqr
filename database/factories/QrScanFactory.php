<?php

namespace Database\Factories;

use App\Models\QrScan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrScan>
 */
class QrScanFactory extends Factory
{
    protected $model = QrScan::class;

    public function definition(): array
    {
        return [
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'country_code' => 'PK',
            'country_name' => 'Pakistan',
            'device_type' => 'mobile',
            'os' => 'Android',
            'browser' => 'Chrome',
            'is_bot' => false,
            'is_unique' => true,
            'scanned_at' => now(),
        ];
    }

    public function bot(): static
    {
        return $this->state(fn () => ['is_bot' => true, 'device_type' => 'bot', 'is_unique' => false]);
    }
}
