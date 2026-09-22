<?php

namespace Database\Factories;

use App\Models\QrCode;
use App\Services\ShortCodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    protected $model = QrCode::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'code' => app(ShortCodeGenerator::class)->generate(),
            'label' => fake()->words(2, true),
            'target_url' => fake()->url(),
            'is_active' => true,
            'user_can_edit' => true,
            'scan_count' => 0,
            'unique_scan_count' => 0,
        ];
    }

    /** In the pool, not yet handed to a customer. */
    public function unassigned(): static
    {
        return $this->state(fn () => ['user_id' => null, 'assigned_at' => null]);
    }

    /** Printed but with no destination yet. */
    public function unconfigured(): static
    {
        return $this->state(fn () => ['target_url' => null]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Destination locked so only staff can change it. */
    public function locked(): static
    {
        return $this->state(fn () => ['user_can_edit' => false]);
    }
}
