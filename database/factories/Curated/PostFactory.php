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
            // The shelf must live in the SAME community as the post. A bare Shelf::factory()
            // mints its own community, leaving the post's shelf in a different community than
            // the post itself — an impossible state in the app. Inherit the post's community.
            'shelf_id' => fn (array $attributes) => Shelf::factory()->create([
                'community_id' => $attributes['community_id'],
            ])->id,
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
