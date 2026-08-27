<?php

namespace Database\Factories;

use App\Domain\Labels\Models\Label;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Label>
 */
class LabelFactory extends Factory
{
    protected $model = Label::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return ['user_id' => User::factory(), 'name' => $name, 'normalized_name' => Str::lower($name), 'color' => fake()->optional()->hexColor()];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => ['user_id' => $user->id]);
    }
}
