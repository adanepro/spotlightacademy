<?php

namespace App\Http\Controllers\StudentControllers;

use App\Http\Controllers\NotificationController;
use App\Models\EnrollmentProject;
use App\Models\Project;
use App\Models\ProjectSubmission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectSubmissionController extends NotificationController
{
    /**
     * Submit a project file or link.
     */
    public function submit(Request $request, EnrollmentProject $enrollmentProject)
    {
        $student = Auth::user()->student;
        $project = $enrollmentProject->project;

        if (!$enrollmentProject->enrollment_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Enrollment ID missing for this project enrollment.',
            ], 400);
        }

        // $now = now();

        // if ($now->lt($project->start_date)) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Project submission is not yet open.',
        //     ], 400);
        // }

        // if ($now->gt($project->end_date)) {
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'Project submission deadline has passed.',
        //     ], 400);
        // }


        $data = $request->validate([
            'project_file' => 'nullable|file|mimes:pdf,doc,docx,zip|max:20480', // max 20MB
            'link' => 'nullable|url',
        ]);

        try {
            DB::beginTransaction();

            $enrollmentProject = EnrollmentProject::where('id', $enrollmentProject->id)
                ->whereHas('enrollment', function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                })
                ->firstOrFail();

            // Prevent duplicate submission unless resubmission is allowed
            // $existingSubmission = $enrollmentProject->submission;
            // if ($existingSubmission->status !== 'failed') {
            //     return response()->json([
            //         'status' => 'error',
            //         'message' => 'You have already submitted this project.',
            //     ], 400);
            // }

            // Create or update submission
            $submission = ProjectSubmission::updateOrCreate(
                [
                    'enrollment_project_id' => $enrollmentProject->id,
                ],
                [
                    'enrollment_id' => $enrollmentProject->enrollment_id,
                    'project_id' => $enrollmentProject->project_id,
                    'course_id' => $enrollmentProject->project->course_id,
                    'status' => 'submitted',
                    'review_comments' => null,
                    'link' => $data['link'] ?? null,
                ]
            );

            // Handle file upload (replace old if re-submitting)
            if ($request->hasFile('project_file')) {
                $submission->clearMediaCollection('project_file');
                $submission
                    ->addMediaFromRequest('project_file')
                    ->toMediaCollection('project_file');
            }

            // Update enrollment project
            $enrollmentProject->update([
                'status' => 'in_progress',
                'started_at' => $enrollmentProject->started_at ?? now(),
            ]);

            DB::commit();

            activity()
                ->causedBy(Auth::user())
                ->performedOn($submission)
                ->withProperties([
                    'project' => $project->title,
                    'trainer_id' => $project->created_by,
                    'course_id' => $project->course_id,
                    'course_name' => $project->course->name,
                    'student_id' => $student->id,
                    'student_name' => $student->user->full_name,
                ])
                ->log('{student_name} submitted project {project} for review');

            if ($project->createdBy && $project->createdBy->user) {
                $trainerUser = $project->createdBy->user;

                $body = [
                    'title' => 'Project Submission',
                    'body' => [
                        'message' => $student->user->full_name . ' has submitted a project titled "' . $project->title . '" for review.',
                    ],
                ];

                $this->notify($body, $trainerUser);
            }

            /**
             * Log activity
             */

            // $activity = activity()
            //     ->causedBy(Auth::user())
            //     ->performedOn($submission)
            //     ->withProperties([
            //         'project' => $project->title,
            //         'trainer_id' => $project->created_by,
            //     ])
            //     ->event('project_submitted')
            //     ->log('submitted project');


            return response()->json([
                'status' => 'success',
                'message' => 'Project submitted successfully.',
                'data' => $submission,
                // 'activity' => $activity,

            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Project submission failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show student’s project submissions.
     */
    public function index()
    {
        $student = Auth::user()->student;

        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this.',
            ], 403);
        }

        // Fetch only submitted projects
        $submissions = ProjectSubmission::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->where('status', 'submitted')
            ->with(['project', 'course', 'enrollmentProject'])
            ->latest()
            ->get();

        // Format and determine late / ontime for each submission
        $formatted = $submissions->map(function ($submission) {

            $submittedAt = Carbon::parse($submission->created_at);
            $endDate = Carbon::parse($submission->project->end_date);

            $submissionStatus = $submittedAt->gt($endDate) ? 'late' : 'ontime';

            return [
                'project_id'           => $submission->project->id,
                'project_title'        => $submission->project->title,
                'course_id'            => $submission->course->id,
                'course_name'          => $submission->course->name,
                'project_enrollment_id' => $submission->enrollmentProject->id,
                'status'               => $submission->status,
                'submission_status'    => $submissionStatus, 
                'review_comments'      => $submission->review_comments,
                'submitted_at'         => $submittedAt->toDateTimeString(),
                'file'                 => $submission->getFirstMediaUrl('project_file') ?? null,
                'link'                 => $submission->link ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Project submissions retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    /**
     * Show single project submission.
     */
    public function show(ProjectSubmission $projectSubmission)
    {
        $student = Auth::user()->student;

        if (!$projectSubmission->enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Enrollment not found for this submission.',
            ], 404);
        }

        if ($projectSubmission->enrollment->student_id !== $student->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: You cannot view this submission.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Project submission details retrieved successfully.',
            'data' => $projectSubmission->load(['project', 'course', 'media']),
        ], 200);
    }

    public function allProjects(Request $request)
    {
        $student = Auth::user()->student;
        $institutionId = $student->institution_id ?? null;

        if (!$institutionId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student does not belong to any institution.',
            ], 400);
        }

        $status = $request->query('status');

        $courseIds = $student->enrollments()->pluck('course_id');

        $query = Project::whereIn('course_id', $courseIds)
            ->whereHas('createdBy', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })->whereBetween('start_date', [Carbon::now(), Carbon::now()->addWeek()]);

        if (in_array($status, ['upcoming', 'ongoing', 'closed'])) {
            $query->where('status', $status);

            if ($status === 'upcoming') {
                $query->whereBetween('start_date', [Carbon::now(), Carbon::now()->addWeek()]);
            }
        }

        $projects = $query->latest()->get();

        $formattedProjects = $projects->map(function ($project) use ($student) {
            $enrollmentProject = $project->enrollmentProjects()
                ->where('enrollment_id', $student->enrollments()->first()->id ?? null)
                ->first();
            return [
                'project_id'   => $project->id,
                'project_enrollment_id' => $enrollmentProject?->id ?? null,
                'course_id'    => $project->course->id,
                'course_name'  => $project->course->name,
                'title'        => $project->title,
                'description'  => $project->description,
                'start_date'   => $project->start_date,
                'end_date'     => $project->end_date,
                'remedial_of'  => $enrollmentProject?->remedial_of ?? null,
                'resubmission_count' => $enrollmentProject->submission->resubmission_count ?? 0,
                'status'       => $project->status,
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Projects retrieved successfully.',
            'filter'  => $status ?? 'all',
            'data'    => $formattedProjects,
        ], 200);
    }

    public function getUpcomingProjects()
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $upcomingProjects = Project::whereHas('enrollmentProjects.enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->where('start_date', '>=', now())
            ->with(['course'])
            ->latest()
            ->get();


        if ($upcomingProjects->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No upcoming projects found.',
                'data' => [],
            ], 200);
        }

        $formatted = $upcomingProjects->map(function ($project) {
            return [
                'project_id'   => $project->id,
                'project_enrollment_id' => $project->enrollmentProjects()->first()->id ?? null,
                'project_status' => $project->enrollmentProjects()->first()->status ?? 'not_started',
                'course_id'    => $project->course->id,
                'course_name'  => $project->course->name,
                'title'        => $project->title,
                'description'  => $project->description,
                'start_date'   => $project->start_date,
                'end_date'     => $project->end_date,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Upcoming projects retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    public function getEvaluatedProjects()
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $evaluatedSubmissions = ProjectSubmission::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->whereIn('status', ['passed', 'failed'])
            ->with(['project', 'course', 'media'])
            ->latest()
            ->get();

        if ($evaluatedSubmissions->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No evaluated projects found.',
                'data' => [],
            ], 200);
        }

        $formatted = $evaluatedSubmissions->map(function ($submission) {
            return [
                'submission_id' => $submission->id,
                'project_title' => $submission->project->title ?? null,
                'course_title'  => $submission->course->title ?? null,
                'status'        => $submission->status,
                'review_comments' => $submission->review_comments,
                'dadeline'       => $submission->project->end_date,
                'submitted_at'  => $submission->created_at->toDateTimeString(),
                'evaluated_at'  => $submission->updated_at->toDateTimeString(),
                'file'          => $submission->getFirstMediaUrl('project_file') ?? null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Evaluated projects retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    // Get Projects that are assigned as remidal
    public function getRemedialProjects()
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $remedialProjects = EnrollmentProject::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->where('remedial_of', '!=', null)
            ->where('status', '!=', 'completed')
            ->with(['project', 'course'])
            ->latest()
            ->get();

        if ($remedialProjects->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No remedial projects found.',
                'data' => [],
            ], 200);
        }

        $formatted = $remedialProjects->map(function ($project) {
            return [
                'project_id' => $project->id,
                'project_title' => $project->project->title ?? null,
                'description' => $project->project->description ?? null,
                'project_enrollment_id' => $project->id,
                'course_title'  => $project->course->title ?? null,
                'remedial_of'   => $project->remedial_of,
                'status'        => $project->status,
                'start_date'    => $project->project->start_date,
                'end_date'      => $project->project->end_date,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Remedial projects retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }
}
