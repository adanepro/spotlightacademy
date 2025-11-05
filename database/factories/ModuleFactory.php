<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Module>
 */
class ModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = Module::class;

    public function definition(): array
    {

        $course = Course::first()->id;
        return [
            'course_id' => $course,
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'order' => $this->faker->numberBetween(1,5),
        ];
    }
}
