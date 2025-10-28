<?php

namespace App\Http\Controllers\TrainerControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseQuize;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseQuizeController extends Controller
{

    public function index()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $courseIds = Auth::user()->trainer->courses->pluck('id');

        $moduleIds = Module::whereIn('course_id', $courseIds)->pluck('id');

        $quizes = CourseQuize::whereIn('module_id', $moduleIds)
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Quizzes fetched successfully',
            'data' => $quizes,
        ], 200);
    }


    public function store(Request $request, Course $course, Module $module)
    {
        $trainer = Auth::user()->trainer;

        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        if (!$trainer->courses()->where('course_id', $course->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Trainer is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This module does not belong to the given course.',
            ], 400);
        }

        $validated = $request->validate([
            'questions' => 'required|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        try {
            DB::beginTransaction();
            $validated['module_id'] = $module->id;
            $quiz = CourseQuize::create($validated);
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Quiz created successfully',
                'data' => $quiz,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create quiz: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(CourseQuize $quiz)
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Quiz fetched successfully',
            'data' => $quiz,
        ], 200);
    }

    public function update(Request $request, Course $course, Module $module, CourseQuize $quiz)
    {
        $trainer = Auth::user()->trainer;

        if (! $trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        if (! $trainer->courses()->where('course_id', $course->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Trainer is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This module does not belong to the given course.',
            ], 400);
        }

        if ($quiz->module_id !== $module->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This quiz does not belong to the given module.',
            ], 400);
        }

        $validated = $request->validate([
            'questions' => 'sometimes|required|array',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after:start_date',
        ]);

        try {
            DB::beginTransaction();

            $quiz->update($validated);

            $quiz->refresh();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Quiz updated successfully',
                'data' => $quiz,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update quiz: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function destroy(CourseQuize $quiz)
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        try {
            DB::beginTransaction();
            $quiz->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Quiz deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete quiz: ' . $e->getMessage(),
            ], 500);
        }
    }
}
