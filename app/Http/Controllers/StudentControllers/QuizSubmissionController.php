<?php

namespace App\Http\Controllers\StudentControllers;

use App\Http\Controllers\NotificationController;
use App\Models\CourseQuize;
use App\Models\EnrollmentQuiz;
use App\Models\QuizSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuizSubmissionController extends NotificationController
{
    /**
     * Submit a quiz for a student.
     */
    public function submit(Request $request, EnrollmentQuiz $enrollmentQuiz)
    {
        $student = Auth::user()->student;
        $quiz = $enrollmentQuiz->quiz;

        $validated = $request->validate([
            'answers'            => 'nullable|array',
            'link'               => 'nullable|url',
            'quiz_file'          => 'nullable|file|mimes:pdf,doc,docx,zip|max:10240',
        ]);

        try {
            DB::beginTransaction();

            // Verify ownership
            $enrollmentQuiz = EnrollmentQuiz::where('id', $enrollmentQuiz->id)
                ->whereHas('enrollment', function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                })
                ->firstOrFail();

            // Create or update submission
            $quizSubmission = QuizSubmission::updateOrCreate(
                ['enrollment_quiz_id' => $enrollmentQuiz->id],
                [
                    'enrollment_id'   => $enrollmentQuiz->enrollment_id,
                    'quiz_id'         => $enrollmentQuiz->quiz_id,
                    'module_id'       => $enrollmentQuiz->module_id,
                    'course_id'       => $enrollmentQuiz->module->course_id,
                    'status'          => 'submitted',
                    'review_comments' => null,
                    'link'            => $validated['link'] ?? null,
                    'answers'         => $validated['answers'],
                ]
            );

            // Handle file upload
            if ($request->hasFile('quiz_file')) {
                $quizSubmission->clearMediaCollection('quiz_file');
                $quizSubmission
                    ->addMediaFromRequest('quiz_file')
                    ->toMediaCollection('quiz_file');
            }

            //Update enrollment quiz progress
            $enrollmentQuiz->update([
                'status'       => 'in_progress',
                'started_at'   => $enrollmentQuiz->started_at ?? now(),
            ]);

            // Optionally update total enrollment progress (if you track per-student progress)
            if (method_exists($enrollmentQuiz->enrollment, 'calculateProgress')) {
                $enrollmentQuiz->enrollment->update([
                    'progress' => $enrollmentQuiz->enrollment->calculateProgress(),
                ]);
            }

            DB::commit();

            activity()
                ->causedBy(Auth::user())
                ->performedOn($quizSubmission)
                ->withProperties([
                    'course' => $enrollmentQuiz->enrollment->course->name,
                    'quiz' => $quiz->title,
                ])
                ->log('quiz submitted');

            // notification
            if ($quiz->createdBy && $quiz->createdBy->user) {
                $trainerUser = $quiz->createdBy->user;

                $body = [
                    'title' => 'Quiz Submission',
                    'body' => [
                        'message' => $student->user->full_name . ' has submitted a quiz titled "' . $quiz->title . '" for review.',
                    ],
                ];

                $this->notify($body, $trainerUser);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Quiz submitted successfully.',
                'data'    => $quizSubmission->load(['quiz', 'course']),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Quiz submission failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * View quiz submission by enrollment quiz ID.
     */
    public function show(EnrollmentQuiz $enrollmentQuiz)
    {
        $student = Auth::user()->student;

        if ($enrollmentQuiz->enrollment->student_id !== $student->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized access to this quiz submission.',
            ], 403);
        }

        $submission = $enrollmentQuiz->submission;

        return response()->json([
            'status'  => 'success',
            'message' => 'Quiz submission retrieved successfully.',
            'data'    => $submission,
        ], 200);
    }

    /**
     * List all quiz submissions for the logged-in student.
     */
    public function index()
    {
        $student = Auth::user()->student;

        $submissions = QuizSubmission::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->with(['quiz', 'course'])->latest()->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Quiz submissions retrieved.',
            'data'    => $submissions,
        ], 200);
    }

    public function allQuizzes()
    {
        $student = Auth::user()->student;
        $institutionId = $student->institution_id ?? null;

        if (!$institutionId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student does not belong to any institution.',
            ], 400);
        }
        // quizes inside enrolled courses created by trainers of the student's institution
        $courseIds = $student->enrollments()->pluck('course_id');

        $quizzes = CourseQuize::whereHas('module', function ($query) use ($courseIds) {
            $query->whereIn('course_id', $courseIds);
        })
            ->whereHas('createdBy', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })
            ->with('module.course', 'enrollmentQuiz')
            ->latest()
            ->get();

        $quizzes = $quizzes->map(function ($quiz) {
                return [
                    'quiz_id' => $quiz->id,
                    'course_id' => $quiz->module->course->id,
                    'enrollment_quiz_id' => $quiz->enrollmentQuiz->id ?? null,
                    'course_name' => $quiz->module->course->name,
                    'module_id' => $quiz->module->id,
                    'module_name' => $quiz->module->title,
                    'questions' => $quiz->questions,
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Quizzes fetched successfully',
            'data' => $quizzes,
        ], 200);
    }

    public function getEvaluatedQuizzes()
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $evaluatedSubmissions = QuizSubmission::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->whereIn('status', ['passed', 'failed'])
            ->with(['quiz', 'course', 'media'])
            ->latest()
            ->get();

        if ($evaluatedSubmissions->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No evaluated quizzes found.',
                'data' => [],
            ], 200);
        }

        $formatted = $evaluatedSubmissions->map(function ($submission) {
            return [
                'submission_id' => $submission->id,
                'quiz_title' => $submission->quiz->title ?? null,
                'questions' => $submission->quiz->questions ?? null,
                'status'        => $submission->status,
                'review_comments' => $submission->review_comments,
                'submitted_at'  => $submission->created_at->toDateTimeString(),
                'evaluated_at'  => $submission->updated_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Evaluated quizzes retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }
}
