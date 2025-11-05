<?php

namespace App\Http\Controllers\TrainerControllers;

use App\Http\Controllers\NotificationController;
use App\Models\Course;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectController extends NotificationController
{
    public function index()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $projects = Auth::user()->trainer->courses->map(function ($course) {
            return $course->projects;
        })->flatten();

        return response()->json([
            'status' => 'success',
            'message' => 'Projects fetched successfully',
            'data' => $projects,
        ], 200);
    }

    public function store(Request $request, Course $course)
    {
        $trainerInstitutionId = Auth::user()->trainer->institution_id;
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

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        try {

            DB::beginTransaction();
            $project = Project::create([
                'course_id' => $course->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ]);
            DB::commit();

            $users = User::whereHas('roles', function ($query) {
                $query->where('name', 'Student');
            })
                ->whereHas('student', function ($query) use ($trainerInstitutionId) {
                    $query->where('institution_id', $trainerInstitutionId);
                })
                ->get();

            $body = [
                'title' => 'New Project',
                'body' => [
                    'message' => 'A new project has been created for ' . $course->name . '.',
                    'title' => $project->title,
                    'from' => $project->from,
                    'to' => $project->to,
                ],
            ];

            foreach ($users as $user) {
                $this->notify($body, $user);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Project created successfully',
                'data' => $project,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create project' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(Course $course, Project $project)
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

        return response()->json([
            'status' => 'success',
            'message' => 'Project fetched successfully',
            'data' => $project,
        ], 200);
    }

    public function update(Request $request, Course $course, Project $project)
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

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'from' => 'sometimes|nullable|date',
            'to' => 'sometimes|nullable|date|after_or_equal:from',
        ]);

        try {
            DB::beginTransaction();

            $project->update($validated);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Project updated successfully',
                'data' => $project,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update project',
            ], 500);
        }
    }

    public function destroy(Course $course, Project $project)
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

        try {
            DB::beginTransaction();
            $project->delete();
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Project deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete project',
            ], 500);
        }
    }
}
