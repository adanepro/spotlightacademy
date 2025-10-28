<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Trainer;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class TrainerFactory extends Factory
{
    protected $model = Trainer::class;

    protected static $phoneNumberCounter = 11;

     /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    public function definition(): array
    {
        $number = str_pad(self::$phoneNumberCounter, 3, '0', STR_PAD_LEFT);
        $phoneNumber = '251900000' . $number;
        self::$phoneNumberCounter++;

        $institution = Institution::inRandomOrder()->first();

        $user = User::create([
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $phoneNumber,
            'username' => User::generateUniqueUsername($this->faker->userName()),
            'password' => Hash::make('password'),
            'status' => true,
            'type' => 'trainer',
        ]);

        $user->assignRole('Trainer');

        return [
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'qualification' => $this->faker->word(),
            'social_links' => ['facebook' => $this->faker->url()],
            'expertise' => [$this->faker->word(), $this->faker->word()],
            'certifications' => [$this->faker->word()],
            'bio' => $this->faker->paragraph(),
            'status' => true,
        ];
    }
}
