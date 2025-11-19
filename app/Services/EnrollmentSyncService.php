<?php

namespace App\Services;

use App\Models\Enrollment;

class EnrollmentSyncService
{
    public function sync(Enrollment $enrollment)
    {
        // Sync modules, lectures, materials, quizzes, exams, projects
        $course = $enrollment->course()->with([
            'modules.lectures.materials',
            'modules.quizzes',
            'projects',
            'exams',
        ])->first();

        /* sync modules */
        foreach ($course->modules as $module) {
            $enrollment->modules()->updateOrCreate([
                'module_id' => $module->id,
            ], [
                'status' => 'not_started',
                'progress' => 0,
            ]);

            /* sync lectures */
            foreach ($module->lectures as $lecture) {
                $enrollment->modules()->where('module_id', $module->id)->first()->lectures()->updateOrCreate([
                    'lecture_id' => $lecture->id,
                ], [
                    'status' => 'not_started',
                    'is_watched' => false,
                    'progress' => 0,
                ]);

                /* sync materials */
                foreach ($lecture->materials as $material) {
                    $enrollment->modules()->where('module_id', $module->id)->first()->lectures()->where('lecture_id', $lecture->id)->first()->materials()->updateOrCreate([
                        'lecture_material_id' => $material->id,
                    ], [
                        'is_viewed' => false,
                        'is_downloaded' => false,
                    ]);
                }
            }

            /* sync quizzes */
            foreach ($module->quizzes as $quiz) {
                $enrollment->quizzes()->updateOrCreate([
                    'quiz_id' => $quiz->id,
                ], [
                    'status' => 'not_started',
                    'progress' => 0,
                ]);
            }
        }

        /* sync projects */
        foreach ($course->projects as $project) {
            $enrollment->projects()->updateOrCreate([
                'project_id' => $project->id,
            ], [
                'status' => 'not_started',
                'progress' => 0,
            ]);
        }

        /* sync exams */
        foreach ($course->exams as $exam) {
            $enrollment->exams()->updateOrCreate([
                'exam_id' => $exam->id,
            ], [
                'status' => 'not_started',
                'progress' => 0,
                
            ]);
        }
    }
}
