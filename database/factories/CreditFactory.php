<?php

namespace Database\Factories;

use App\Enums\CreditDepartment;
use App\Models\Artist;
use App\Models\Credit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Give it the row's owner: Credit::factory()->for($event, 'creditable').
 *
 * @extends Factory<Credit>
 */
class CreditFactory extends Factory
{
    protected $model = Credit::class;

    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'department' => CreditDepartment::Cast->value,
            'role' => 'Performer',
        ];
    }

    public function writing(): static
    {
        return $this->state(fn () => ['department' => CreditDepartment::Writing->value, 'role' => 'Writer']);
    }
}
