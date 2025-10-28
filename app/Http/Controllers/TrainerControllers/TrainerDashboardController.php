<?php

namespace App\Http\Controllers\TrainerControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
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
                return optional($module->quizes)->count() ?? 0;
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

    public function getAssignedCourses()
    {
        if (!Auth::user()->trainer) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. User is not a trainer.',
            ], 403);
        }
        $assignedCourses = Auth::user()->trainer->courses()->get();

        $formattedCourses = $assignedCourses->map(function ($course) {
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
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
                'total_quizes' => $course->modules->sum(function ($module) {
                    return $module->quizes->count();
                }),
                'total_exams' => $course->exams->count(),
                'total_projects' => $course->projects->count(),
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
        $course = $course->load(['modules.quizes', 'modules.lectures.materials']);

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
                        'quizes' => $module->quizes->map(function ($quiz) {
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
            ],
        ], 200);
    }
}
