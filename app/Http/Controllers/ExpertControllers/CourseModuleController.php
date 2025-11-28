<?php

namespace App\Http\Controllers\ExpertControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourseModuleController extends Controller
{
    public function index(Course $course)
    {
        if (!Auth::user()->expert) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not an expert.',
            ], 403);
        }
        if ($course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }

        $modules = Module::with(['lectures.materials', 'quizzes'])
            ->where('course_id', $course->id)
            ->latest()
            ->paginate(10);


        $formattedModules = $modules->map(function ($module) {
            return [
                'module_id' => $module->id,
                'title' => $module->title,
                'description' => $module->description,
                'order' => $module->order,
                'course_id' => $module->course_id,
                'lectures_count' => $module->lectures->count(),
                'lecture_materials_count' => $module->lectures->sum(fn($lecture) => $lecture->materials->count()),
                'quizzes_count' => $module->quizzes->count(),
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
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Modules fetched successfully',
            'data' => $formattedModules,
        ], 200);
    }

    public function store(Request $request, Course $course)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'order' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('modules')
                    ->where(fn($query) => $query->where('course_id', $course->id)),
            ],
        ]);

        $validated['course_id'] = $course->id;

        try {

            DB::beginTransaction();
            $module = Module::create($validated);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Module created successfully',
                'data' => $module,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Module $module, Course $course)
    {
        if (!Auth::user()->expert || $module->course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Module fetched successfully',
            'data' => $module,
        ], 200);
    }

    public function update(Request $request, Course $course, Module $module)
    {

        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This module does not belong to the given course.',
            ], 400);
        }

        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'order' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('modules')
                    ->where(fn($query) => $query->where('course_id', $course->id))
                    ->ignore($module->id),
            ],
        ]);

        try {
            DB::beginTransaction();

            $module->update($validated);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Module updated successfully',
                'data' => $module,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Course $course, Module $module)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This module does not belong to the specified course.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $module->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Module deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete module: ' . $e->getMessage(),
            ], 500);
        }
    }
}
