<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Expert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    protected static $courseCounter = 1;

    public function definition(): array
    {
        $expert = Expert::skip(self::$courseCounter - 1)->first();

        if (!$expert) {
            $expert = Expert::inRandomOrder()->first();
        }

        $number = str_pad(self::$courseCounter, 3, '0', STR_PAD_LEFT);
        self::$courseCounter++;

        return [
            'name' => 'Course ' . $number,
            'expert_id' => $expert->id,
            'description' => $this->faker->paragraph(),
            'status' => true,
        ];
    }
}
