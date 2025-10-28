<?php

namespace App\Http\Controllers\ExpertControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpertDashboardController extends Controller
{
    public function getStatusOverview()
    {
        $assignedCourseCount = Auth::user()->expert->courses()->count();
        //count projects inside assigned courses
        //count quizes inside assigned courses
        //count exames inside assigned courses

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
