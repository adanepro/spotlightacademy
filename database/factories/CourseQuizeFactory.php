<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseQuize;
use App\Models\Module;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CourseQuizeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = CourseQuize::class;

    public function definition(): array
    {
        $course = Course::first();
        $module = $course->modules()->first()->id;
        $trainer = Trainer::first()->id;
        $question = [
            'question' => $this->faker->sentence(),
            'options' => [
                'A' => $this->faker->sentence(),
                'B' => $this->faker->sentence(),
                'C' => $this->faker->sentence(),
                'D' => $this->faker->sentence(),
            ],
            'answer' => $this->faker->randomElement(['A', 'B', 'C', 'D']),
        ];
        return [
            'module_id' => $module,
            'questions' => [$question],
            'created_by' => $trainer,
        ];
    }
}
