<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Project;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::first()->id,
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'start_date' => $this->faker->dateTime(),
            'end_date' => $this->faker->dateTime(),
            'status' => 'upcoming',
            'created_by' => Trainer::first()->id,
        ];
    }
}
