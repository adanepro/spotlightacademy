<?php

namespace App\Http\Controllers\ExpertControllers;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lecture;
use App\Models\LectureMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CourseContentWizardController extends Controller
{
    public function storeWizard(Request $request, Course $course)
    {

        if (!Auth::user()->expert || $course->expert_id !== Auth::user()->expert->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized access. You are not assigned to this course.',
            ], 403);
        }

        $validated = $request->validate([
            'modules' => 'required|array|min:1',
            'modules.*.title' => 'required|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.order' => 'required|integer|min:1',

            'modules.*.lectures' => 'required|array|min:1',
            'modules.*.lectures.*.title' => 'required|string|max:255',
            'modules.*.lectures.*.order' => 'required|integer|min:1',
            'modules.*.lectures.*.lecture_video' => 'nullable|file|mimetypes:video/mp4,video/mpeg|max:51200',

            'modules.*.lectures.*.materials' => 'nullable|array',
            'modules.*.lectures.*.materials.*.title' => 'required|string|max:255',
            'modules.*.lectures.*.materials.*.order' => 'required|integer|min:1',
            'modules.*.lectures.*.materials.*.lecture_notes' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx|max:10240',
            'status' => 'required|in:draft,published,archived',
        ]);

        try {
            DB::beginTransaction();

            $savedModules = [];

            foreach ($validated['modules'] as $moduleData) {

                $module = Module::create([
                    'course_id' => $course->id,
                    'title' => $moduleData['title'],
                    'description' => $moduleData['description'] ?? null,
                    'order' => $moduleData['order'],
                ]);

                foreach ($moduleData['lectures'] as $lectureData) {
                    $lecture = Lecture::create([
                        'module_id' => $module->id,
                        'title' => $lectureData['title'],
                        'order' => $lectureData['order'],
                    ]);

                    if (isset($lectureData['lecture_video']) && $lectureData['lecture_video'] instanceof \Illuminate\Http\UploadedFile) {
                        $lecture->addMedia($lectureData['lecture_video'])->toMediaCollection('lecture_video');
                    }

                    if (!empty($lectureData['materials'])) {
                        foreach ($lectureData['materials'] as $materialData) {
                            $material = LectureMaterial::create([
                                'lecture_id' => $lecture->id,
                                'title' => $materialData['title'],
                                'order' => $materialData['order'],
                            ]);

                            if (isset($materialData['lecture_notes']) && $materialData['lecture_notes'] instanceof \Illuminate\Http\UploadedFile) {
                                $material->addMedia($materialData['lecture_notes'])->toMediaCollection('lecture_notes');
                            }
                        }
                    }
                }

                $course->update(['status' => $validated['status']]);

                $savedModules[] = $module->load('course', 'lectures.materials');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Course content (modules, lectures, materials) created successfully.',
                'data' => $savedModules,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create course content: ' . $e->getMessage(),
            ], 500);
        }
    }
}
