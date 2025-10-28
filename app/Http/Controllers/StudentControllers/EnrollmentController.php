<?php

namespace App\Http\Controllers\StudentControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentLecture;
use App\Models\EnrollmentLectureMaterial;
use App\Models\EnrollmentModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function enroll(Request $request)
    {
        $student = Auth::user()->student;
        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
        ]);

        $alreadyEnrolled = $student->enrollments()->where('course_id', $data['course_id'])->exists();

        if ($alreadyEnrolled) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already enrolled in this course.',
            ], 400);
        }

        try {
            DB::beginTransaction();
            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'course_id' => $data['course_id'],
                'started_at' => now(),
                'status' => 'in_progress',
                'progress' => 0,
            ]);

            $course = Course::with('modules.lectures.materials')->find($data['course_id']);
            foreach ($course->modules as $module) {
                $enrollmentModule = $enrollment->modules()->create([
                    'module_id' => $module->id,
                    'status' => 'in_progress',
                    'progress' => 0,
                ]);

                foreach ($module->lectures as $lecture) {
                    $enrollmentLecture = $enrollmentModule->lectures()->create([
                        'lecture_id' => $lecture->id,
                        'status' => 'in_progress',
                        'is_watched' => false,
                        'progress' => 0,
                    ]);

                    foreach ($lecture->materials as $material) {
                        $enrollmentLecture->materials()->create([
                            'lecture_material_id' => $material->id,
                            'is_downloaded' => false,
                            'is_viewed' => false,
                        ]);
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Enrollment failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Enrolled successfully.',
            'data' => $enrollment,
        ], 200);
    }

    public function watchLecture(Request $request, Enrollment $enrollment, EnrollmentLecture $enrollmentLecture)
    {
        
        $enrollmentLecture->update([
            'is_watched' => true,
            'progress' => 100,
            'completed_at' => now(),
            'status' => 'completed',
        ]);
        $this->markModuleAsCompleted($enrollmentLecture->enrollmentModule);
        $this->markEnrollmentAsCompleted($enrollment);

        return response()->json([
            'status' => 'success',
            'message' => 'Lecture watched successfully.',
            'data' => $enrollmentLecture,
        ], 200);
    }

    public function downloadMaterial(Request $request, Enrollment $enrollment, EnrollmentLectureMaterial $enrollmentLectureMaterial)
    {

        $enrollmentLectureMaterial->update([
            'is_downloaded' => true,
            
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Material download status updated successfully.',
            'data' => $enrollmentLectureMaterial,
        ], 200);
    }

    public function viewMaterial(Request $request, Enrollment $enrollment, EnrollmentLectureMaterial $enrollmentLectureMaterial)
    {

        $enrollmentLectureMaterial->update([
            'is_viewed' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Material view status updated successfully.',
            'data' => $enrollmentLectureMaterial,
        ], 200);
    }



    public function getProgress(Enrollment $enrollment)
    {
        $progress = $enrollment->calculateProgress();

        return response()->json([
            'status' => 'success',
            'message' => 'Progress retrieved successfully.',
            'data' => [
                'progress' => $progress,
            ],
        ], 200);
    }

    // if all lectures in a module are completed, all materials are viewed or downloaded, and all quizes are passed, and all projects are submitted, and all exams are passed, mark the module as completed
    public function markModuleAsCompleted(EnrollmentModule $enrollmentModule)
    {
        $allLecturesCompleted = $enrollmentModule->lectures()->where('status', 'completed')->count() === $enrollmentModule->lectures()->count();
        $allMaterialsViewedOrDownloaded = $enrollmentModule->lectures()->with('materials')->get()->every(function ($lecture) {
            return $lecture->materials->every(function ($material) {
                return $material->is_viewed || $material->is_downloaded;
            });
        });

        if ($allLecturesCompleted && $allMaterialsViewedOrDownloaded) {
            $enrollmentModule->update([
                'status' => 'completed',
                'completed_at' => now(),
                'progress' => 100,
            ]);
        }
    }

    public function markEnrollmentAsCompleted(Enrollment $enrollment)
    {
        $allModulesCompleted = $enrollment->modules()->where('status', 'completed')->count() === $enrollment->modules()->count();

        if ($allModulesCompleted) {
            $enrollment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'progress' => 100,
            ]);
        }
    }
}
