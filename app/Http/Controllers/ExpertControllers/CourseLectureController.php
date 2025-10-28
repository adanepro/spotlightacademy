<?php

namespace App\Http\Controllers\ExpertControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lecture;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourseLectureController extends Controller
{
    /**
     * Display all lectures in a specific module.
     */
    public function index(Course $course, Module $module)
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

        $lectures = Lecture::where('module_id', $module->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Lectures fetched successfully.',
            'data' => $lectures,
        ], 200);
    }

    /**
     * Store a newly created lecture.
     */
    public function store(Request $request, Course $course, Module $module)
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
            'title' => 'required|string|max:255',
            'order' => [
                'required',
                'integer',
                Rule::unique('lectures')->where(fn($q) => $q->where('module_id', $module->id)),
            ],
            'lecture_video' => 'nullable|file|mimetypes:video/mp4,video/avi,video/mpeg|max:51200',
        ]);

        try {
            DB::beginTransaction();

            $validated['module_id'] = $module->id;
            $lecture = Lecture::create($validated);

            if ($request->hasFile('lecture_video')) {
                $lecture->addMediaFromRequest('lecture_video')->toMediaCollection('lecture_video');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Lecture created successfully.',
                'data' => $lecture->load('module'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create lecture: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific lecture.
     */
    public function show(Course $course, Module $module, Lecture $lecture)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access.',
            ], 403);
        }

        if ($lecture->module_id !== $module->id || $module->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lecture does not belong to this module or course.',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Lecture fetched successfully.',
            'data' => $lecture->load('module'),
        ], 200);
    }

    /**
     * Update a lecture.
     */
    public function update(Request $request, Course $course, Module $module, Lecture $lecture)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access.',
            ], 403);
        }

        if ($lecture->module_id !== $module->id || $module->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lecture does not belong to this module or course.',
            ], 400);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'order' => [
                'sometimes',
                'integer',
                Rule::unique('lectures')
                    ->where(fn($q) => $q->where('module_id', $module->id))
                    ->ignore($lecture->id),
            ],
            'lecture_video' => 'nullable|file|mimetypes:video/mp4,video/avi,video/mpeg|max:51200',
        ]);

        try {
            DB::beginTransaction();

            $lecture->update($validated);

            if ($request->hasFile('lecture_video')) {
                $lecture->clearMediaCollection('lecture_video');
                $lecture->addMediaFromRequest('lecture_video')->toMediaCollection('lecture_video');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Lecture updated successfully.',
                'data' => $lecture->refresh(),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update lecture: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a lecture.
     */
    public function destroy(Course $course, Module $module, Lecture $lecture)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access.',
            ], 403);
        }

        if ($lecture->module_id !== $module->id || $module->course_id !== $course->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lecture does not belong to this module or course.',
            ], 400);
        }

        try {
            DB::beginTransaction();
            $lecture->clearMediaCollection('lecture_video');
            $lecture->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Lecture deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete lecture: ' . $e->getMessage(),
            ], 500);
        }
    }
}
