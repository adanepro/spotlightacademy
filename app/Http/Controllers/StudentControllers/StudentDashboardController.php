<?php

namespace App\Http\Controllers\StudentControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;

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

    public function courseIndex(Course $course)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Course details fetched successfully',
            'data' => [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'description' => $course->description,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
                'progress' => 0,
                'status' => $course->status,
            ],
        ], 200);
    }
}
