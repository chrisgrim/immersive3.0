<?php

namespace Database\Factories\Components;

use App\Models\Components\Favorite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Polymorphic target; set via ->for($model, 'favorited') or state.
            'favorited_id' => null,
            'favorited_type' => null,
        ];
    }
}
