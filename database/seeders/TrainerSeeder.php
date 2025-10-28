<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Institution;
use App\Models\Trainer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TrainerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure institutions and courses exist
        if (Institution::count() === 0) {
            Institution::factory()->count(2)->create();
        }

        if (Course::count() === 0) {
            Course::factory()->count(5)->create();
        }

        // Create 5 trainers
        $trainers = Trainer::factory()->count(5)->create();

        // Optionally attach each trainer to random courses (many-to-many)
        $trainers->each(function ($trainer) {
            $trainer->courses()->sync(
                Course::inRandomOrder()->take(rand(1, 2))->pluck('id')->toArray()
            );
        });
    }
}
