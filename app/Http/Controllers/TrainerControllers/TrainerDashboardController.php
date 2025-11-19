<?php

namespace App\Http\Controllers\TrainerControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class TrainerDashboardController extends Controller
{
    public function getStatusOverview()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $trainer = Auth::user()->trainer;

        $assignedCourseCount = $trainer->courses()->count();

        $projectsCount = $trainer->courses->sum(function ($course) {
            return optional($course->projects)->count() ?? 0;
        });

        $quizzesCount = $trainer->courses->sum(function ($course) {
            return optional($course->modules)->sum(function ($module) {
                return optional($module->quizzes)->count() ?? 0;
            }) ?? 0;
        });

        $examsCount = $trainer->courses->sum(function ($course) {
            return optional($course->exams)->count() ?? 0;
        });
        return response()->json([
            'status' => 'success',
            'message' => 'Status overview fetched successfully',
            'data' => [
                'assigned_course_count' => $assignedCourseCount,
                'projects_count' => $projectsCount,
                'quizzes_count' => $quizzesCount,
                'exams_count' => $examsCount,
            ],
        ], 200);
    }

    public function getAssessmentsOveriew()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $trainer = Auth::user()->trainer;
        //pending projects for evaluation
        $pendingProjects = Project::where('created_by', $trainer->id)
            ->whereHas('submissions', function ($q) {
                $q->where('status', 'submitted');
            })
            ->count();
        //pending exams for evaluation
        $pendingExams = Exam::where('created_by', $trainer->id)
            ->whereHas('submissions', function ($q) {
                $q->where('status', 'submitted');
            })
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Assessments overview fetched successfully',
            'data' => [
                'pending_projects' => $pendingProjects,
                'pending_exams' => $pendingExams,
            ],
        ], 200);
    }
    public function getAssignedCourses()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }

        $assignedCourses = Auth::user()->trainer->courses()->where('status', 'published')->get();

        $formattedCourses = $assignedCourses->map(function ($course) {
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
                'status' => $course->status,
                'modules_created' => $course->modules->count(),
                'total_video_lectures' => $course->modules->sum(function ($module) {
                    return optional($module->lectures)->count() ?? 0;
                    // return $module->lectures->count();
                }),
                'total_lecture_notes' => $course->modules->sum(function ($module) {
                    return $module->lectures->sum(function ($lecture) {
                        return optional($lecture->materials)->count() ?? 0;
                        //return $lecture->materials->count();
                    });
                }),
                'total_quizzes' => $course->modules->sum(function ($module) {
                    return optional($module->quizzes)->count() ?? 0;
                    //return $module->quizzes->count();
                }),
                'total_exams' => optional($course->exams)->count() ?? 0,
                // 'total_exams' =>     $course->exams->count(),
                'total_projects' => optional($course->projects)->count() ?? 0,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Assigned courses fetched successfully',
            'data' => $formattedCourses,
        ], 200);
    }

    public function show(Course $course)
    {
        $trainerId = Auth::user()->trainer->id;
        $course = $course->load(['modules.quizzes', 'modules.lectures.materials', 'projects', 'exams']);

        $projects = $course->projects()->where('created_by', $trainerId)->get();
        $exams = $course->exams()->where('created_by', $trainerId)->get();
        $quizzes = $course->modules->flatMap(function ($module) use ($trainerId) {
            return $module->quizzes->where('created_by', $trainerId);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Course details fetched successfully',
            'data' => [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'description' => $course->description,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
                'status' => $course->status,
                'modules' => $course->modules->map(function ($module) {
                    return [
                        'module_id' => $module->id,
                        'title' => $module->title,
                        'description' => $module->description,
                        'order' => $module->order,
                        'quizzes' => $module->quizzes->map(function ($quiz) {
                            return [
                                'quiz_id' => $quiz->id,
                                'questions' => $quiz->questions,
                                'start_date' => $quiz->start_date,
                                'end_date' => $quiz->end_date,
                            ];
                        }),
                        'lectures' => $module->lectures->map(function ($lecture) {
                            return [
                                'lecture_id' => $lecture->id,
                                'title' => $lecture->title,
                                'order' => $lecture->order,
                                'lecture_video' => $lecture->lecture_video,
                                'materials' => $lecture->materials->map(function ($material) {
                                    return [
                                        'material_id' => $material->id,
                                        'title' => $material->title,
                                        'order' => $material->order,
                                        'lecture_note' => $material->lecture_notes,
                                    ];
                                }),
                            ];
                        }),
                    ];
                }),

                'projects' => $projects->map(function ($project) {
                    return [
                        'project_id' => $project->id,
                        'title' => $project->title,
                        'description' => $project->description,
                        'start_date' => $project->start_date,
                        'end_date' => $project->end_date,
                    ];
                }),

                'exams' => $exams->map(function ($exam) {
                    return [
                        'exam_id' => $exam->id,
                        'title' => $exam->title,
                        'questions' => $exam->questions,
                        'start_date' => $exam->start_date,
                        'end_date' => $exam->end_date,
                        'duration_minutes' => $exam->duration_minutes,
                    ];
                }),
            ],
        ], 200);
    }

    public function getSchedule()
    {
       // get shedule of project and exam
       $trainer = Auth::user()->trainer;
       $projects = Project::where('created_by', $trainer->id)
           ->whereBetween('start_date', [Carbon::now(), Carbon::now()->addWeek()])
           ->get();
       $exams = Exam::where('created_by', $trainer->id)
           ->whereBetween('start_date', [Carbon::now(), Carbon::now()->addWeek()])
           ->get();

       $schedule = collect();

       foreach ($projects as $p) {
           $schedule->push([
               'type' => 'project',
               'id' => $p->id,
               'title' => $p->title,
               'start_date' => $p->start_date,
               'end_date' => $p->end_date,
           ]);
       }

       foreach ($exams as $e) {
           $schedule->push([
               'type' => 'exam',
               'id' => $e->id,
               'title' => $e->title,
               'start_date' => $e->start_date,
               'end_date' => $e->end_date,
           ]);
       }

       return response()->json([
           'status' => 'success',
           'message' => 'Schedule retrieved successfully.',
           'data' => $schedule,
       ], 200);
    }

    public function getStudentProgressPerCoursePerStudent()
    {
        // student, course, progress, submitted_exam_count, submitted_project_count
        // for each course
        $trainer = Auth::user()->trainer;
        $enrollments = $trainer->courses->flatMap->enrollments;

        $students = $enrollments->map(function ($enrollment) {
            return [
                'student_id' => $enrollment->student->id,
                'student_name' => $enrollment->student->user->full_name,
                'course_id' => $enrollment->course->id,
                'course_name' => $enrollment->course->name,
                'progress' => $enrollment->progress,
                'submitted_exam_count' => $enrollment->examSubmissions->count(),
                'submitted_project_count' => $enrollment->projectSubmissions->count(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Student progress retrieved successfully.',
            'data' => $students,
        ], 200);
    }


    public function getTotalProgressPerStudent()
    {
       // get total progress of all students for all courses per student

       $trainer = Auth::user()->trainer;
       $enrollments = $trainer->courses->flatMap->enrollments;

       $students = $enrollments->map(function ($enrollment) {
           return [
               'student_id' => $enrollment->student->id,
               'student_name' => $enrollment->student->user->full_name,
               'progress' => $enrollment->progress,
           ];
       });

       return response()->json([
           'status' => 'success',
           'message' => 'Total progress retrieved successfully.',
           'data' => $students,
       ], 200);


    }
}
