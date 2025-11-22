<?php

namespace App\Http\Controllers\StudentControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\EnrollmentExam;
use App\Models\EnrollmentProject;
use App\Models\EnrollmentQuiz;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Project;
use App\Models\ProjectSubmission;
use App\Models\QuizSubmission;
use App\Models\User;
use App\Services\EnrollmentSyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentDashboardController extends Controller
{
    public function getStatusOverview()
    {
        // Implementation for student dashboard status overview
    }

    public function getEnrolledCourses()
    {
        // Implementation for student dashboard enrolled courses
    }

    public function courseIndex()
    {
        $user = Auth::user();

        if (!$user->student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a student.',
            ], 403);
        }
        $courses = Course::query();

        // only display published courses
        $courses = $courses->where('status', 'published');

        $courses = $courses->latest()->paginate(10);

        $formattedCourses = $courses->getCollection()->map(function ($course) {
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'description' => $course->description,
                'status' => $course->status,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
                'progress' => $course->enrollments()->where('student_id', Auth::user()->student->id)->first()->progress ?? 0,
                'is_started' => $course->enrollments()->where('student_id', Auth::user()->student->id)->exists(),
            ];
        });

        $paginatedCourses = $courses->toArray();
        $paginatedCourses['data'] = $formattedCourses->values()->all();
        return response()->json([
            'status' => 'success',
            'message' => 'Courses fetched successfully',
            'data' => $paginatedCourses,
        ], 200);
    }

    public function courseShow(Course $course)
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: User is not a student.',
            ], 403);
        }

        $institutionId = $student->institution_id;

        $enrollment = $student->enrollments()->where('course_id', $course->id)->first();

        // Auto sync enrollment if not exist
        app(EnrollmentSyncService::class)->sync($enrollment);

        //dd($enrollment);

        // Load course content and exam, project and quizzes should be those created by the trainer who belongs to the same institution as the student
        $course->load(
            [
                'modules.lectures.materials',
                'projects' => function ($query) use ($institutionId) {
                    $query->whereHas('createdBy', function ($query) use ($institutionId) {
                        $query->where('institution_id', $institutionId);
                    });
                    // ->whereBetween('start_date', [Carbon::now(), Carbon::now()->addMinute()]);
                },
                'exams' => function ($query) use ($institutionId) {
                    $query->whereHas('createdBy', function ($query) use ($institutionId) {
                        $query->where('institution_id', $institutionId);
                    });
                    // ->whereBetween('start_date', [Carbon::now(), Carbon::now()->addMinute()]);
                },
                'modules.quizzes' => function ($query) use ($institutionId) {
                    $query->whereHas('createdBy', function ($query) use ($institutionId) {
                        $query->where('institution_id', $institutionId);
                    });
                },
            ]
        );


        $enrollment = $student->enrollments()->where('course_id', $course->id)->first();

        $data = [
            'course_id' => $course->id,
            'course_name' => $course->name,
            'description' => $course->description,
            'course_image' => $course->course_image,
            'course_trailer' => $course->course_trailer,
            'status' => $course->status,
            'enrollment_id' => $enrollment?->id,
            'progress' => $enrollment?->progress ?? 0,
            'modules' => $course->modules->map(function ($module) use ($enrollment) {
                $enrollmentModule = $enrollment?->modules()->where('module_id', $module->id)->first();

                return [
                    'module_id' => $module->id,
                    'module_name' => $module->title ?? null,
                    'module_description' => $module->description ?? null,
                    'module_order' => $module->order ?? null,
                    'enrollment_module_id' => $enrollmentModule?->id,
                    'status' => $enrollmentModule?->status ?? 'not_started',
                    'progress' => $enrollmentModule?->progress ?? 0,
                    'lectures' => $module->lectures->map(function ($lecture) use ($enrollmentModule) {
                        $enrollmentLecture = $enrollmentModule?->lectures()->where('lecture_id', $lecture->id)->first();

                        return [
                            'lecture_id' => $lecture->id,
                            'lecture_name' => $lecture->title ?? null,
                            'lecture_order' => $lecture->order ?? null,
                            'enrollment_lecture_id' => $enrollmentLecture?->id,
                            'status' => $enrollmentLecture?->status ?? 'not_started',
                            'is_watched' => $enrollmentLecture?->is_watched ?? false,
                            'progress' => $enrollmentLecture?->progress ?? 0,
                            'lecture_video' => $lecture->lecture_video ?? null,
                            'materials' => $lecture->materials->map(function ($material) use ($enrollmentLecture) {
                                $enrollmentMaterial = $enrollmentLecture?->materials()->where('lecture_material_id', $material->id)->first();

                                return [
                                    'material_id' => $material->id,
                                    'material_name' => $material->title ?? null,
                                    'material_order' => $material->order ?? null,
                                    'enrollment_material_id' => $enrollmentMaterial?->id,
                                    'is_viewed' => $enrollmentMaterial?->is_viewed ?? false,
                                    'is_downloaded' => $enrollmentMaterial?->is_downloaded ?? false,
                                    'lecture_note' => $material->lecture_notes ?? null,
                                ];
                            }),
                        ];
                    }),
                    'quizzes' => $module->quizzes->map(function ($quiz) use ($enrollment) {
                        $enrollmentQuiz = $enrollment?->quizzes()->where('quiz_id', $quiz->id)->first();

                        return [
                            'quiz_id' => $quiz->id,
                            'questions' => $quiz->questions ?? null,
                            'enrollment_quiz_id' => $enrollmentQuiz?->id,
                            'status' => $enrollmentQuiz?->status ?? 'not_started',
                            'created_by' => $quiz->createdBy?->user->full_name ?? null,
                            'is_submitted' => $enrollmentQuiz?->submission ? true : false,
                        ];
                    }),
                ];
            }),
            'projects' => $course->projects->map(function ($project) use ($enrollment) {
                $enrollmentProject = $enrollment?->projects()->where('project_id', $project->id)->first();

                return [
                    'project_id' => $project->id,
                    'project_title' => $project->title ?? null,
                    'enrollment_project_id' => $enrollmentProject?->id,
                    'status' => $enrollmentProject?->status ?? 'not_started',
                    'project_description' => $project->description ?? null,
                    'created_by' => $project->createdBy?->user->full_name ?? null,
                    'is_submitted' => $enrollmentProject?->submission ? true : false,
                ];
            }),
            'exams' => $course->exams->map(function ($exam) use ($enrollment) {
                $enrollmentExam = $enrollment?->exams()->where('exam_id', $exam->id)->first();

                return [
                    'exam_id' => $exam->id,
                    'exam_title' => $exam->title ?? null,
                    'enrollment_exam_id' => $enrollmentExam?->id,
                    'status' => $enrollmentExam?->status ?? 'not_started',
                    'score' => $enrollmentExam?->score ?? null,
                    'created_by' => $exam->createdBy?->user->full_name ?? null,
                    'questions' => $exam->questions ?? null,
                    'is_submitted' => $enrollmentExam?->submission ? true : false,
                ];
            }),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Course details fetched successfully',
            'data' => $data,
        ], 200);
    }


    // all course the student is enrolled in progress average progress
    public function getCourseProgressAverage()
    {
        $student = Auth::user()->student;
        $enrollments = $student->enrollments;
        $totalCourses = $enrollments->count();
        $totalProgress = $enrollments->sum('progress');
        $averageProgress = $totalCourses > 0 ? $totalProgress / $totalCourses : 0;
        $averageProgress = round($averageProgress, 2);
        // unread notifications count
        $unreadNotifications = User::find(Auth::id())->unreadNotifications->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Course progress average fetched successfully',
            'data' => [
                'total_courses' => $totalCourses,
                'total_progress' => $totalProgress,
                'average_progress' => $averageProgress,
                'unread_notifications' => $unreadNotifications,
            ],
        ], 200);
    }

    public function getOverview()
    {
        $student = Auth::user()->student;

        if (! $student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only students can access this.',
            ], 403);
        }

        // 1. Get all enrollment_projects of the student
        $enrollmentProjects = EnrollmentProject::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->pluck('id');

        // 2. Get all project_ids assigned to this student
        $projectIds = EnrollmentProject::whereIn('id', $enrollmentProjects)
            ->pluck('project_id');

        // ============================
        // Upcoming Projects
        // ============================
        $upcomingProjects = Project::whereIn('id', $projectIds)
            ->where('status', 'upcoming')
            ->count();

        // ============================
        // Submitted Projects
        // ============================
        $submittedProjects = ProjectSubmission::whereIn('enrollment_project_id', $enrollmentProjects)
            ->where('status', 'submitted')
            ->count();

        // ============================
        // Failed Projects
        // ============================
        $failedProjects = ProjectSubmission::whereIn('enrollment_project_id', $enrollmentProjects)
            ->where('status', 'failed')
            ->count();

        // ============================
        // Passed Projects
        // ============================
        $passedProjects = ProjectSubmission::whereIn('enrollment_project_id', $enrollmentProjects)
            ->where('status', 'passed')
            ->count();

        // ============================
        // Evaluated Projects = passed + failed
        // ============================
        $evaluatedProjects = $passedProjects + $failedProjects;

        // 1. Get all enrollment_exams of the student
        $enrollmentExams = EnrollmentExam::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->pluck('id');

        // 2. Get all exam_ids assigned to this student
        $examIds = EnrollmentExam::whereIn('id', $enrollmentExams)
            ->pluck('exam_id');

        // ============================
        // Upcoming Exams
        // ============================
        $upcomingExams = Exam::whereIn('id', $examIds)
            ->where('status', 'upcoming')
            ->count();


        // ============================
        // Submitted Exams
        // ============================
        $submittedExams = ExamSubmission::whereIn('enrollment_exam_id', $enrollmentExams)
            ->where('status', 'submitted')
            ->count();

        // ============================
        // Failed Exams
        // ============================
        $failedExams = ExamSubmission::whereIn('enrollment_exam_id', $enrollmentExams)
            ->where('status', 'failed')
            ->count();

        // ============================
        // Passed Exams
        // ============================
        $passedExams = ExamSubmission::whereIn('enrollment_exam_id', $enrollmentExams)
            ->where('status', 'passed')
            ->count();

        // ============================
        // Evaluated Exams = passed + failed
        // ============================
        $evaluatedExams = $passedExams + $failedExams;

        // 1. Get all enrollment_quizzes of the student
        $enrollmentQuizzes = EnrollmentQuiz::whereHas('enrollment', function ($q) use ($student) {
            $q->where('student_id', $student->id);
        })->pluck('id');


        // 2. Get all quiz_ids assigned to this student
        $quizIds = EnrollmentQuiz::whereIn('id', $enrollmentQuizzes)
            ->pluck('quiz_id');

        // ============================
        // Submitted Quizzes
        // ============================
        $submittedQuizzes = QuizSubmission::whereIn('enrollment_quiz_id', $enrollmentQuizzes)
            ->where('status', 'submitted')
            ->count();

        // ============================
        // Failed Quizzes
        // ============================
        $failedQuizzes = QuizSubmission::whereIn('enrollment_quiz_id', $enrollmentQuizzes)
            ->where('status', 'failed')
            ->count();

        // ============================
        // Passed Quizzes
        // ============================
        $passedQuizzes = QuizSubmission::whereIn('enrollment_quiz_id', $enrollmentQuizzes)
            ->where('status', 'passed')
            ->count();

        // ============================
        // Evaluated Quizzes = passed + failed
        // ============================
        $evaluatedQuizzes = $passedQuizzes + $failedQuizzes;

        return response()->json([
            'status' => 'success',
            'message' => 'Student overview fetched successfully.',
            'data' => [
                'upcoming_projects' => $upcomingProjects,
                'submitted_projects' => $submittedProjects,
                'failed_projects' => $failedProjects,
                'passed_projects' => $passedProjects,
                'evaluated_projects' => $evaluatedProjects,

                'upcoming_exams' => $upcomingExams,
                'submitted_exams' => $submittedExams,
                'failed_exams' => $failedExams,
                'passed_exams' => $passedExams,
                'evaluated_exams' => $evaluatedExams,

                'submitted_quizzes' => $submittedQuizzes,
                'failed_quizzes' => $failedQuizzes,
                'passed_quizzes' => $passedQuizzes,
                'evaluated_quizzes' => $evaluatedQuizzes,
            ],
        ], 200);
    }

    public function getExamByCourseId($courseId)
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $enrollment = $student->enrollments()->where('course_id', $courseId)->first();

        $exams = Exam::where('course_id', $courseId)
            ->with(['createdBy.user'])
            ->get();

        $exams = $exams->map(function ($exam) use ($enrollment) {
            $enrollmentExam = $enrollment?->exams()->where('exam_id', $exam->id)->first();

            return [
                'exam_id' => $exam->id,
                'exam_title' => $exam->title ?? null,
                'enrollment_exam_id' => $enrollmentExam?->id,
                'status' => $enrollmentExam?->status ?? 'not_started',
                'created_by' => $exam->createdBy?->user->full_name ?? null,
                'questions' => $exam->questions ?? null,
                'is_submitted' => $enrollmentExam?->submission ? true : false,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Exams fetched successfully',
            'data' => $exams,
        ], 200);
    }

    public function getProjectByCourseId($courseId)
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $enrollment = $student->enrollments()->where('course_id', $courseId)->first();

        $projects = Project::where('course_id', $courseId)
            ->with(['createdBy.user'])
            ->get();

        $projects = $projects->map(function ($project) use ($enrollment) {
            $enrollmentProject = $enrollment?->projects()->where('project_id', $project->id)->first();

            return [
                'project_id' => $project->id,
                'project_title' => $project->title ?? null,
                'enrollment_project_id' => $enrollmentProject?->id,
                'status' => $enrollmentProject?->status ?? 'not_started',
                'project_description' => $project->description ?? null,
                'created_by' => $project->createdBy?->user->full_name ?? null,
                'is_submitted' => $enrollmentProject?->submission ? true : false,
            ];
        });
        return response()->json([
            'status' => 'success',
            'message' => 'Projects fetched successfully',
            'data' => $projects,
        ], 200);
    }
}
