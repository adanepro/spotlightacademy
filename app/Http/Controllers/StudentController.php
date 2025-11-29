<?php

namespace App\Http\Controllers;

use App\Imports\StudentsImport;
use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $student = User::whereHas('roles', function ($query) {
                $query->where('name', 'Student');
            })
                ->with('student')
                ->latest()
                ->paginate(10);

            $formattedStudent = $student->getCollection()->map(function ($user) {
                return [
                    'student_id' => $user->student->id ?? null,
                    'user_id' => $user->id,
                    'institution_id' => $user->student->institution_id ?? null,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'address' => $user->student->address ?? null,
                    'age' => $user->student->age ?? null,
                    'gender' => $user->student->gender ?? null,
                    'status' => $user->status,
                    'type' => $user->type,
                ];
            });

            $paginatedStudent = $student->toArray();
            $paginatedStudent['data'] = $formattedStudent->values()->all();

            return response()->json([
                'status' => 'success',
                'message' => 'Students fetched successfully',
                'data' => $paginatedStudent,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $validated = $request->validate([
                'full_name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'phone_number' => 'required|string|unique:users,phone_number|starts_with:251|digits:12',
                'password' => 'required|string|min:8',
                'institution_id' => 'required|exists:institutions,id',
                'address' => 'nullable|string',
                'age' => 'nullable|integer|min:15|max:80',
                'gender' => 'nullable|string|in:male,female',
            ]);

            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'username' => User::generateUniqueUsername($validated['full_name']),
                'password' => Hash::make($validated['password']),
                'status' => 1,
                'type' => 'student',
            ]);

            $user->assignRole('Student');

            $student = Student::create([
                'user_id' => $user->id,
                'institution_id' => $validated['institution_id'],
                'address' => $validated['address'],
                'age' => $validated['age'],
                'gender' => $validated['gender'],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Student created successfully',
                'data' => [
                    'student_id' => $student->id,
                    'user_id' => $user->id,
                    'institution_id' => $student->institution_id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'address' => $student->address,
                    'age' => $student->age,
                    'gender' => $student->gender,
                    'status' => $user->status,
                    'type' => $user->type,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Student fetched successfully',
            'data' => [
                'student_id' => $student->id,
                'user_id' => $student->user->id,
                'institution_id' => $student->institution_id,
                'full_name' => $student->user->full_name,
                'email' => $student->user->email,
                'phone_number' => $student->user->phone_number,
                'username' => $student->user->username,
                'address' => $student->address,
                'age' => $student->age,
                'gender' => $student->gender,
                'status' => $student->user->status,
                'type' => $student->user->type,
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Student $student)
    {
        try {
            DB::beginTransaction();
            $request['status'] = filter_var($request['status'], FILTER_VALIDATE_BOOLEAN);
            $validated = $request->validate([
                'full_name' => 'sometimes|nullable|string',
                'email' => [
                    'sometimes',
                    'nullable',
                    'email',
                    Rule::unique('users', 'email')->ignore($student->user->id, 'id'),
                ],
                'phone_number' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'starts_with:251',
                    'digits:12',
                    Rule::unique('users', 'phone_number')->ignore($student->user->id, 'id'),
                ],
                'password' => 'sometimes|nullable|string|min:8',
                'institution_id' => 'sometimes|nullable|required|exists:institutions,id',
                'address' => 'sometimes|nullable|string',
                'age' => 'sometimes|nullable|integer|min:15|max:80',
                'gender' => 'sometimes|nullable|string|in:male,female',
                'status' => 'sometimes|nullable|boolean',
            ]);

            $student->user->update([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
                'type' => 'student',
            ]);

            $student->update([
                'institution_id' => $validated['institution_id'],
                'address' => $validated['address'],
                'age' => $validated['age'],
                'gender' => $validated['gender'],
            ]);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Student updated successfully',
                'data' => [
                    'student_id' => $student->id,
                    'user_id' => $student->user->id,
                    'institution_id' => $student->institution_id,
                    'full_name' => $student->user->full_name,
                    'email' => $student->user->email,
                    'phone_number' => $student->user->phone_number,
                    'username' => $student->user->username,
                    'address' => $student->address,
                    'age' => $student->age,
                    'gender' => $student->gender,
                    'status' => $student->user->status,
                    'type' => $student->user->type,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student)
    {
        try {
            DB::beginTransaction();
            $student->user->delete();
            $student->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Student deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function bulkImport(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,xlsx,xls|max:2048',
            'institution_id' => 'required|exists:institutions,id',
        ]);

        try {
            DB::beginTransaction();

            $import = new StudentsImport($request->institution_id);
            Excel::import($import, $request->file('file'));

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Students imported successfully',
                'summary' => [
                    'imported_count' => count($import->imported),
                    'skipped_count' => count($import->skipped),
                    'failed_count' => count($import->failed),
                ],
                'skipped_students' => $import->skipped,
                'failed_rows' => $import->failed,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getStudentByInstitution(Request $request, Institution $institution)
    {
        $students = $institution->students()->latest()->paginate(10);

        $formattedStudents = $students->getCollection()->map(function ($student) {
            return [
                'student_id' => $student->id,
                'user_id' => $student->user->id,
                'institution_id' => $student->institution_id,
                'full_name' => $student->user->full_name,
                'email' => $student->user->email,
                'phone_number' => $student->user->phone_number,
                'username' => $student->user->username,
                'address' => $student->address,
                'age' => $student->age,
                'gender' => $student->gender,
                'status' => $student->user->status,
                'type' => $student->user->type,
            ];
        });

        $paginatedStudents = $students->toArray();
        $paginatedStudents['data'] = $formattedStudents->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Students fetched successfully',
            'data' => $paginatedStudents,
        ], 200);
    }
}
