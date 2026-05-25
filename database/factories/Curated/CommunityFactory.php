<?php

namespace Database\Factories\Curated;

use App\Models\Curated\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    protected $model = Community::class;

    public function definition(): array
    {
        $name = $this->faker->company().' Community';
        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($name).'-'.Str::random(6),
            'name' => $name,
            'blurb' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => 'p',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'p']);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'r']);
    }
}
