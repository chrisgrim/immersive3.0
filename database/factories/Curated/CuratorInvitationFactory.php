<?php

namespace Database\Factories\Curated;

use App\Models\Curated\Community;
use App\Models\Curated\CuratorInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CuratorInvitation>
 */
class CuratorInvitationFactory extends Factory
{
    protected $model = CuratorInvitation::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
