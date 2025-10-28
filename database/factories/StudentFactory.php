<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Student;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    protected static $studentCounter = 16;

    public function definition(): array
    {

        $institution = Institution::inRandomOrder()->first();

        // Create linked user
        $user = User::create([
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => '2519000000' . str_pad(self::$studentCounter++, 2, '0', STR_PAD_LEFT),
            'username' => User::generateUniqueUsername($this->faker->userName()),
            'password' => Hash::make('password'),
            'status' => true,
            'type' => 'student',
        ]);

        $user->assignRole('Student');

        return [
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'address' => $this->faker->address(),
            'age' => $this->faker->numberBetween(18, 35),
            'gender' => $this->faker->randomElement(['male', 'female']),
        ];
    }
}
