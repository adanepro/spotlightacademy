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
use Spatie\Activitylog\Facades\Activity;

class EnrollmentController extends Controller
{
    /**
     * Enroll a student into a course with its full structure:
     * Modules → Lectures → Materials → Quizzes → Exam → Project
     */
    public function enroll(Request $request)
    {
        $student = Auth::user()->student;

        $institutionId = Auth::user()->student->institution_id;
        $data = $request->validate([
            'course_id' => 'required|uuid|exists:courses,id',
        ]);

        // Check if already enrolled
        if ($student->enrollments()->where('course_id', $data['course_id'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already enrolled in this course.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            /** Load full course structure */
            $course = Course::with([
                'modules.lectures.materials',
                'modules.quizzes',
                'projects',
                'exams',
            ])->findOrFail($data['course_id']);

            // Ensure course has content
            if ($course->modules->isEmpty()) {
                throw new \Exception('Course has no modules to enroll.');
            }


            /** @var Enrollment $enrollment */

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'course_id'  => $data['course_id'],
                'started_at' => now(),
                'status'     => 'in_progress',
                'progress'   => 0,
            ]);

            /* ------------------------------
             * ENROLL MODULES, LECTURES, MATERIALS
             * ------------------------------ */
            foreach ($course->modules as $module) {
                $enrollmentModule = $enrollment->modules()->create([
                    'enrollment_id' => $enrollment->id,
                    'module_id' => $module->id,
                    'status'    => 'not_started',
                    'progress'  => 0,
                ]);

                foreach ($module->lectures as $lecture) {
                    $enrollmentLecture = $enrollmentModule->lectures()->create([
                        'enrollment_module_id' => $enrollmentModule->id,
                        'lecture_id'   => $lecture->id,
                        'status'       => 'not_started',
                        'is_watched'   => false,
                        'progress'     => 0,
                    ]);

                    foreach ($lecture->materials as $material) {
                        $enrollmentLecture->materials()->create([
                            'enrollment_lecture_id' => $enrollmentLecture->id,
                            'lecture_material_id' => $material->id,
                            'is_viewed'           => false,
                            'is_downloaded'       => false,
                        ]);
                    }
                }

                $validQuizzes = $module->quizzes->filter(function ($quiz) use ($institutionId) {
                    return optional($quiz->createdBy)->institution_id === $institutionId;
                });

                /* ------------------------------
                 * ENROLL MODULE QUIZZES
                 * ------------------------------ */
                foreach ($validQuizzes as $quiz) {
                    $enrollment->quizzes()->create([
                        'enrollment_id' => $enrollment->id,
                        'quiz_id'      => $quiz->id,
                        'module_id'    => $module->id,
                        'status'       => 'not_started',
                        'progress'     => 0,
                        'started_at'   => null,
                        'completed_at' => null,
                    ]);
                }
            }


            /* ------------------------------
             * ENROLL COURSE EXAMS
             * ------------------------------ */

            $validExams = $course->exams->filter(function ($exam) use ($institutionId) {
                return optional($exam->createdBy)->institution_id === $institutionId;
            });

            foreach ($validExams as $exam) {
                $enrollment->exams()->create([
                    'enrollment_id' => $enrollment->id,
                    'exam_id'      => $exam->id,
                    'status'       => 'not_started',
                    'progress'     => 0,
                    'started_at'   => null,
                    'completed_at' => null,
                    'result'       => null,
                ]);
            }

            /* ------------------------------
             * ENROLL COURSE PROJECTS
             * ------------------------------ */

            $validProjects = $course->projects->filter(function ($project) use ($institutionId) {
                return optional($project->createdBy)->institution_id === $institutionId;
            });

            foreach ($validProjects as $project) {
                $enrollment->projects()->create([
                    'enrollment_id' => $enrollment->id,
                    'project_id'   => $project->id,
                    'status'       => 'not_started',
                    'progress'     => 0,
                    'started_at'   => null,
                    'completed_at' => null,
                ]);
            }

            DB::commit();

            activity()
                ->causedBy(Auth::user())
                ->performedOn($enrollment)
                ->withProperties([
                    'course' => $course->name,
                ])
                ->log("{$student->user->full_name} started learning {$course->name}");

            return response()->json([
                'status'  => 'success',
                'message' => 'Enrolled successfully.',
                'data'    => $enrollment,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Enrollment failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * My Enrollment
     */

    public function myEnrollment(Request $request)
    {
        $studen = Auth::user()->student;
        $enrollments = Enrollment::where('student_id', $studen->id)->get();
        $enrollments->load('course');
        //filter by status
        if ($request->has('status')) {
            $enrollments = $enrollments->where('status', $request->status);
        }
        $formattedData = $enrollments->map(function ($enrollment) {
            return [
                'enrollment_id' => $enrollment->id,
                'course_id' => $enrollment->course->id,
                'course_name' => $enrollment->course->name,
                'course_image' => $enrollment->course->course_image,
                'progress' => $enrollment->progress,
                'status' => $enrollment->status,
            ];
        });
        return response()->json([
            'status'  => 'success',
            'message' => 'Enrollments fetched successfully.',
            'data'    => $formattedData,
        ], 200);
    }


    /**
     * Mark lecture as watched.
     */
    public function watchLecture(Request $request, Enrollment $enrollment, EnrollmentLecture $enrollmentLecture)
    {
        $this->authorizeOwnership($enrollment, $enrollmentLecture->enrollmentModule->enrollment_id);

        $enrollmentLecture->update([
            'is_watched'   => true,
        ]);
        activity()
            ->causedBy(Auth::user())
            ->performedOn($enrollmentLecture)
            ->withProperties([
                'course' => $enrollment->course->name,
                'lecture' => $enrollmentLecture->lecture->title,
            ])
            ->log('lecture watched');

        $this->markLectureAsCompleted($enrollmentLecture);
        $this->markModuleAsCompleted($enrollmentLecture->enrollmentModule);
        $this->updateEnrollmentProgress($enrollment);

        return response()->json([
            'status'  => 'success',
            'message' => 'Lecture watched successfully.',
            'data'    => $enrollmentLecture,
        ], 200);
    }

    /**
     * View material.
     */
    public function viewMaterial(Request $request, Enrollment $enrollment, EnrollmentLectureMaterial $material)
    {
        $this->authorizeOwnership($enrollment, $material->enrollmentLecture->enrollmentModule->enrollment_id);

        $material->update(['is_viewed' => true]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($material)
            ->withProperties([
                'course' => $enrollment->course->name,
                'material' => $material->lectureMaterial->title,
            ])
            ->log('material viewed');

        return response()->json([
            'status'  => 'success',
            'message' => 'Material viewed successfully.',
            'data'    => $material,
        ], 200);
    }

    /**
     * Download material.
     */
    public function downloadMaterial(Request $request, Enrollment $enrollment, EnrollmentLectureMaterial $material)
    {
        $this->authorizeOwnership($enrollment, $material->enrollmentLecture->enrollmentModule->enrollment_id);

        $material->update(['is_downloaded' => true]);

        activity()
            ->causedBy(Auth::user())
            ->performedOn($material)
            ->withProperties([
                'course' => $enrollment->course->name,
                'material' => $material->lectureMaterial->title,
            ])
            ->log('material downloaded');

        return response()->json([
            'status'  => 'success',
            'message' => 'Material download status updated successfully.',
            'data'    => $material,
        ], 200);
    }

    /**
     * Get enrollment progress dynamically.
     */
    public function getProgress(Enrollment $enrollment)
    {
        $this->authorizeOwnership($enrollment);

        return response()->json([
            'status'  => 'success',
            'message' => 'Progress retrieved successfully.',
            'data'    => ['progress' => $enrollment->calculateProgress()],
        ], 200);
    }

    /* ========================================================
     *                HELPER METHODS
     * ======================================================== */

    public function markLectureAsCompleted(EnrollmentLecture $lecture)
    {
        $lecture->load('materials');
        $totalMaterials = $lecture->materials->count();
        $viewedCount = $lecture->materials->filter(fn($m) => $m->is_viewed || $m->is_downloaded)->count();

        // Calculate progress from materials (if exist)
        $materialProgress = $totalMaterials > 0
            ? ($viewedCount / $totalMaterials) * 100
            : 100; // if no materials, assume 100%

        // If video watched, give weight, e.g., 50% watch + 50% materials
        $lecture->progress = $lecture->is_watched
            ? min(100, ($materialProgress * 0.5) + 50)
            : $materialProgress * 0.5;

        // Mark completed if both watched and all materials done
        if ($lecture->is_watched && $materialProgress == 100) {
            $lecture->status = 'completed';
            $lecture->completed_at = now();
            $lecture->progress = 100;
        } else {
            $lecture->status = 'in_progress';
        }

        $lecture->save();
    }

    protected function markModuleAsCompleted(EnrollmentModule $module)
    {
        $lectures = $module->lectures;
        $lectureCount = $lectures->count();
        if ($lectureCount === 0) return;

        $averageProgress = $lectures->avg('progress');
        $module->progress = round($averageProgress, 2);

        if ($averageProgress == 100) {
            $module->status = 'completed';
            $module->completed_at = now();
        } else {
            $module->status = 'in_progress';
        }

        $module->save();
    }

    protected function updateEnrollmentProgress(Enrollment $enrollment)
    {
        $enrollment->update(['progress' => $enrollment->calculateProgress()]);
    }

    protected function authorizeOwnership(Enrollment $enrollment, $checkId = null)
    {
        $student = Auth::user()->student;

        if ($enrollment->student_id !== $student->id || ($checkId && $enrollment->id !== $checkId)) {
            abort(403, 'Unauthorized');
        }
    }
}
