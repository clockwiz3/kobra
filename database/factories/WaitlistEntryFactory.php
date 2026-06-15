<?php

namespace Database\Factories;

use App\Enums\WaitlistStatus;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->firstName(),
            'status' => WaitlistStatus::Pending,
            'locale' => 'ru',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => WaitlistStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmation_token' => null,
        ]);
    }
}
