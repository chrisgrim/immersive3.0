<?php

namespace Database\Factories\Curated;

use App\Models\Curated\Community;
use App\Models\Curated\Post;
use App\Models\Curated\Shelf;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $name = $this->faker->sentence(3);

        return [
            'community_id' => Community::factory(),
            'shelf_id' => Shelf::factory(),
            'user_id' => User::factory(),
            'name' => $name,
            // The posts.slug column is unique; community_id makes it distinct per community.
            'slug' => fn (array $attributes) => Str::slug($name).'-'.$attributes['community_id'],
            'blurb' => $this->faker->sentence(),
            'status' => 'p',
            'is_hidden' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'p']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'd']);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_hidden' => true]);
    }
}
