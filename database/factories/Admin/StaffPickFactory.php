<?php

namespace Database\Factories\Admin;

use App\Models\Admin\StaffPick;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffPick>
 *
 * NOTE: The staff_picks table does not have `admin_id`/`featured_until` columns.
 * Its actual schema is event_id, user_id, rank, start_date, end_date, comments.
 * This factory matches the live schema rather than the requested column names.
 */
class StaffPickFactory extends Factory
{
    protected $model = StaffPick::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 week', 'now');

        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'rank' => $this->faker->numberBetween(1, 5),
            'start_date' => $start->format('Y-m-d H:i:s'),
            'end_date' => $this->faker->dateTimeBetween($start, '+3 months')->format('Y-m-d H:i:s'),
            'comments' => $this->faker->sentence(),
        ];
    }
}
