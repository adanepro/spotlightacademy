<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Expert;
use App\Models\Trainer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // search by course name(both capital or small letter)
        $courses = Course::when($request->search, function ($query, $search) {
            return $query->where('name', 'ilike', "%$search%")
                ->orWhere('description', 'ilike', "%$search%");
        })->with('expert', 'trainers')->latest()->paginate(20);

        $formattedCourses = $courses->getCollection()->map(function ($course) {
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'expert_id' => $course->expert_id,
                'expert_name' => $course->expert->user->full_name ?? null,
                'trainer_count' => optional($course->trainers)->count() ?? 0,
                'description' => $course->description,
                'status' => $course->status,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
                'expert_is_assigned' => $course->expert->is_assigned ?? false,
                'trainer_is_assigned' => $course->trainers->isNotEmpty(),
            ];
        });

        $paginatedCourses = $courses->toArray();
        $paginatedCourses['data'] = $formattedCourses->values()->all();
        return response()->json([
            'status' => 'success',
            'message' => 'Courses fetched successfully',
            'data' => $paginatedCourses,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'expert_id' => 'required|exists:experts,id',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'course_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
            'course_trailer' => 'nullable|file|mimes:mp4,avi,flv,wmv,webm',
        ]);

        try {

            DB::beginTransaction();
            $course = Course::create([
                'expert_id' => $validated['expert_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'status' => 'draft',
            ]);

            DB::commit();

            if ($request->hasFile('course_image') && $request->file('course_image')->isValid()) {
                $course->addMediaFromRequest('course_image')->toMediaCollection('course_image');
            }

            if ($request->hasFile('course_trailer') && $request->file('course_trailer')->isValid()) {
                $course->addMediaFromRequest('course_trailer')->toMediaCollection('course_trailer');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Course created successfully',
                'data' => [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'expert_id' => $course->expert_id,
                    'expert_name' => $course->expert->user->full_name ?? null,
                    'description' => $course->description,
                    'status' => $course->status,
                    'course_image' => $course->course_image,
                    'course_trailer' => $course->course_trailer,
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
    public function show(Course $course)
    {
        $module = optional($course->modules)->count() ?? 0;
        $lectures = $course->modules->sum(function ($module) {
            return optional($module->lectures)->count() ?? 0;
        });
        $materials = $course->modules->sum(function ($module) {
            return $module->lectures->sum(function ($lecture) {
                return optional($lecture->materials)->count() ?? 0;
            });
        });
        $quizzes = $course->modules->sum(function ($module) {
            return optional($module->quizzes)->count() ?? 0;
        });
        $exams = optional($course->exams)->count() ?? 0;
        $projects = optional($course->projects)->count() ?? 0;
        $course->load(['modules.lectures.materials']);
        $course->load(['modules.quizzes']);
        $course->load(['exams']);
        $course->load(['projects']);
        return response()->json([
            'status' => 'success',
            'message' => 'Course fetched successfully',
            'data' => [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'description' => $course->description,
                'status' => $course->status,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
                'expert_id' => $course->expert_id,
                'expert_name' => $course->expert->user->full_name ?? null,
                'trainers' => $course->trainers->map(function ($trainer) {
                    return [
                        'trainer_id' => $trainer->id,
                        'user_id' => $trainer->user->id,
                        'full_name' => $trainer->user->full_name,
                        'email' => $trainer->user->email,
                        'phone_number' => $trainer->user->phone_number,
                        'username' => $trainer->user->username,
                    ];
                }),
                'modules_count' => $module,
                'lectures_count' => $lectures,
                'materials_count' => $materials,
                'quizzes_count' => $quizzes,
                'exams_count' => $exams,
                'projects_count' => $projects,
                'modules' => $course->modules->map(function ($module) {
                    return [
                        'module_id' => $module->id,
                        'title' => $module->title,
                        'description' => $module->description,
                        'order' => $module->order,
                        'lectures' => $module->lectures->map(function ($lecture) {
                            return [
                                'lecture_id' => $lecture->id,
                                'title' => $lecture->title,
                                'order' => $lecture->order,
                                'lecture_video' => $lecture->lecture_video,
                                'materials' => $lecture->materials->map(function ($material) {
                                    return [
                                        'material_id' => $material->id,
                                        'title' => $material->title,
                                        'order' => $material->order,
                                        'lecture_notes' => $material->lecture_notes,
                                    ];
                                }),
                            ];
                        }),
                    ];
                }),
                'quizzes' => $course->modules->map(function ($module) {
                    return [
                        'module_id' => $module->id,
                        'quizzes' => $module->quizzes->map(function ($quiz) {
                            return [
                                'quiz_id' => $quiz->id,
                                'questions' => $quiz->questions,
                                'created_by' => $quiz->createdBy->user->full_name,
                            ];
                        }),
                    ];
                }),
                'projects' => $course->projects->map(function ($project) {
                    return [
                        'project_id' => $project->id,
                        'title' => $project->title,
                        'description' => $project->description,
                        'start_date' => $project->start_date,
                        'end_date' => $project->end_date,
                        'created_by' => $project->createdBy->user->full_name,
                    ];
                }),
                'exams' => $course->exams->map(function ($exam) {
                    return [
                        'exam_id' => $exam->id,
                        'title' => $exam->title,
                        'description' => $exam->description,
                        'start_date' => $exam->start_date,
                        'end_date' => $exam->end_date,
                        'duration_minutes' => $exam->duration_minutes,
                        'created_by' => $exam->createdBy->user->full_name,
                    ];
                }),
            ],
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Course $course)
    {
        try {
            $validated = $request->validate([
                'expert_id' => 'sometimes|exists:experts,id',
                'name' => 'sometimes|nullable|string',
                'description' => 'sometimes|nullable|string',
                'course_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,svg',
                'course_trailer' => 'sometimes|nullable|file|mimes:mp4,avi,flv,wmv,webm',
                'status' => 'sometimes|nullable|in:draft,published,archived',
            ]);

            $course->update($validated);

            if ($request->hasFile('course_image') && $request->file('course_image')->isValid()) {
                $course->clearMediaCollection('course_image');
                $course->addMediaFromRequest('course_image')->toMediaCollection('course_image');
            }

            if ($request->hasFile('course_trailer') && $request->file('course_trailer')->isValid()) {
                $course->clearMediaCollection('course_trailer');
                $course->addMediaFromRequest('course_trailer')->toMediaCollection('course_trailer');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Course updated successfully',
                'data' => $course,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        $course->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Course deleted successfully',
        ], 200);
    }

    public function getCourseByExpert(Request $request, Expert $expert)
    {
        $courses = $expert->courses()->latest()->paginate(10);

        $formattedCourses = $courses->getCollection()->map(function ($course) {
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'expert_id' => $course->expert_id,
                'expert_name' => $course->expert->user->full_name ?? null,
                'trainer_id' => $course->trainer_id ?? null,
                'trainer_name' => $course->trainer->user->full_name ?? null,
                'description' => $course->description,
                'status' => $course->status,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
            ];
        });

        $paginatedCourses = $courses->toArray();
        $paginatedCourses['data'] = $formattedCourses->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Courses fetched successfully',
            'data' => $paginatedCourses,
        ], 200);
    }

    public function getCourseByTrainer(Request $request, Trainer $trainer)
    {
        $courses = $trainer->courses()->latest()->paginate(10);

        $formattedCourses = $courses->getCollection()->map(function ($course) use ($trainer) {
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'expert_id' => $course->expert_id,
                'expert_name' => $course->expert->user->full_name ?? null,
                'trainer_id' => $trainer->id ?? null,
                'trainer_name' => $trainer->user->full_name ?? null,
                'description' => $course->description,
                'status' => $course->status,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
            ];
        });

        $paginatedCourses = $courses->toArray();
        $paginatedCourses['data'] = $formattedCourses->values()->all();

        return response()->json([
            'status' => 'success',
            'message' => 'Courses fetched successfully',
            'data' => $paginatedCourses,
        ], 200);
    }
}
