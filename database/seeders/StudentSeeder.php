<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\Student;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $institutions = Institution::all();

        foreach ($institutions as $institution) {
            Student::factory()->count(10)->create([
                'institution_id' => $institution->id,
            ]);
        }
    }
}
