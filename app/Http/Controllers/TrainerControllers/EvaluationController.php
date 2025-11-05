<?php

namespace App\Http\Controllers\TrainerControllers;

use App\Http\Controllers\NotificationController;
use App\Models\CourseQuize;
use App\Models\EnrollmentExam;
use App\Models\EnrollmentProject;
use App\Models\EnrollmentQuiz;
use App\Models\Exam;
use App\Models\ProjectSubmission;
use App\Models\ExamSubmission;
use App\Models\Project;
use App\Models\QuizSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EvaluationController extends NotificationController
{

    /**
     * Get all submissions of a project created by the trainer
     */
    public function getProjectSubmissions(Project $project)
    {
        $trainer = Auth::user()->trainer;
        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        if ($project->created_by !== $trainer->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Trainer is not the creator of this project.',
            ], 403);
        }

        $submissions = ProjectSubmission::where('project_id', $project->id)
            ->with(['enrollment.student', 'media'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Project submissions fetched successfully.',
            'data' => $submissions,
        ], 200);
    }

    /**
     * Evaluate a project submission
     */
    public function evaluateProject(Request $request, EnrollmentProject $enrollmentProject)
    {
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        if (!$trainer->courses()->where('course_id', $enrollmentProject->project->course_id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Trainer is not assigned to this course.',
            ], 403);
        }

        $data = $request->validate([
            'status' => 'required|in:passed,failed,in_review',
            'review_comments' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $submission = $enrollmentProject->submission;
            $submission->update([
                'status' => $data['status'],
                'review_comments' => $data['review_comments'] ?? null,
            ]);

            if ($submission->status === 'passed') {
                $enrollmentProject->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'completed_at' => now(),
                ]);
            }

            $submission->enrollment->update([
                'progress' => $submission->enrollment->calculateProgress(),
            ]);

            DB::commit();
            // notification
            $user = User::whereHas('roles', function ($query) {
                $query->where('name', 'Student');
            })
                ->where('id', $submission->enrollment->student->user_id)
                ->first();

            $body = [
                'title' => 'Project Evaluation',
                'body' => [
                    'message' => 'Your project submission has been evaluated.',
                    'status' => $submission->status,
                    'comments' => $submission->review_comments ?? null,
                ],
            ];

            $this->notify($body, $user);

            return response()->json([
                'status' => 'success',
                'message' => 'Project evaluated successfully.',
                'data' => $submission,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Evaluation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all submissions of a exam created by the trainer
     */
    public function getExamSubmissions(Exam $exam)
    {
        $trainer = Auth::user()->trainer;
        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        if ($exam->created_by !== $trainer->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Trainer is not the creator of this exam.',
            ], 403);
        }

        $submissions = ExamSubmission::where('exam_id', $exam->id)
            ->with(['enrollment.student', 'media'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Exam submissions fetched successfully.',
            'data' => $submissions,
        ], 200);
    }

    /**
     * Evaluate an exam submission
     */
    public function evaluateExam(Request $request, EnrollmentExam $enrollmentExam)
    {
        $trainer = Auth::user()->trainer;
        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        if (!$trainer->courses()->where('course_id', $enrollmentExam->exam->course_id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. Trainer is not assigned to this course.',
            ], 403);
        }

        $data = $request->validate([
            'status' => 'required|in:passed,failed,in_review',
            'review_comments' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $submission = $enrollmentExam->submission;
            $submission->update([
                'status' => $data['status'],
                'review_comments' => $data['review_comments'] ?? null,
            ]);

            if ($submission->status === 'passed') {
                $enrollmentExam->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'completed_at' => now(),
                ]);
            }

            $submission->enrollment->update([
                'progress' => $submission->enrollment->calculateProgress(),
            ]);

            DB::commit();
            // notification
            $user = User::whereHas('roles', function ($query) {
                $query->where('name', 'Student');
            })
                ->where('id', $submission->enrollment->student->user_id)
                ->first();

            $body = [
                'title' => 'Exam Evaluation',
                'body' => [
                    'message' => 'Your exam submission has been evaluated.',
                    'status' => $submission->status,
                    'comments' => $submission->review_comments ?? null,
                ],
            ];

            $this->notify($body, $user);

            return response()->json([
                'status' => 'success',
                'message' => 'Exam evaluated successfully.',
                'data' => $submission,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Evaluation failed: ' . $e->getMessage(),
            ], 500);
        }           
    }

    /**
     * Evaluate a quiz submission
     */
    public function evaluateQuiz(Request $request, QuizSubmission $submission)
    {
        $data = $request->validate([
            'status' => 'required|in:passed,failed,in_review',
            'review_comments' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $submission->update([
                'status' => $data['status'],
                'review_comments' => $data['review_comments'] ?? null,
            ]);

            if ($submission->status === 'passed') {
                $enrollmentQuiz = $submission->enrollmentQuiz;
                $enrollmentQuiz->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'completed_at' => now(),
                ]);
            }

            $submission->enrollment->update([
                'progress' => $submission->enrollment->calculateProgress(),
            ]);

            DB::commit();
            // notification
            $user = User::whereHas('roles', function ($query) {
                $query->where('name', 'Student');
            })
                ->where('id', $submission->enrollment->student->user_id)
                ->first();

            $body = [
                'title' => 'Quiz Evaluation',
                'body' => [
                    'message' => 'Your quiz submission has been evaluated.',
                    'status' => $submission->status,
                    'comments' => $submission->review_comments ?? null,
                ],
            ];

            $this->notify($body, $user);

            return response()->json([
                'status' => 'success',
                'message' => 'Quiz evaluated successfully.',
                'data' => $submission,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Evaluation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getFailedStudentOnProject()
    {
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $failedSubmissions = ProjectSubmission::where('status', 'failed')
            ->whereHas('project', function ($q) use ($trainer) {
                $q->where('created_by', $trainer->id);
            })
            ->with(['project', 'enrollment.student.user', 'enrollmentProject'])
            ->latest()
            ->get();

        if ($failedSubmissions->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No failed students found.',
                'data' => [],
            ], 200);
        }

        $formatted = $failedSubmissions->map(function ($submission) {
            return [
                'submission_id' => $submission->id,
                'enrollment_project_id' => $submission->enrollmentProject->id,
                'project_id' => $submission->project->id,
                'student_id' => $submission->enrollment->student->id,
                'student_name' => $submission->enrollment->student->user->full_name,
                'project_title' => $submission->project->title,
                'course_title' => $submission->course->name ?? null,
                'status' => $submission->status,
                'review_comments' => $submission->review_comments,
                'submitted_at' => $submission->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Failed students retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    public function assignRemedialProject(Request $request)
    {
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'failed_enrollment_project_ids' => 'required|array',
            'failed_enrollment_project_ids.*' => 'required|exists:enrollment_projects,id',
        ]);

        $project = Project::where('id', $data['project_id'])
            ->where('created_by', $trainer->id)
            ->first();

        if (!$project) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. You can only assign projects you created.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $remidal = [];
            foreach ($data['failed_enrollment_project_ids'] as $failedId) {
                $failed = EnrollmentProject::find($failedId);

                if ($failed && optional($failed->submission)->status === 'failed') {

                    $exixtingRemidal = EnrollmentProject::where('remedial_of', $failed->id)->first();
                    if ($exixtingRemidal) {
                        continue;
                    }

                    $remidal[] = EnrollmentProject::create([
                        'enrollment_id' => $failed->enrollment_id,
                        'project_id' => $project->id,
                        'remedial_of' => $failed->id,
                        'status' => 'not_started',
                        'progress' => 0,
                        'started_at' => now(),
                        'completed_at' => null,
                    ]);

                    // notify student
                    $user = User::whereHas('roles', function ($query) {
                        $query->where('name', 'Student');
                    })
                        ->where('id', $failed->enrollment->student->user_id)
                        ->first();

                    $body = [
                        'title' => 'Remedial Project Assigned',
                        'body' => [
                            'message' => 'A remedial project has been assigned to you.',
                            'project_title' => $project->title,
                        ],
                    ];

                    $this->notify($body, $user);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Remedial projects assigned successfully.',
                'data' => $remidal,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign remedial projects: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getFailedStudentOnExam()
    {
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $failedSubmissions = ExamSubmission::where('status', 'failed')
            ->whereHas('exam', function ($q) use ($trainer) {
                $q->where('created_by', $trainer->id);
            })
            ->with(['exam', 'enrollment.student.user', 'enrollmentExam'])
            ->latest()
            ->get();

        if ($failedSubmissions->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No failed students found.',
                'data' => [],
            ], 200);
        }

        $formatted = $failedSubmissions->map(function ($submission) {
            return [
                'submission_id' => $submission->id,
                'student_id' => $submission->enrollment->student->id,
                'enrollment_id' => $submission->enrollment->id,
                'enrollment_exam_id' => $submission->enrollmentExam->id,
                'student_name' => $submission->enrollment->student->user->full_name,
                'exam_title' => $submission->exam->title,
                'course_title' => $submission->course->title ?? null,
                'status' => $submission->status,
                'review_comments' => $submission->review_comments,
                'submitted_at' => $submission->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Failed students retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    public function assignRemedialExam(Request $request)
    {
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $data = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'failed_enrollment_exam_ids' => 'required|array',
            'failed_enrollment_exam_ids.*' => 'required|exists:enrollment_exams,id',
        ]);

        $exam = Exam::where('id', $data['exam_id'])
            ->where('created_by', $trainer->id)
            ->first();

        if (!$exam) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. You can only assign exams you created.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            $remidal = [];
            foreach ($data['failed_enrollment_exam_ids'] as $failedId) {
                $failed = EnrollmentExam::find($failedId);

                if ($failed && optional($failed->submission)->status === 'failed') {

                    $exixtingRemidal = EnrollmentExam::where('remedial_of', $failed->id)->first();
                    if ($exixtingRemidal) {
                        continue;
                    }

                    $remidal[] = EnrollmentExam::create([
                        'enrollment_id' => $failed->enrollment_id,
                        'exam_id' => $exam->id,
                        'remedial_of' => $failed->id,
                        'status' => 'not_started',
                        'progress' => 0,
                        'started_at' => now(),
                        'completed_at' => null,
                    ]);

                    // notify student
                    $user = User::whereHas('roles', function ($query) {
                        $query->where('name', 'Student');
                    })
                        ->where('id', $failed->enrollment->student->user_id)
                        ->first();

                    $body = [
                        'title' => 'Remedial Exam Assigned',
                        'body' => [
                            'message' => 'A remedial exam has been assigned to you.',
                            'exam_title' => $exam->title,
                        ],
                    ];

                    $this->notify($body, $user);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Remedial exams assigned successfully.',
                'data' => $remidal,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign remedial exams: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getFailedStudentOnQuiz()
    {
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $failedSubmissions = QuizSubmission::where('status', 'failed')
            ->whereHas('quiz', function ($q) use ($trainer) {
                $q->where('created_by', $trainer->id);
            })
            ->with(['quiz', 'enrollment.student.user', 'enrollmentQuiz'])
            ->latest()
            ->get();

        if ($failedSubmissions->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No failed students found.',
                'data' => [],
            ], 200);
        }

        $formatted = $failedSubmissions->map(function ($submission) {
            return [
                'submission_id' => $submission->id,
                'student_id' => $submission->enrollment->student->id,
                'enrollment_id' => $submission->enrollment->id,
                'enrollment_quiz_id' => $submission->enrollmentQuiz->id,
                'student_name' => $submission->enrollment->student->user->full_name,
                'quiz_title' => $submission->quiz->title,
                'course_title' => $submission->course->title ?? null,
                'status' => $submission->status,
                'review_comments' => $submission->review_comments,
                'submitted_at' => $submission->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Failed students retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    public function assignRemedialQuiz(Request $request)
    {
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $data = $request->validate([
            'quiz_id' => 'required|exists:course_quizes,id',
            'failed_enrollment_quiz_ids' => 'required|array',
            'failed_enrollment_quiz_ids.*' => 'required|exists:enrollment_quizzes,id',
        ]);

        $quiz = CourseQuize::where('id', $data['quiz_id'])
            ->where('created_by', $trainer->id)
            ->first();

        if (!$quiz) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. You can only assign quizzes you created.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            foreach ($data['failed_enrollment_quiz_ids'] as $failedId) {
                $failed = EnrollmentQuiz::find($failedId);

                if ($failed && optional($failed->submission)->status === 'failed') {

                    $exixtingRemidal = EnrollmentQuiz::where('remedial_of', $failed->id)->first();
                    if ($exixtingRemidal) {
                        continue;
                    }

                    $remidal = EnrollmentQuiz::create([
                        'enrollment_id' => $failed->enrollment_id,
                        'quiz_id' => $quiz->id,
                        'module_id' => $failed->module_id,
                        'remedial_of' => $failed->id,
                        'status' => 'not_started',
                        'progress' => 0,
                        'started_at' => now(),
                        'completed_at' => null,
                    ]);

                    // notify student
                    $user = User::whereHas('roles', function ($query) {
                        $query->where('name', 'Student');
                    })
                        ->where('id', $failed->enrollment->student->user_id)
                        ->first();

                    $body = [
                        'title' => 'Remedial Quiz Assigned',
                        'body' => [
                            'message' => 'A remedial quiz has been assigned to you.',
                            'quiz_title' => $quiz->title,
                        ],
                    ];

                    $this->notify($body, $user);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Remedial quizzes assigned successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign remedial quizzes: ' . $e->getMessage(),
            ], 500);
        }
    }
}
