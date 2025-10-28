<?php

namespace App\Http\Controllers\TrainerControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    public function index()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        //fetch exams from assigned courses
        $exams = Auth::user()->trainer->courses->map(function ($course) {
            return $course->exams;
        })->flatten();

        return response()->json([
            'status' => 'success',
            'message' => 'Exams fetched successfully',
            'data' => $exams,
        ], 200);
    }

    public function store(Request $request, Course $course)
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

        // Validation and creation logic goes here
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        try {
            $exam = Exam::create([
                'course_id' => $course->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'duration_minutes' => $validated['duration_minutes'] ?? null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Exam created successfully',
                'data' => $exam,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create exam: ' . $e->getMessage(),
            ], 500);
        }

    }

    public function show(Course $course, Exam $exam)
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

        if ($exam->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Exam does not belong to this course.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Exam details fetched successfully',
            'data' => $exam,
        ], 200);
    }

    public function update(Request $request, Course $course, Exam $exam)
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

        if ($exam->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Exam does not belong to this course.',
            ], 403);
        }

        // Validation and update logic goes here

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'scheduled_at' => 'sometimes|nullable|date',
            'duration_minutes' => 'sometimes|nullable|integer|min:1',
        ]);

        try {
            $exam->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Exam updated successfully',
                'data' => $exam,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update exam: ' . $e->getMessage(),
            ], 500);
        }

    }

    public function destroy(Course $course, Exam $exam)
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

        if ($exam->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Exam does not belong to this course.',
            ], 403);
        }

        // Deletion logic goes here

        try {
            $exam->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Exam deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete exam: ' . $e->getMessage(),
            ], 500);
        }

    }
}
