<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Exam::class;
    public function definition(): array
    {
        $course = Course::first()->id;
        return [
            'course_id' => $course,
            'title' => $this->faker->sentence(),
            'questions' => $this->faker->paragraph(),
            'start_date' => $this->faker->dateTime(),
            'end_date' => $this->faker->dateTime(),
            'status' => 'upcoming',
            'duration_minutes' => $this->faker->numberBetween(1, 120),
            'created_by' => Trainer::first()->id,
        ];
    }
}
