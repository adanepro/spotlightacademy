<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = [
            'full_name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'phone_number' => '251910000000',
            'password' => bcrypt('password'),
            'status' => true,
            'type' => 'admin',
        ];

        $admin = User::create($admin);
        $admin->assignRole('Admin');

        // $experts = [
        //     [
        //         'full_name' => 'Expert 1',
        //         'username' => 'expert',
        //         'email' => 'expert@gmail.com',
        //         'phone_number' => '251900000001',
        //         'password' => bcrypt('password'),
        //         'status' => true,
        //     ],
        //     [
        //         'full_name' => 'Expert 2',
        //         'username' => 'expert2',
        //         'email' => 'expert2@gmail.com',
        //         'phone_number' => '251900000002',
        //         'password' => bcrypt('password'),
        //         'status' => true,
        //     ],

        //     [
        //         'full_name' => 'Expert 3',
        //         'username' => 'expert3',
        //         'email' => 'expert3@gmail.com',
        //         'phone_number' => '251900000003',
        //         'password' => bcrypt('password'),
        //     ],
        // ];
        // foreach ($experts as $expert) {
        //     $expert = User::create($expert);
        //     $expert->assignRole('Expert');
        // }

        // $trainers = [
        //     [
        //         'full_name' => 'Trainer 1',
        //         'username' => 'trainer',
        //         'email' => 'user@gmail.com',
        //         'phone_number' => '251900000004',
        //         'password' => bcrypt('password'),
        //         'status' => true,
        //     ],

        //     [
        //         'full_name' => 'Trainer 2',
        //         'username' => 'trainer2',
        //         'email' => 'user2@gmail.com',
        //         'phone_number' => '251900000005',
        //         'password' => bcrypt('password'),
        //         'status' => true,
        //     ],

        //     [
        //         'full_name' => 'Trainer 3',
        //         'username' => 'trainer3',
        //         'email' => 'user3@gmail.com',
        //         'phone_number' => '251900000006',
        //         'password' => bcrypt('password'),
        //         'status' => true,
        //     ],
        // ];

        // foreach ($trainers as $trainer) {
        //     $trainer = User::create($trainer);
        //     $trainer->assignRole('Trainer');
        // }

        // $students = [
        //     [
        //         'full_name' => 'Student 1',
        //         'username' => 'student',
        //         'email' => 'student@gmail.com',
        //         'phone_number' => '251900000007',
        //         'password' => bcrypt('password'),
        //         'status' => true,
        //     ],

        //     [
        //         'full_name' => 'Student 2',
        //         'username' => 'student2',
        //         'email' => 'student2@gmail.com',
        //         'phone_number' => '251900000008',
        //         'password' => bcrypt('password'),
        //         'status' => true,
        //     ],

        //     [
        //         'full_name' => 'Student 3',
        //         'username' => 'student3',
        //         'email' => 'student3@gmail.com',
        //         'phone_number' => '251900000009',
        //         'password' => bcrypt('password'),
        //         'status' => true,
        //     ],
        // ];

        // foreach ($students as $student) {
        //     $student = User::create($student);
        //     $student->assignRole('Student');
        // }
    }
}
