<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Institution>
 */
class InstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */


    protected $model = Institution::class;

    protected static $phoneNumberCounter = 1;

    public function definition(): array
    {
        $number = str_pad(self::$phoneNumberCounter, 3, '0', STR_PAD_LEFT);
        $phoneNumber = '251900000' . $number;
        self::$phoneNumberCounter++;

        return [
            'name' => 'Institution ' . $number,
            'email' => 'institution' . $number . '@example.com',
            'phone_number' => $phoneNumber,
            'address' => $this->faker->address(),
            'region' => $this->faker->state(),
            'city' => $this->faker->city(),
            'description' => $this->faker->paragraph(),
            'status' => true,
        ];
    }
}
