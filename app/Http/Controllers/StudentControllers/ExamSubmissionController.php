<?php

namespace App\Http\Controllers\StudentControllers;

use App\Http\Controllers\NotificationController;
use App\Models\ExamSubmission;
use App\Models\EnrollmentExam;
use App\Models\Exam;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamSubmissionController extends NotificationController
{
    /**
     * Submit an exam for a student (file upload or link).
     */
    public function submit(Request $request, EnrollmentExam $enrollmentExam)
    {
        // no redundent submission
        $existingSubmission = ExamSubmission::where('enrollment_exam_id', $enrollmentExam->id)->first();
        if ($existingSubmission) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already submitted this exam.',
            ], 400);
        }
        $student = Auth::user()->student;
        $exam = $enrollmentExam->exam;

        $data = $request->validate([
            'answers' => 'nullable|array',
            'answers.*.answer' => 'nullable|string',
            'exam_file' => 'nullable|file|max:20480', // up to 20MB
            'link' => 'nullable|url',
        ]);

        try {
            DB::beginTransaction();

            $enrollmentExam = EnrollmentExam::where('id', $enrollmentExam->id)
                ->whereHas('enrollment', function ($q) use ($student) {
                    $q->where('student_id', $student->id);
                })
                ->firstOrFail();

            // Prevent duplicate submission
            if ($enrollmentExam->status !== 'not_started' && $enrollmentExam->status !== 'in_progress') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already submitted or completed this exam.',
                ], 400);
            }

            // Create exam submission record
            $examSubmission = ExamSubmission::create([
                'enrollment_exam_id' => $enrollmentExam->id,
                'exam_id' => $enrollmentExam->exam_id,
                'enrollment_id' => $enrollmentExam->enrollment_id,
                'course_id' => $enrollmentExam->exam->course_id,
                'status' => 'submitted',
                'answers' => $data['answers'] ?? null,
                'review_comments' => null,
                'link' => $data['link'] ?? null,
            ]);

            // Handle file upload if provided
            if ($request->hasFile('exam_file')) {
                $examSubmission
                    ->addMediaFromRequest('exam_file')
                    ->toMediaCollection('exam_file');
            }

            // Update enrollment exam status
            $enrollmentExam->update([
                'status' => 'in_progress',
                'started_at' => $enrollmentExam->started_at ?? now(),
            ]);

            DB::commit();

            activity()
                ->causedBy(Auth::user())
                ->performedOn($examSubmission)
                ->withProperties([
                    'course_name' => $enrollmentExam->enrollment->course->name,
                    'course_id' => $enrollmentExam->enrollment->course->id,
                    'exam' => $exam->title,
                    'exam_id' => $exam->id,
                    'student_id' => $student->id,
                    'student_name' => $student->user->full_name,
                ])
                ->log("{student_name} submitted exam {exam} for review");

            // notification
            if ($exam->createdBy && $exam->createdBy->user) {
                $trainerUser = $exam->createdBy->user;

                $body = [
                    'title' => 'Exam Submission',
                    'body' => [
                        'message' => $student->user->full_name . ' has submitted an exam titled "' . $exam->title . '" for review.',
                    ],
                ];

                $this->notify($body, $trainerUser);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Exam submitted successfully.',
                'data' => $examSubmission->load('media'),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Exam submission failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a student's exam submissions.
     */
    public function index()
    {
        $student = Auth::user()->student;

        $submissions = ExamSubmission::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->with(['exam', 'course', 'enrollmentExam'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Exam submissions fetched successfully.',
            'data' => $submissions,
        ], 200);
    }

    /**
     * Show a single exam submission.
     */

    public function show(ExamSubmission $examSubmission)
    {
        $student = Auth::user()->student;

        if ($examSubmission->enrollment->student_id !== $student->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: You cannot view this submission.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Exam submission details fetched successfully.',
            'data' => $examSubmission->load(['exam', 'course', 'media']),
        ], 200);
    }

    public function allExams(Request $request)
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

        $query = Exam::whereIn('course_id', $courseIds)
            ->whereHas('createdBy', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            });
            // ->whereBetween('start_date', [Carbon::now(), Carbon::now()->addMinute()]);

        // if (in_array($status, ['upcoming', 'ongoing', 'closed'])) {
        //     $query->where('status', $status);

        //     if ($status === 'upcoming') {
        //         $query->whereBetween('start_date', [Carbon::now(), Carbon::now()->addMinute()]);
        //     }
        // }

        $exams = $query->latest()->get();

        $formatedExams = $exams->map(function ($exam) use ($student) {
            $enrollmentExam = $exam->enrollmentExams()
                ->where('enrollment_id', $student->enrollments()->first()->id ?? null)
                ->first();
            return [
                'exam_id' => $exam->id,
                'exam_enrollment_id' => $enrollmentExam?->id ?? null,
                'title' => $exam->title,
                'questions' => $exam->questions,
                'start_date' => $exam->start_date,
                'end_date' => $exam->end_date,
                'duration' => $exam->duration_minutes,
                'remedial_of' => $enrollmentExam?->remedial_of ?? null,
                'resubmission_count' => $enrollmentExam->submission->resubmission_count ?? 0,
                'status' => $exam->status,
                'is_submitted' => $enrollmentExam?->submission ? true : false,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Exam retrived successfully',
            'filter' => $status ?? 'all',
            'data' => $formatedExams,
        ], 200);
    }


    public function getEvaluatedExams()
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $evaluatedSubmissions = ExamSubmission::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->whereIn('status', ['passed', 'failed'])
            ->with(['exam', 'course', 'media'])
            ->latest()
            ->get();

        if ($evaluatedSubmissions->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No evaluated Exams found.',
                'data' => [],
            ], 200);
        }

        $formatted = $evaluatedSubmissions->map(function ($submission) {
            return [
                'submission_id' => $submission->id,
                'exam_title' => $submission->exam->title ?? null,
                'questions' => $submission->exam->questions ?? null,
                'status'        => $submission->status,
                'review_comments' => $submission->review_comments,
                'dadeline'       => $submission->exam->end_date,
                'submitted_at'  => $submission->created_at->toDateTimeString(),
                'evaluated_at'  => $submission->updated_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Evaluated exams retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    public function getRemedialExams()
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $remedialExams = EnrollmentExam::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })
            ->where('remedial_of', '!=', null)
            ->where('status', '!=', 'completed')
            ->whereHas('exam', function ($query) {
                $query->whereBetween('start_date', [Carbon::now(), Carbon::now()->addMinute()]);
            })
            ->with(['exam', 'course'])
            ->latest()
            ->get();

        if ($remedialExams->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No remedial exams found.',
                'data' => [],
            ], 200);
        }

        $formatted = $remedialExams->map(function ($exam) {
            return [
                'exam_id' => $exam->id,
                'exam_title' => $exam->exam->title ?? null,
                'questions' => $exam->exam->questions ?? null,
                'remedial_of' => $exam->remedial_of,
                'status' => $exam->status,
                'start_date' => $exam->exam->start_date,
                'end_date' => $exam->exam->end_date,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Remedial exams retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }
}
