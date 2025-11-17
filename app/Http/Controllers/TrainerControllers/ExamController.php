<?php

namespace App\Http\Controllers\TrainerControllers;

use App\Http\Controllers\NotificationController;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamController extends NotificationController
{
    public function index()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $exams = Auth::user()->trainer->courses->map(function ($course) {
            return $course->exams;
        })->flatten();

        return response()->json([
            'status' => 'success',
            'message' => 'Exams fetched successfully',
            'data' => $exams,
        ], 200);
    }

    // all exams created by the trainer
    public function allExams()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }
        $exams = Auth::user()->trainer->exams;

        $exams = Exam::where('created_by', Auth::user()->trainer->id)
            ->with(['course'])
            ->latest()
            ->get();
        $exams = $exams->map(function ($exam) {
            return [
                'exam_id' => $exam->id,
                'course_id' => $exam->course->id,
                'course_name' => $exam->course->name,
                'title' => $exam->title,
                'start_date' => $exam->start_date,
                'end_date' => $exam->end_date,
                'status' => $exam->status,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Exams fetched successfully',
            'data' => $exams,
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
            'for' => 'required|in:all,failed',
            'questions' => 'required|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'duration_minutes' => 'nullable|integer|min:1',
        ]);

        try {
            DB::beginTransaction();
            $exam = Exam::create([
                'course_id' => $course->id,
                'title' => $validated['title'],
                'for' => $validated['for'],
                'questions' => $validated['questions'],
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'duration_minutes' => $validated['duration_minutes'] ?? null,
            ]);
            DB::commit();
            // notification
            $users = User::whereHas('roles', function ($query) {
                $query->where('name', 'Student');
            })
                ->whereHas('student', function ($query) use ($trainerInstitutionId) {
                    $query->where('institution_id', $trainerInstitutionId);
                })
                ->get();

            $body = [
                'title' => 'New Exam',
                'body' => [
                    'message' => 'A new exam has been created for ' . $course->name . '.',
                    'title' => $exam->title,
                    'start_date' => $exam->start_date,
                    'end_date' => $exam->end_date,
                    'duration_minutes' => $exam->duration_minutes,
                ],
            ];

            foreach ($users as $user) {
                // if ($exam->for === 'failed' && $user->student->enrollments->where('course_id', $course->id)->first()->status !== 'failed') {
                //     continue;
                // }
                $this->notify($body, $user);
            }

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
            'questions' => 'sometimes|nullable|array',
            'start_date' => 'sometimes|nullable|date',
            'end_date' => 'sometimes|nullable|date|after:start_date',
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
                'enrollment_exam_id' => $submission->enrollmentExam->id,
                'exam_id' => $submission->exam->id,
                'student_id' => $submission->enrollment->student->id,
                'student_name' => $submission->enrollment->student->user->full_name,
                'exam_title' => $submission->exam->title,
                'course_name' => $submission->course->name ?? null,
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

    public function getEvaluatedExams()
    {
        // get all evaluated exams created by the trainer,
        // display course name, exam title, exam_strat_date, exam_end_date, faild_student_count, passed_student_count, total_student_count

        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $evaluatedExams = Exam::whereHas('submissions', function ($q) {
            $q->whereIn('status', ['passed', 'failed']);
        })
            ->where('created_by', $trainer->id)
            ->with(['course', 'submissions'])
            ->latest()
            ->get();

        if ($evaluatedExams->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'message' => 'No evaluated exams found.',
                'data' => [],
            ], 200);
        }

        $formatted = $evaluatedExams->map(function ($exam) {
            return [
                'exam_id' => $exam->id,
                'course_name' => $exam->course->name ?? null,
                'exam_title' => $exam->title,
                'start_date' => $exam->start_date,
                'end_date' => $exam->end_date,
                'failed_student_count' => $exam->submissions->where('status', 'failed')->count(),
                'passed_student_count' => $exam->submissions->where('status', 'passed')->count(),
                'total_student_count' => $exam->submissions->count(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Evaluated exams retrieved successfully.',
            'data' => $formatted,
        ], 200);
    }

    public function getEvaluatedExamDetails(Exam $exam)
    {
        // get all evaluated exams created by the trainer,
        // display course name, exam title, all students with their submission status, review comments, submission date
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

        $formatted = $submissions->map(function ($submission) {
            return [
                'exam_title' => $submission->exam->title,
                'submission_id' => $submission->id,
                'student_id' => $submission->enrollment->student->id,
                'student_name' => $submission->enrollment->student->user->full_name,
                'status' => $submission->status,
                'progress' => $submission->enrollment->progress,
                'review_comments' => $submission->review_commens,
                'submitted_at' => $submission->created_at->toDateTimeString(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Exam submissions fetched successfully.',
            'data' => $formatted,
        ], 200);

    }
}
