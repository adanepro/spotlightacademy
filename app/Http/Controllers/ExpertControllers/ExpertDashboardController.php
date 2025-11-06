<?php

namespace App\Http\Controllers\ExpertControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseQuize;
use App\Models\Exam;
use App\Models\Module;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpertDashboardController extends Controller
{
    public function getStatusOverview()
    {
        if (!Auth::user()->expert) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not an expert.',
            ], 403);
        }

        $expert = Auth::user()->expert;

        // get course content count
        $modulesCount = $expert->courses->sum(function ($course) {
            return $course->modules->count();
        });
        $lecturesCount = $expert->courses->sum(function ($course) {
            return $course->modules->sum(function ($module) {
                return $module->lectures->count();
            });
        });
        $materialsCount = $expert->courses->sum(function ($course) {
            return $course->modules->sum(function ($module) {
                return $module->lectures->sum(function ($lecture) {
                    return $lecture->materials->count();
                });
            });
        });
        $quizzesCount = $expert->courses->sum(function ($course) {
            return $course->modules->sum(function ($module) {
                return $module->quizzes->count();
            });
        });

        // Total counts
        $totalExamsCount = $expert->courses->sum(function ($course) {
            return $course->exams->count();
        });

        $totalProjectsCount = $expert->courses->sum(function ($course) {
            return $course->projects->count();
        });

        // Get all course IDs for the expert
        $courseIds = $expert->courses->pluck('id');

        // Get all exams created by trainers for these courses
        $examsGroupedByTrainer = Exam::whereIn('course_id', $courseIds)
            ->whereNotNull('created_by')
            ->with('createdBy.user')
            ->get()
            ->groupBy('created_by');

        $examsCreatedByTrainer = $examsGroupedByTrainer->map(function ($exams, $trainerId) {
            $trainer = $exams->first()->createdBy;
            return [
                'trainer_id' => $trainerId,
                'institution_id' => $trainer->institution_id ?? null,
                'institution_name' => $trainer->institution->name ?? null,
                'trainer_name' => $trainer->user->full_name ?? 'Unknown',
                'count' => $exams->count(),
            ];
        })->values();

        // Get all projects created by trainers for these courses
        $projectsGroupedByTrainer = Project::whereIn('course_id', $courseIds)
            ->whereNotNull('created_by')
            ->with('createdBy.user')
            ->get()
            ->groupBy('created_by');

        $projectsCreatedByTrainer = $projectsGroupedByTrainer->map(function ($projects, $trainerId) {
            $trainer = $projects->first()->createdBy;
            return [
                'trainer_id' => $trainerId,
                'institution_id' => $trainer->institution_id ?? null,
                'institution_name' => $trainer->institution->name ?? null,
                'trainer_name' => $trainer->user->full_name ?? 'Unknown',
                'count' => $projects->count(),
            ];
        })->values();

        // Get all quizzes created by trainers for modules in these courses
        $moduleIds = Module::whereIn('course_id', $courseIds)->pluck('id');

        $quizzesGroupedByTrainer = CourseQuize::whereIn('module_id', $moduleIds)
            ->whereNotNull('created_by')
            ->with('createdBy.user')
            ->get()
            ->groupBy('created_by');

        $quizzesCreatedByTrainer = $quizzesGroupedByTrainer->map(function ($quizzes, $trainerId) {
            $trainer = $quizzes->first()->createdBy;
            return [
                'trainer_id' => $trainerId,
                'trainer_name' => $trainer->user->full_name ?? 'Unknown',
                'institution_id' => $trainer->institution_id ?? null,
                'institution_name' => $trainer->institution->name ?? null,
                'count' => $quizzes->count(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Status overview fetched successfully',
            'data' => [
                'modules_count' => $modulesCount,
                'lectures_count' => $lecturesCount,
                'materials_count' => $materialsCount,
                'quizzes_count' => $quizzesCount,
                'total_exams_count' => $totalExamsCount,
                'total_projects_count' => $totalProjectsCount,
                'exams_created_by_trainer' => $examsCreatedByTrainer,
                'projects_created_by_trainer' => $projectsCreatedByTrainer,
                'quizzes_created_by_trainer' => $quizzesCreatedByTrainer,
            ],
        ], 200);
    }

    public function getAssignedCourses()
    {
        $assignedCourses = Auth::user()->expert->courses()->get();

        $formattedCourses = $assignedCourses->map(function ($course) {
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
                'trainers_count' => $course->trainers->count(),
                'status' => $course->status,
                'modules_created' => $course->modules->count(),
                'total_video_lectures' => $course->modules->sum(function ($module) {
                    return $module->lectures->count();
                }),
                'total_lecture_notes' => $course->modules->sum(function ($module) {
                    return $module->lectures->sum(function ($lecture) {
                        return $lecture->materials->count();
                    });
                }),
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
        $course = $course->load(['modules.lectures.materials']);

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
            ],
        ], 200);
    }
}
