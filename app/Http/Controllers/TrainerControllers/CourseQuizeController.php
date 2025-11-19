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

    public function getQuizzesByCourse(Course $course)
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        if (!Auth::user()->trainer->courses->contains($course)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Trainer is not assigned to this course.',
            ], 403);
        }

        $quizes = CourseQuize::whereHas('module', function ($q) use ($course) {
            $q->where('course_id', $course->id);
        })
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Quizzes fetched successfully',
            'data' => $quizes,
        ], 200);
    }

    public function allQuizzes()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $quizes = Auth::user()->trainer->quizzes;

        if ($quizes->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No quizzes found.',
                'data' => [],
            ], 200);
        }

        // if quiz has submissions,and status is not in ['passed', 'failed'] then has_submissions is false
        $quizes = $quizes->map(function ($quiz) {
            if ($quiz->submissions->count() > 0 && !in_array($quiz->submissions->first()->status, ['passed', 'failed'])) {
                $quiz->has_submissions = true;
            } else {
                $quiz->has_submissions = false;
            }
            return $quiz;
        });

        $quizes = CourseQuize::where('created_by', Auth::user()->trainer->id)
            ->with(['module.course', 'submissions'])
            ->latest()
            ->get();
        $quizes = $quizes->map(function ($quiz) {
            return [
                'quiz_id' => $quiz->id,
                'course_id' => $quiz->module->course->id,
                'course_name' => $quiz->module->course->name,
                'module_id' => $quiz->module->id,
                'module_name' => $quiz->module->title,
                'title' => $quiz->title,
                'has_submissions' => $quiz->has_submissions,
            ];
        });

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
            'questions.*.question' => 'required|string',
            'questions.*.type' => 'required|in:mcq,short_answer',
            'questions.*.options' => 'required_if:questions.*.type,mcq|array',
            'questions.*.options.*' => 'required_if:questions.*.type,mcq|string',
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
            'questions.*.question' => 'sometimes|required|string',
            'questions.*.type' => 'sometimes|required|in:mcq,short_answer',
            'questions.*.options' => 'sometimes|required_if:questions.*.type,mcq|array',
            'questions.*.options.*' => 'sometimes|required_if:questions.*.type,mcq|string',
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

    public function getEvaluatedQuizzes()
    {
        // get all evaluated quizzes created by the trainer,
        // display course name, quiz title, quiz_strat_date, quiz_end_date, faild_student_count, passed_student_count, total_student_count
        $trainer = Auth::user()->trainer;
        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $evaluatedQuizzes = CourseQuize::whereHas('submissions', function ($q) {
            $q->whereIn('status', ['passed', 'failed']);
        })
            ->where('created_by', $trainer->id)
            ->with(['module.course', 'submissions'])
            ->latest()
            ->get();

        if ($evaluatedQuizzes->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No evaluated quizzes found.',
                'data' => [],
            ], 200);
        }

        $formatted = $evaluatedQuizzes->map(function ($quiz) {
            return [
                'quiz_id' => $quiz->id,
                'course_name' => $quiz->module->course->name ?? null,
                'quiz_title' => $quiz->title,
                'failed_student_count' => $quiz->submissions->where('status', 'failed')->count(),
                'passed_student_count' => $quiz->submissions->where('status', 'passed')->count(),
                'total_student_count' => $quiz->submissions->count(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Evaluated quizzes retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }
}
