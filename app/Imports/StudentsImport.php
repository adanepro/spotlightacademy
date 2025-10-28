<?php

namespace App\Imports;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StudentsImport implements ToCollection, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */

    protected $institution_id;
    public $imported = [];
    public $skipped = [];
    public $failed = [];

    public function __construct($institution_id)
    {
        $this->institution_id = $institution_id;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $data = [
                'full_name'    => trim($row['full_name'] ?? ''),
                'email'        => trim($row['email'] ?? ''),
                'phone_number' => trim($row['phone_number'] ?? ''),
                'password'     => $row['password'] ?? 'password123',
                'address'      => $row['address'] ?? null,
                'age'          => $row['age'] ?? null,
                'gender'       => $row['gender'] ?? null,
                'status'       => isset($row['status']) ? filter_var($row['status'], FILTER_VALIDATE_BOOLEAN) : true,
            ];

            // Validate minimal fields
            $validator = Validator::make($data, [
                'full_name'    => 'required|string',
                'email'        => 'required|email',
                'phone_number' => 'required|string',
                'password'     => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                $this->failed[] = [
                    'row' => $rowNumber,
                    'data' => $data,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            // Skip duplicates (email or phone)
            if (User::where('email', $data['email'])->orWhere('phone_number', $data['phone_number'])->exists()) {
                $this->skipped[] = [
                    'row' => $rowNumber,
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone_number' => $data['phone_number'],
                    'reason' => 'Duplicate email or phone number',
                ];
                continue;
            }

            // Create User
            $user = User::create([
                'full_name'    => $data['full_name'],
                'email'        => $data['email'],
                'phone_number' => $data['phone_number'],
                'username'     => User::generateUniqueUsername($data['full_name']),
                'password'     => Hash::make($data['password']),
                'status'       => $data['status'],
                'type'         => 'student',
            ]);

            $user->assignRole('Student');

            // Create Student
            Student::create([
                'user_id'        => $user->id,
                'institution_id' => $this->institution_id,
                'address'        => $data['address'],
                'age'            => $data['age'],
                'gender'         => $data['gender'],
            ]);

            $this->imported[] = [
                'row' => $rowNumber,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
            ];
        }
    }
}
