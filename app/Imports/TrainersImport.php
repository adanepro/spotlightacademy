<?php

namespace App\Imports;

use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TrainersImport implements ToCollection, WithHeadingRow
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
    /**
     * @param Collection $collection
     */
    
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $data = [
                'full_name'    => trim($row['full_name'] ?? ''),
                'email'        => trim($row['email'] ?? ''),
                'phone_number' => trim($row['phone_number'] ?? ''),
                'password'     => $row['password'] ?? 'password123',
                'qualification' => $row['qualification'] ?? null,
                'social_links' => $row['social_links'] ?? null,
                'expertise' => $row['expertise'] ?? null,
                'certifications' => $row['certifications'] ?? null,
                'bio' => $row['bio'] ?? null,
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
                'type'         => 'trainer',
            ]);

            $user->assignRole('Trainer');

            // Create Trainer
            Trainer::create([
                'user_id' => $user->id,
                'institution_id' => $this->institution_id,
                'qualification' => $data['qualification'],
                'social_links' => $data['social_links'],
                'expertise' => $data['expertise'],
                'certifications' => $data['certifications'],
                'bio' => $data['bio'],
                'status' => $data['status'],
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
