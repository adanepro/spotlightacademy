<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Expert;
use App\Models\Institution;
use App\Models\Student;
use App\Models\Trainer;
use Illuminate\Http\Request;

use function Pest\Laravel\options;

class AdminDashBoardController extends Controller
{
    public function getStatusOverview()
    {
        $institutionCount = Institution::count();
        $trainerCount = Trainer::count();
        $expertCount = Expert::count();
        $studentCount = Student::count();
        $courseCount = Course::count();
        $maleStudents = Student::whereHas('user', function ($query) {
            $query->where('gender', 'male');
        })->count();
        $femaleStudents = Student::whereHas('user', function ($query) {
            $query->where('gender', 'female');
        })->count();
        // count male and female students on each institution
        $institutions = Institution::withCount(['students as male_students_count' => function ($query) {
            $query->whereHas('user', function ($query) {
                $query->where('gender', 'male');
            });
        }, 'students as female_students_count' => function ($query) {
            $query->whereHas('user', function ($query) {
                $query->where('gender', 'female');
            });
        }])->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Status overview fetched successfully',
            'data' => [
                'institution_count' => $institutionCount,
                'trainer_count' => $trainerCount,
                'expert_count' => $expertCount,
                'student_count' => $studentCount,
                'course_count' => $courseCount,
                'total_male_students' => $maleStudents,
                'total_female_students' => $femaleStudents,
                'institutions' => $institutions->map(function ($institution) {
                    return [
                        'institution_id' => $institution->id,
                        'name' => $institution->name,
                        'male_students_count' => $institution->male_students_count,
                        'female_students_count' => $institution->female_students_count,
                    ];
                }),
            ],
        ], 200);
    }

    public function getLatestCourses()
    {
        $courses = Course::with('expert', 'trainers')->latest()->take(5)->get();

        $formattedCourses = $courses->map(function ($course) {
            return [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'expert_id' => $course->expert_id,
                'expert_name' => $course->expert->user->full_name ?? null,
                'trainers' => $course->trainers->map(function ($trainer) {
                    return [
                        'trainer_id' => $trainer->id ?? null,
                        'user_id' => $trainer->user->id ?? null,
                        'full_name' => $trainer->user->full_name,
                    ];
                }),
                'trainer_count' => optional($course->trainers)->count() ?? 0,
                // 'student_count' => optional($course->students)->count() ?? 0,
                'description' => $course->description,
                'status' => $course->status,
                'course_image' => $course->course_image,
                'course_trailer' => $course->course_trailer,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Latest courses fetched successfully',
            'data' => $formattedCourses,
        ], 200);
    }
}
