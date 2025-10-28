<?php

namespace App\Http\Controllers\ExpertControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lecture;
use App\Models\LectureMaterial;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourseLectureMaterialController extends Controller
{
    public function index(Course $course, Module $module, Lecture $lecture)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id || $lecture->module_id !== $module->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This lecture does not belong to the given module and course.',
            ], 400);
        }

        $materials = $lecture->materials()->latest()->paginate(10);
        return response()->json([
            'status' => 'success',
            'message' => 'Materials fetched successfully',
            'data' => $materials,
        ], 200);
    }

    public function store(Request $request, Course $course, Module $module, Lecture $lecture)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id || $lecture->module_id !== $module->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This lecture does not belong to the given module and course.',
            ], 400);
        }

        $validated = $request->validate([
            'title' => 'required|string',
            'order' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('lecture_materials')->where(fn($q) => $q->where('lecture_id', $lecture->id)),
            ],
            'lecture_notes' => 'nullable|file|mimes:pdf,doc,docx',
        ]);

        try {
            DB::beginTransaction();
            $validated['lecture_id'] = $lecture->id;
            $material = $lecture->materials()->create($validated);

            if ($request->hasFile('lecture_notes')) {
                $material->addMediaFromRequest('lecture_notes')->toMediaCollection('lecture_notes');
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Material created successfully',
                'data' => $material,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create material',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Course $course, Module $module, Lecture $lecture, LectureMaterial $material)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id || $lecture->module_id !== $module->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This lecture does not belong to the given module and course.',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Material fetched successfully',
            'data' => $material,
        ], 200);
    }

    public function update(Request $request, Course $course, Module $module, Lecture $lecture, LectureMaterial $material)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id || $lecture->module_id !== $module->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This lecture does not belong to the given module and course.',
            ], 400);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string',
            'order' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                Rule::unique('lecture_materials')->where(fn($q) => $q->where('lecture_id', $lecture->id))->ignore($material->id),
            ],
            'lecture_notes' => 'nullable|file|mimes:pdf,doc,docx',
        ]);

        try {
            DB::beginTransaction();
            $material->update($validated);

            if ($request->hasFile('lecture_notes')) {
                $material->clearMediaCollection('lecture_notes');
                $material->addMediaFromRequest('lecture_notes')->toMediaCollection('lecture_notes');
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Material updated successfully',
                'data' => $material->refresh(),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update material',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Course $course, Module $module, Lecture $lecture, LectureMaterial $material)
    {
        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not assigned to this course.',
            ], 403);
        }

        if ($module->course_id !== $course->id || $lecture->module_id !== $module->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This lecture does not belong to the given module and course.',
            ], 400);
        }

        try {
            DB::beginTransaction();
            $material->clearMediaCollection('lecture_notes');
            $material->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Material deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete material',
                'error' => $e->getMessage(),
            ], 500);
        }
    }   
}
