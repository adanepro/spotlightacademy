<?php

namespace Database\Factories;

use App\Models\Lecture;
use App\Models\LectureMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LectureMaterial>
 */
class LectureMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    protected $model = LectureMaterial::class;

    public function definition(): array
    {
        $lecture = Lecture::first()->id;
        return [
                'lecture_id' => $lecture,
                'title' => $this->faker->sentence(),
                'order' => $this->faker->numberBetween(1,5),
        ];
    }
}
