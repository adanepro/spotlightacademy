<?php

namespace App\Http\Controllers;

use App\Imports\TrainersImport;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class TrainerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $trainer = User::whereHas('roles', function ($query) {
                $query->where('name', 'Trainer');
            })
                ->with('trainer')
                ->latest()
                ->paginate(10);
            $formatedTrainers = $trainer->getCollection()->map(function ($user) {
                return [
                    'trainer_id' => $user->trainer->id ?? null,
                    'user_id' => $user->id,
                    'institution_id' => $user->trainer->institution_id ?? null,
                    'courses' => optional($user->trainer)->courses?->map(function ($course) {
                        return [
                            'id' => $course->id,
                            'name' => $course->name,
                            'status' => $course->status,
                        ];
                    }) ?? [],
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'qualification' => $user->trainer->qualification ?? null,
                    'social_links' => $user->trainer->social_links ?? [],
                    'expertise' => $user->trainer->expertise ?? [],
                    'certifications' => $user->trainer->certifications ?? [],
                    'bio' => $user->trainer->bio ?? null,
                    'type' => $user->type ?? null,
                    'course_is_assigned' => $user->trainer->courses->isNotEmpty() ?? false,
                    'institution_is_assigned' => !is_null($user->trainer->institution_id) ?? false,
                ];
            });

            $paginatedTrainers = $trainer->toArray();
            $paginatedTrainers['data'] = $formatedTrainers->values()->all();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainers fetched successfully',
                'data' => $paginatedTrainers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request['status'] = filter_var($request['status'], FILTER_VALIDATE_BOOLEAN);
        $validated = $request->validate([
            'full_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8',
            'institution_id' => 'required|exists:institutions,id',
            'qualification' => 'nullable|string',
            'social_links' => 'nullable|array',
            'social_links.*' => 'nullable|string',
            'expertise' => 'nullable|array',
            'expertise.*' => 'nullable|string',
            'certifications' => 'nullable|array',
            'certifications.*' => 'nullable|string',
            'bio' => 'nullable|string',
            'status' => 'required|boolean',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'nullable|exists:courses,id',
        ]);

        try {
            DB::beginTransaction();

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
                'institution_id' => $validated['institution_id'],
                'qualification' => $validated['qualification'],
                'social_links' => $validated['social_links'],
                'expertise' => $validated['expertise'],
                'certifications' => $validated['certifications'],
                'bio' => $validated['bio'],
                'status' => $validated['status'],
            ]);

            if (!empty($validated['course_ids'])) {
                $trainer->courses()->sync($validated['course_ids']);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainer created successfully',
                'data' => [
                    'trainer_id' => $trainer->id,
                    'user_id' => $user->id,
                    'institution_id' => $trainer->institution_id,
                    'full_name' => $trainer->user->full_name,
                    'email' => $trainer->user->email,
                    'phone_number' => $trainer->user->phone_number,
                    'username' => $trainer->user->username,
                    'courses' => $trainer->courses->map(function ($course) {
                        return [
                            'id' => $course->id,
                            'name' => $course->name,
                            'status' => $course->status,
                        ];
                    }),
                    'qualification' => $trainer->qualification,
                    'social_links' => $trainer->social_links ?? [],
                    'expertise' => $trainer->expertise ?? [],
                    'certifications' => $trainer->certifications ?? [],
                    'bio' => $trainer->bio,
                    'type' => $trainer->user->type ?? null,
                    'course_is_assigned' => $trainer->is_assigned,
                    'institution_is_assigned' => !is_null($trainer->institution_id),
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Trainer $trainer)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Trainer fetched successfully',
            'data' => [
                'trainer_id' => $trainer->id,
                'user_id' => $trainer->user->id,
                'institution_id' => $trainer->institution_id,
                'courses' => optional($trainer->courses)->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'name' => $course->name,
                        'status' => $course->status,
                    ];
                }) ?? [],
                'full_name' => $trainer->user->full_name,
                'email' => $trainer->user->email,
                'phone_number' => $trainer->user->phone_number,
                'username' => $trainer->user->username,
                'qualification' => $trainer->qualification,
                'social_links' => $trainer->social_links ?? [],
                'expertise' => $trainer->expertise ?? [],
                'certifications' => $trainer->certifications ?? [],
                'bio' => $trainer->bio,
                'type' => $trainer->user->type ?? null,
                'is_assigned' => $trainer->is_assigned,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Trainer $trainer)
    {
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
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'nullable|exists:courses,id',
        ]);
        try {
            DB::beginTransaction();
            $trainer->user->update([
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password' => Hash::make($validated['password']),
                'status' => $validated['status'],
                'type' => 'trainer',
            ]);

            $trainer->update([
                'qualification' => $validated['qualification'],
                'social_links' => $validated['social_links'],
                'expertise' => $validated['expertise'],
                'certifications' => $validated['certifications'],
                'bio' => $validated['bio'],
                'status' => $validated['status'],
            ]);

            if (!empty($validated['course_ids'])) {
                $trainer->courses()->sync($validated['course_ids']);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainer updated successfully',
                'data' => [
                    'trainer_id' => $trainer->id,
                    'user_id' => $trainer->user->id,
                    'institution_id' => $trainer->institution_id,
                    'courses' => optional($trainer->courses)->map(function ($course) {
                        return [
                            'id' => $course->id,
                            'name' => $course->name,
                            'status' => $course->status,
                        ];
                    }) ?? [],
                    'full_name' => $trainer->user->full_name,
                    'email' => $trainer->user->email,
                    'phone_number' => $trainer->user->phone_number,
                    'username' => $trainer->user->username,
                    'qualification' => $trainer->qualification,
                    'social_links' => $trainer->social_links ?? [],
                    'expertise' => $trainer->expertise ?? [],
                    'certifications' => $trainer->certifications ?? [],
                    'bio' => $trainer->bio,
                    'type' => $trainer->user->type ?? null,
                    'is_assigned' => $trainer->is_assigned,
                ],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Trainer $trainer)
    {
        try {
            DB::beginTransaction();
            $trainer->user->delete();
            $trainer->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainer deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTrainerByInstitution(Request $request, Institution $institution)
    {
        $trainers = $institution->trainers()->latest()->paginate(10);

        $formattedTrainers = $trainers->getCollection()->map(function ($trainer) {
            return [
                'trainer_id' => $trainer->id,
                'user_id' => $trainer->user->id,
                'institution_id' => $trainer->institution_id,
                'full_name' => $trainer->user->full_name,
                'email' => $trainer->user->email,
                'phone_number' => $trainer->user->phone_number,
                'username' => $trainer->user->username,
                'qualification' => $trainer->qualification,
                'social_links' => $trainer->social_links ?? [],
                'expertise' => $trainer->expertise ?? [],
                'certifications' => $trainer->certifications ?? [],
                'bio' => $trainer->bio,
                'type' => $trainer->user->type ?? null,
                'is_assigned' => $trainer->is_assigned,
            ];
        });

        $paginatedTrainers = $trainers->toArray();
        $paginatedTrainers['data'] = $formattedTrainers->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Trainers fetched successfully',
            'data' => $paginatedTrainers,
        ], 200);
    }

    public function getTrainersByCourse(Request $request, Course $course)
    {
        $trainers = $course->trainers()->latest()->paginate(10);

        $formattedTrainers = $trainers->getCollection()->map(function ($trainer) {
            return [
                'trainer_id' => $trainer->id,
                'user_id' => $trainer->user->id,
                'institution_id' => $trainer->institution_id,
                'full_name' => $trainer->user->full_name,
                'email' => $trainer->user->email,
                'phone_number' => $trainer->user->phone_number,
                'username' => $trainer->user->username,
                'qualification' => $trainer->qualification,
                'social_links' => $trainer->social_links ?? [],
                'expertise' => $trainer->expertise ?? [],
                'certifications' => $trainer->certifications ?? [],
                'bio' => $trainer->bio,
                'type' => $trainer->user->type ?? null,
                'is_assigned' => $trainer->is_assigned,
            ];
        });

        $paginatedTrainers = $trainers->toArray();
        $paginatedTrainers['data'] = $formattedTrainers->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Trainers fetched successfully',
            'data' => $paginatedTrainers,
        ], 200);
    }

    public function indexTrainersWithoutCourse()
    {
        try {
            $trainer = User::whereHas('roles', function ($query) {
                $query->where('name', 'Trainer');
            })->whereHas('trainer', function ($q) {
                $q->doesntHave('courses');
            })
                ->latest()
                ->paginate(10);
            $formatedTrainers = $trainer->getCollection()->map(function ($user) {
                return [
                    'trainer_id' => $user->trainer->id ?? null,
                    'user_id' => $user->id,
                    'institution_id' => $user->trainer->institution_id ?? null,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'is_assigned' => $user->trainer->is_assigned ?? false,
                ];
            });

            $paginatedTrainers = $trainer->toArray();
            $paginatedTrainers['data'] = $formatedTrainers->values()->all();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainers fetched successfully',
                'data' => $paginatedTrainers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function indexTrainerWithoutInstitution()
    {
        try {
            $trainers = User::whereHas('roles', function ($query) {
                $query->where('name', 'Trainer');
            })->whereHas('trainer', function ($q) {
                $q->whereNull('institution_id');
            })
                ->latest()
                ->paginate(10);

            $formattedTrainers = $trainers->getCollection()->map(function ($user) {
                return [
                    'trainer_id' => $user->trainer->id ?? null,
                    'user_id' => $user->id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'username' => $user->username,
                    'is_assigned' => $user->trainer->is_assigned ?? false,
                ];
            });

            $paginatedTrainers = $trainers->toArray();
            $paginatedTrainers['data'] = $formattedTrainers->values()->all();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainers fetched successfully',
                'data' => $paginatedTrainers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function bulkImportTrainer(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv',
            'institution_id' => 'required|exists:institutions,id',
        ]);

        try {
            DB::beginTransaction();

            $import = new TrainersImport($request->institution_id);
            Excel::import($import, $request->file('file'));

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Trainers imported successfully',
                'data' => [
                    'imported_count' => count($import->imported),
                    'skipped_count' => count($import->skipped),
                    'failed_count' => count($import->failed),
                ],
                'skipped_trainers' => $import->skipped,
                'failed_rows' => $import->failed,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function assignTrainerToInstitution(Request $request, Trainer $trainer, Institution $institution)
    {
        $request->validate([
            'trainer_id' => 'required|exists:trainers,id',
        ]);

        $trainer->update([
            'institution_id' => $institution->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Trainer assigned to institution successfully',
        ], 200);
    }

    public function assignTrainerToCourse(Request $request, Trainer $trainer, Course $course)
    {
        $trainer->courses()->syncWithoutDetaching($course->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Trainer assigned to course successfully',
        ], 200);
    }
}
