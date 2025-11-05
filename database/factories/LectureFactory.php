<?php

namespace Database\Factories;

use App\Models\Lecture;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lecture>
 */
class LectureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Lecture::class;

    public function definition(): array
    {
        $module = Module::first()->id;
        return [
            'module_id' => $module,
            'title' => $this->faker->sentence(),
            'order' => $this->faker->numberBetween(1,5),
        ];
    }
}
