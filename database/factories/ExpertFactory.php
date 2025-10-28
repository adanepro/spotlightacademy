<?php

namespace Database\Factories;

use App\Models\Expert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expert>
 */
class ExpertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = Expert::class;

    protected static $phoneNumberCounter = 1;
    public function definition(): array
    {
        $number = str_pad(self::$phoneNumberCounter, 3, '0', STR_PAD_LEFT);
        $phoneNumber = '251900000' . $number;
        self::$phoneNumberCounter++;

        // Create related user
        $user = User::create([
            'full_name' => $this->faker->name(),
            'email' => 'expert' . $number . '@example.com',
            'phone_number' => $phoneNumber,
            'username' => User::generateUniqueUsername('expert' . $number),
            'password' => Hash::make('password'),
            'status' => true,
            'type' => 'expert',
        ]);

        $user->assignRole('Expert');

        return [
            'user_id' => $user->id,
            'qualification' => $this->faker->randomElement(['BSc', 'MSc', 'PhD']),
            'social_links' => [
                'linkedin' => 'https://linkedin.com/in/expert' . $number,
                'twitter' => 'https://twitter.com/expert' . $number,
            ],
            'expertise' => ['Laravel', 'PHP', 'MySQL'],
            'certifications' => ['Certified Developer'],
            'bio' => $this->faker->paragraph(),
            'status' => true,
        ];
    }
}
