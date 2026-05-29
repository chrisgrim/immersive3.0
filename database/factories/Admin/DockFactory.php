<?php

namespace Database\Factories\Admin;

use App\Models\Admin\Dock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dock>
 */
class DockFactory extends Factory
{
    protected $model = Dock::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            // type char: f=featured, t, i, h, s, p (see Dock domain).
            'type' => $this->faker->randomElement(['f', 't', 'i', 'h', 's', 'p']),
            'location' => 'home',
            'order' => 0,
        ];
    }
}
