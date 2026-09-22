<?php

namespace Database\Factories;

use App\Models\QrBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrBatch>
 */
class QrBatchFactory extends Factory
{
    protected $model = QrBatch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'label_prefix' => 'Standee',
            'quantity' => 10,
        ];
    }
}
