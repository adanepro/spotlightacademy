<?php

namespace App\Http\Controllers\StudentControllers;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Get all upcoming or ongoing exams and projects
     * for a student in the same institution as trainers.
     */
    public function getSchedule(Request $request)
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Only students can access this resource.',
            ], 403);
        }

        $institutionId = $student->institution_id;

        if (!$institutionId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student does not belong to any institution.',
            ], 400);
        }

        $now = now();

        // --- Query parameters ---
        $type = $request->query('type', 'all'); // exam | project | all
        $status = $request->query('status', 'all'); // upcoming | ongoing | completed | all
        $filterDate = $request->query('date'); // YYYY-MM-DD (optional)

        // Get all enrolled course IDs
        $enrolledCourseIds = $student->enrollments()->pluck('course_id');

        $schedule = collect();

        // --- Fetch Projects ---
        if ($type === 'project' || $type === 'all') {
            $projects = Project::whereIn('course_id', $enrolledCourseIds)
                ->whereHas('createdBy', function ($q) use ($institutionId) {
                    $q->where('institution_id', $institutionId);
                })
                ->when($status !== 'all', function ($q) use ($status) {
                    $q->where('status', $status);
                })
                ->when($filterDate, function ($q) use ($filterDate) {
                    $q->whereDate('start_date', '<=', $filterDate)
                        ->whereDate('end_date', '>=', $filterDate);
                })
                ->select('id', 'title', 'start_date', 'end_date', 'status', 'course_id')
                ->get();

            foreach ($projects as $p) {
                $schedule->push([
                    'type' => 'project',
                    'id' => $p->id,
                    'title' => $p->title,
                    'start_date' => $p->start_date,
                    'end_date' => $p->end_date,
                    'status' => $p->status,
                    'course_id' => $p->course_id,
                ]);
            }
        }

        // --- Fetch Exams ---
        if ($type === 'exam' || $type === 'all') {
            $exams = Exam::whereIn('course_id', $enrolledCourseIds)
                ->whereHas('createdBy', function ($q) use ($institutionId) {
                    $q->where('institution_id', $institutionId);
                })
                ->when($status !== 'all', function ($q) use ($status) {
                    $q->where('status', $status);
                })
                ->when($filterDate, function ($q) use ($filterDate) {
                    $q->whereDate('start_date', '<=', $filterDate)
                        ->whereDate('end_date', '>=', $filterDate);
                })
                ->select('id', 'title', 'start_date', 'end_date', 'status', 'duration_minutes', 'course_id')
                ->get();

            foreach ($exams as $e) {
                $schedule->push([
                    'type' => 'exam',
                    'id' => $e->id,
                    'title' => $e->title,
                    'start_date' => $e->start_date,
                    'end_date' => $e->end_date,
                    'status' => $e->status,
                    'duration_minutes' => $e->duration_minutes,
                    'course_id' => $e->course_id,
                ]);
            }
        }

        // Sort by start date
        $schedule = $schedule->sortBy('start_date')->values();

        return response()->json([
            'status' => 'success',
            'message' => 'Schedule retrieved successfully.',
            'filters' => [
                'type' => $type,
                'status' => $status,
                'date' => $filterDate ?? 'all',
            ],
            'data' => $schedule,
        ], 200);
    }
}
