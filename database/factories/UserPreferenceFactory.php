<?php

namespace Database\Factories;

use App\Domain\Identity\Models\UserPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPreference>
 */
class UserPreferenceFactory extends Factory
{
    protected $model = UserPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'timezone' => 'Africa/Casablanca',
            'week_start_day' => 'MONDAY',
            'theme' => 'SYSTEM',
            'density' => 'COMFORTABLE',
        ];
    }

    public function timezone(string $timezone): static
    {
        return $this->state(fn (): array => ['timezone' => $timezone]);
    }

    public function sundayStart(): static
    {
        return $this->state(fn (): array => ['week_start_day' => 'SUNDAY']);
    }

    public function light(): static
    {
        return $this->state(fn (): array => ['theme' => 'LIGHT']);
    }

    public function dark(): static
    {
        return $this->state(fn (): array => ['theme' => 'DARK']);
    }

    public function compact(): static
    {
        return $this->state(fn (): array => ['density' => 'COMPACT']);
    }
}
