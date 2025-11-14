<?php

namespace App\Http\Controllers;

use App\Imports\StudentsImport;
use App\Imports\TrainersImport;
use App\Models\Institution;
use App\Models\Student;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class InstitutionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string',
        ]);
        try {
            $institutions = Institution::when($request->search, function ($query, $search) {
                return $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('city', 'like', "%$search%")
                    ->orWhere('region', 'like', "%$search%");
            })
                ->with(['trainers', 'students'])
                ->latest()
                ->paginate(10);
            $formattedInstitutions = $institutions->getCollection()->map(function ($institution) {
                return [
                    'institution_id' => $institution->id,
                    'name' => $institution->name,
                    'address' => $institution->address,
                    'phone_number' => $institution->phone_number,
                    'email' => $institution->email,
                    'region' => $institution->region,
                    'city' => $institution->city,
                    'description' => $institution->description,
                    'status' => $institution->status,
                    'logo' => $institution->logo,
                    'trainers_count' => $institution->trainers->count(),
                    'students_count' => $institution->students->count(),
                ];
            });

            // Replace original collection with formatted one
            $paginatedInstitutions = $institutions->toArray();
            $paginatedInstitutions['data'] = $formattedInstitutions->values()->all();

            return response()->json([
                'status' => 'success',
                'message' => 'Institutions fetched successfully',
                'data' => $paginatedInstitutions,
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

        $request['status'] = filter_var($request['status'], FILTER_VALIDATE_BOOLEAN);
        $validated = $request->validate([
            'name' => 'required|string',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'email' => 'nullable|email',
            'region' => 'nullable|string',
            'city' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
            'students_file' => 'nullable|file|mimes:csv,xlsx,xls|max:2048',
            'trainers_file' => 'nullable|file|mimes:csv,xlsx,xls|max:2048',
        ]);
        try {
            DB::beginTransaction();

            $institution = Institution::create($validated);

            if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
                $institution->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            if ($request->hasFile('students_file') && $request->file('students_file')->isValid()) {
                $import = new StudentsImport($institution->id);
                Excel::import($import, $request->file('students_file'));
            }

            if ($request->hasFile('trainers_file') && $request->file('trainers_file')->isValid()) {
                $import = new TrainersImport($institution->id);
                Excel::import($import, $request->file('trainers_file'));
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Institution created successfully',
                'data' => [
                    'institution_id' => $institution->id,
                    'name' => $institution->name,
                    'address' => $institution->address,
                    'phone_number' => $institution->phone_number,
                    'email' => $institution->email,
                    'region' => $institution->region,
                    'city' => $institution->city,
                    'description' => $institution->description,
                    'status' => $institution->status,
                    'logo' => $institution->logo,
                ],
                'summery' => [
                    'imported_count' => count($import->imported),
                    'skipped_count' => count($import->skipped),
                    'failed_count' => count($import->failed),
                    'skipped_students' => $import->skipped,
                    'failed_rows' => $import->failed,
                ],
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
     * Import students in bulk.
     */

    public function bulkImportStudent(Request $request, Institution $institution)
    {
        $request->validate([
            'file' => 'required|mimes:csv,xlsx,xls|max:2048',
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

    /**
     * Add Student
     */

    public function addStudent(Request $request, Institution $institution)
    {
        try {
            DB::beginTransaction();
            $request['status'] = filter_var($request['status'], FILTER_VALIDATE_BOOLEAN);
            $validated = $request->validate([
                'full_name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'phone_number' => 'required|string|unique:users,phone_number',
                'password' => 'nullable|string|min:8',
                'address' => 'nullable|string',
                'age' => 'nullable|integer|min:15|max:80',
                'gender' => 'nullable|string|in:male,female',
                'status' => 'required|boolean',
            ]);

            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'username' => User::generateUniqueUsername($validated['full_name']),
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
                'type' => 'student',
            ]);

            $user->assignRole('Student');

            $student = Student::create([
                'user_id' => $user->id,
                'institution_id' => $institution->id,
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
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'address' => $student->address,
                    'age' => $student->age,
                    'gender' => $student->gender,
                    'status' => $user->status,
                    'type' => $user->type,
                ],

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
     * Get Students
     */
    public function getStudents(Request $request, Institution $institution)
    {
        try {
            $students = User::whereHas('roles', function ($query) {
                $query->where('name', 'Student');
            })
                ->whereHas('student', function ($query) use ($institution) {
                    $query->where('institution_id', $institution->id);
                })
                ->with('student')
                ->latest()
                ->paginate(20);

            $formattedStudents = $students->getCollection()->map(function ($user) {
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

            $paginatedStudents = $students->toArray();
            $paginatedStudents['data'] = $formattedStudents->values()->all();

            return response()->json([
                'status' => 'success',
                'message' => 'Students fetched successfully',
                'data' => $paginatedStudents,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /*
    * Add Trainer
    */

    public function addTrainer(Request $request, Institution $institution)
    {
        try {
            DB::beginTransaction();
            $request['status'] = filter_var($request['status'], FILTER_VALIDATE_BOOLEAN);
            $validated = $request->validate([
                'full_name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'phone_number' => 'required|string|unique:users,phone_number',
                'password' => 'required|string|min:8',
                'qualification' => 'nullable|string',
                'social_links' => 'nullable|array',
                'social_links.*' => 'nullable|string',
                'expertise' => 'nullable|array',
                'expertise.*' => 'nullable|string',
                'certifications' => 'nullable|array',
                'certifications.*' => 'nullable|string',
                'bio' => 'nullable|string',
                'status' => 'required|boolean',
            ]);

            $user = User::create([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'username' => User::generateUniqueUsername($validated['full_name']),
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
                'type' => 'trainer',
            ]);

            $user->assignRole('Trainer');

            $trainer = Trainer::create([
                'user_id' => $user->id,
                'institution_id' => $institution->id,
                'qualification' => $validated['qualification'],
                'social_links' => $validated['social_links'],
                'expertise' => $validated['expertise'],
                'certifications' => $validated['certifications'],
                'bio' => $validated['bio'],
                'status' => $validated['status'],
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainer created successfully',
                'data' => [
                    'trainer_id' => $trainer->id,
                    'user_id' => $user->id,
                    'full_name' => $trainer->user->full_name,
                    'email' => $trainer->user->email,
                    'phone_number' => $trainer->user->phone_number,
                    'username' => $trainer->user->username,
                    'qualification' => $trainer->qualification,
                    'social_links' => $trainer->social_links,
                    'expertise' => $trainer->expertise,
                    'certifications' => $trainer->certifications,
                    'bio' => $trainer->bio,
                    'type' => $trainer->user->type,
                ],
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
     * Get Trainers
     */

    public function getTrainers(Request $request, Institution $institution)
    {
        try {
            $trainers = User::whereHas('roles', function ($query) {
                $query->where('name', 'Trainer');
            })
                ->whereHas('trainer', function ($query) use ($institution) {
                    $query->where('institution_id', $institution->id);
                })
                ->with('trainer', 'trainer.courses')
                ->latest()
                ->paginate(10);

            $formattedTrainers = $trainers->getCollection()->map(function ($user) {
                return [
                    'trainer_id' => $user->trainer->id ?? null,
                    'user_id' => $user->id,
                    'institution_id' => $user->trainer->institution_id ?? null,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'qualification' => $user->trainer->qualification ?? null,
                    'social_links' => $user->trainer->social_links ?? null,
                    'expertise' => $user->trainer->expertise ?? null,
                    'certifications' => $user->trainer->certifications ?? null,
                    'bio' => $user->trainer->bio ?? null,
                    'type' => $user->type ?? null,
                    'courses' => $user->trainer->courses->map(function ($course) {
                        return [
                            'course_id' => $course->id,
                            'course_name' => $course->name,
                        ];
                    }),
                ];
            });

            $paginatedTrainers = $trainers->toArray();
            $paginatedTrainers['data'] = $formattedTrainers->values()->all();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainers fetched successfully',
                'data' => $paginatedTrainers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Import Trainers in bulk.
     */
    public function bulkImportTrainer(Request $request, Institution $institution)
    {
        $request->validate([
            'file' => 'required|mimes:csv,xlsx,xls|max:2048',
        ]);

        try {
            DB::beginTransaction();
            $import = new TrainersImport($request->institution_id);
            Excel::import($import, $request->file('file'));

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainers imported successfully',
                'summary' => [
                    'imported_count' => count($import->imported),
                    'skipped_count' => count($import->skipped),
                    'failed_count' => count($import->failed),
                ],
                'skipped_trainers' => $import->skipped,
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

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Institution $institution)
    {
        try {

            $trainerPerPage = $request->get('trainer_per_page', 5);
            $studentPerPage = $request->get('student_per_page', 5);

            $trainerSearch = $request->get('trainer_search', null);
            $studentSearch = $request->get('student_search', null);


            $trainersQuery = $institution->trainers()
                ->with('user');
            if ($trainerSearch) {
                $trainersQuery->where(function ($query) use ($trainerSearch) {
                    $query->whereHas('user', function ($userQuery) use ($trainerSearch) {
                        $userQuery->where('full_name', 'like', "%{$trainerSearch}%")
                            ->orWhere('email', 'like', "%{$trainerSearch}%")
                            ->orWhere('phone_number', 'like', "%{$trainerSearch}%");
                    })
                        ->orWhere('qualification', 'like', "%{$trainerSearch}%")
                        ->orWhere('bio', 'like', "%{$trainerSearch}%");
                });
            }

            $trainers = $trainersQuery->paginate($trainerPerPage, ['*'], 'trainers_page');


            $studentsQuery = $institution->students()
                ->with('user');

            if ($studentSearch) {
                $studentsQuery->where(function ($query) use ($studentSearch) {
                    $query->whereHas('user', function ($userQuery) use ($studentSearch) {
                        $userQuery->where('full_name', 'like', "%{$studentSearch}%")
                            ->orWhere('username', 'like', "%{$studentSearch}%")
                            ->orWhere('email', 'like', "%{$studentSearch}%")
                            ->orWhere('phone_number', 'like', "%{$studentSearch}%");
                    })
                        ->orWhere('gender', 'like', "%{$studentSearch}%")
                        ->orWhere('age', 'like', "%{$studentSearch}%");
                });
            }

            $students = $studentsQuery->paginate($studentPerPage, ['*'], 'students_page');

            $institutionData = [
                'institution_id' => $institution->id,
                'name' => $institution->name,
                'address' => $institution->address,
                'phone_number' => $institution->phone_number,
                'email' => $institution->email,
                'region' => $institution->region,
                'city' => $institution->city,
                'description' => $institution->description,
                'status' => $institution->status,
                'logo' => $institution->getFirstMediaUrl('logo') ?? null,
            ];

            // Map trainers for formatted response
            $institutionData['trainers'] = $trainers->getCollection()->map(function ($trainer) {
                return [
                    'trainer_id' => $trainer->id,
                    'user_id' => $trainer->user->id ?? null,
                    'full_name' => $trainer->user->full_name ?? null,
                    'email' => $trainer->user->email ?? null,
                    'phone_number' => $trainer->user->phone_number ?? null,
                    'qualification' => $trainer->qualification ?? null,
                    'bio' => $trainer->bio ?? null,
                    'profile_image' => $trainer->user->getFirstMediaUrl('profile_image') ?? null,
                ];
            });

            // Map students for formatted response
            $institutionData['students'] = $students->getCollection()->map(function ($student) {
                return [
                    'student_id' => $student->id,
                    'user_id' => $student->user->id ?? null,
                    'full_name' => $student->user->full_name ?? null,
                    'username' => $student->user->username ?? null,
                    'gender' => $student->gender ?? null,
                    'age' => $student->age ?? null,
                    'email' => $student->user->email ?? null,
                    'phone_number' => $student->user->phone_number ?? null,
                    'profile_image' => $student->user->getFirstMediaUrl('profile_image') ?? null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Institution fetched successfully',
                'data' => [
                    'institution' => $institutionData,
                    'search_params' => [
                        'trainer_search' => $trainerSearch,
                        'student_search' => $studentSearch,
                    ],
                    'pagination' => [
                        'trainers' => [
                            'current_page' => $trainers->currentPage(),
                            'last_page' => $trainers->lastPage(),
                            'per_page' => $trainers->perPage(),
                            'total' => $trainers->total(),
                            'path' => $trainers->path(),
                            'next_page_url' => $trainers->nextPageUrl(),
                            'prev_page_url' => $trainers->previousPageUrl(),
                            'from' => $trainers->firstItem(),
                            'to' => $trainers->lastItem(),
                            'links' => $trainers->links(),
                            'first_page_url' => $trainers->url(1),
                            'last_page_url' => $trainers->url($trainers->lastPage()),
                            'has_more_pages' => $trainers->hasMorePages(),
                        ],
                        'students' => [
                            'current_page' => $students->currentPage(),
                            'last_page' => $students->lastPage(),
                            'per_page' => $students->perPage(),
                            'total' => $students->total(),
                            'path' => $students->path(),
                            'next_page_url' => $students->nextPageUrl(),
                            'prev_page_url' => $students->previousPageUrl(),
                            'from' => $students->firstItem(),
                            'to' => $students->lastItem(),
                            'links' => $students->links(),
                            'first_page_url' => $students->url(1),
                            'last_page_url' => $students->url($students->lastPage()),
                            'has_more_pages' => $students->hasMorePages(),
                        ],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Institution $institution)
    {
        try {
            DB::beginTransaction();
            $request['status'] = filter_var($request['status'], FILTER_VALIDATE_BOOLEAN);
            $validated = $request->validate([
                'name' => 'required|string',
                'address' => 'nullable|string',
                'phone_number' => 'nullable|string',
                'email' => 'nullable|email',
                'region' => 'nullable|string',
                'city' => 'nullable|string',
                'description' => 'nullable|string',
                'status' => 'required|boolean',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
            ]);

            $institution->update($validated);

            if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
                $institution->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Institution updated successfully',
                'data' => [
                    'institution_id' => $institution->id,
                    'name' => $institution->name,
                    'address' => $institution->address,
                    'phone_number' => $institution->phone_number,
                    'email' => $institution->email,
                    'region' => $institution->region,
                    'city' => $institution->city,
                    'description' => $institution->description,
                    'status' => $institution->status,
                    'logo' => $institution->logo,
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
    public function destroy(Institution $institution)
    {
        try {
            DB::beginTransaction();
            $institution->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Institution deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
