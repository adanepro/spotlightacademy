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

    public function allProjects()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $projects = Auth::user()->trainer->projects;
        if ($projects->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No projects found.',
                'data' => [],
            ], 200);
        }

        // if project has submissions,and status is not in ['passed', 'failed'] then has_submissions is false
        $projects = $projects->map(function ($project) {
            if ($project->submissions->count() > 0 && !in_array($project->submissions->first()->status, ['passed', 'failed'])) {
                $project->has_submissions = true;
            } else {
                $project->has_submissions = false;
            }
            return $project;
        });

        $projects = Project::where('created_by', Auth::user()->trainer->id)
            ->with(['course', 'submissions'])
            ->latest()
            ->get();
        $projects = $projects->map(function ($project) {
            return [
                'project_id' => $project->id,
                'course_id' => $project->course->id,
                'course_name' => $project->course->name,
                'title' => $project->title,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'status' => $project->status,
                'has_submissions' => $project->has_submissions,
            ];
        });

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
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {

            DB::beginTransaction();
            $project = Project::create([
                'course_id' => $course->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
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
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
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
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
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

    public function getEvaluatedProjects()
    {
        // get all evaluated projects created by the trainer,
        // display course name, project title, project_strat_date, project_end_date, faild_student_count, passed_student_count, total_student_count
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $evaluatedProjects = Project::whereHas('submissions', function ($q) {
            $q->whereIn('status', ['passed', 'failed']);
        })
            ->where('created_by', $trainer->id)
            ->with(['course', 'submissions'])
            ->latest()
            ->get();

        if ($evaluatedProjects->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No evaluated projects found.',
                'data' => [],
            ], 200);
        }

        $formatted = $evaluatedProjects->map(function ($project) {
            return [
                'project_id' => $project->id,
                'course_name' => $project->course->name ?? null,
                'project_title' => $project->title,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'failed_student_count' => $project->submissions->where('status', 'failed')->count(),
                'passed_student_count' => $project->submissions->where('status', 'passed')->count(),
                'total_student_count' => $project->submissions->count(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Evaluated projects retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    public function getProjectDetails(Project $project)
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        if ($project->created_by !== Auth::user()->trainer->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Trainer is not the creator of this project.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Project details fetched successfully',
            'data' => $project,
        ], 200);
    }
}
