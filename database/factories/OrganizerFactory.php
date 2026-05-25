<?php

namespace Database\Factories;

use App\Models\Organizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organizer>
 */
class OrganizerFactory extends Factory
{
    protected $model = Organizer::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'description' => $this->faker->sentence(),
            'status' => 'p',
            'type' => 'o',
        ];
    }

    /**
     * Match production: OrganizerController::store() always attaches the
     * creator to the organizer_user pivot with role 'owner'. Without this
     * the owner can't pass the host()/manage()/duplicate() policies, which
     * all check pivot membership.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Organizer $organizer) {
            $organizer->users()->syncWithoutDetaching([
                $organizer->user_id => ['role' => 'owner'],
            ]);
        });
    }
}
