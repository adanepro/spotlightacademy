<?php

namespace Database\Seeders;

use App\Models\CourseQuize;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CourseQuize::factory()->count(5)->create();
    }
}
