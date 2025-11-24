<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Module;
use App\Models\Student;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AnalyticsController extends Controller
{
    /* =====================================
     *  Analytics Overview
     * ===================================== */
    /**
     * Get analytics overview
     */
    public function Overview()
    {
        // total stdents count and active learners in percent last week
        $totalStudents = Student::count();
        $activeLearners = Student::whereHas('enrollments', function ($query) {
            $query->where('status', 'completed');
            $query->whereDate('completed_at', '>=', now()->subWeek());
        })->count();
        $activeLearnersPercentage = $totalStudents > 0 ? round(($activeLearners / $totalStudents) * 100, 2) : 0;

        // total activities
        $totalActivities = Activity::count();
        // activities growth in percent this week
        $activitiesGrowth = Activity::whereDate('created_at', '>=', now()->subWeek())->count();
        $activitiesGrowthPercentage = $totalActivities > 0 ? round(($activitiesGrowth / $totalActivities) * 100, 2) : 0;

        // total assessments submitted
        $assessmentsExamSubmitted = Enrollment::whereHas('examSubmissions', function ($query) {
            $query->where('status', 'submitted');
        })->count();
        $assessmentsProjectSubmitted = Enrollment::whereHas('projectSubmissions', function ($query) {
            $query->where('status', 'submitted');
        })->count();
        $assessmentsQuizSubmitted = Enrollment::whereHas('quizSubmissions', function ($query) {
            $query->where('status', 'submitted');
        })->count();
        $assessmentsSubmitted = $assessmentsExamSubmitted + $assessmentsProjectSubmitted + $assessmentsQuizSubmitted;
        $assessmentsSubmittedPercentage = $totalStudents > 0 ? round(($assessmentsSubmitted / $totalStudents) * 100, 2) : 0;

        // course completion rate
        $courseCompletionRate = Enrollment::where('status', 'completed')->count();
        $courseCompletionRatePercentage = $totalStudents > 0 ? round(($courseCompletionRate / $totalStudents) * 100, 2) : 0;

        return response()->json([
            'status' => 'success',
            'message' => 'Analytics overview fetched successfully',
            'data' => [
                'total_students' => $totalStudents,
                'active_learners' => $activeLearners,
                'active_learners_percentage' => $activeLearnersPercentage,
                'total_activities' => $totalActivities,
                'activities_growth' => $activitiesGrowth,
                'activities_growth_percentage' => $activitiesGrowthPercentage,
                'assessments_submitted' => $assessmentsSubmitted,
                'assessments_submitted_percentage' => $assessmentsSubmittedPercentage,
                'course_completion_rate' => $courseCompletionRate,
                'course_completion_rate_percentage' => $courseCompletionRatePercentage,
            ],
        ], 200);

    }
    /**
     * Get activity trend
     */
    public function activityTrend(Request $request)
    {
        $period = $request->period ?? 'month'; // Default to month
        $dbDriver = config('database.default');
        $isPostgreSQL = $dbDriver === 'pgsql';

        // Determine date range based on period
        switch ($period) {
            case 'today':
                $from = now()->startOfDay();
                $to = now()->endOfDay();
                $groupBy = '3hour';
                break;
            case 'week':
                $from = now()->startOfWeek();
                $to = now()->endOfWeek();
                $groupBy = 'day';
                break;
            case 'month':
                $from = now()->startOfMonth();
                $to = now()->endOfMonth();
                $groupBy = 'day';
                break;
            case 'year':
                $from = now()->startOfYear();
                $to = now()->endOfYear();
                $groupBy = 'month';
                break;
            default:
                $from = now()->startOfMonth();
                $to = now()->endOfMonth();
                $groupBy = 'week';
                break;
        }

        // Fetch data based on grouping with database-specific queries
        switch ($groupBy) {
            case '3hour':
                if ($isPostgreSQL) {
                    $data = Activity::selectRaw('EXTRACT(HOUR FROM created_at) as period, COUNT(*) as count')
                        ->whereBetween('created_at', [$from, $to])
                        ->groupBy('period')
                        ->orderBy('period', 'ASC')
                        ->get()
                        ->pluck('count', 'period');
                } else {
                    $data = Activity::selectRaw('HOUR(created_at) as period, COUNT(*) as count')
                        ->whereBetween('created_at', [$from, $to])
                        ->groupBy('period')
                        ->orderBy('period', 'ASC')
                        ->get()
                        ->pluck('count', 'period');
                }

                // Generate cntinous 3-hour (0-23)
                $periods = collect();
                $labels = collect();
                $ranges = collect();

                for ($i = 0; $i < 24; $i += 3) {
                    $periods->push($i);
                    $labels->push($i . ' - ' . ($i + 2));
                    $ranges->push($i . ' - ' . ($i + 2));
                }

                break;

            case 'day':
                if ($isPostgreSQL) {
                    $data = Activity::selectRaw('EXTRACT(DOW FROM created_at) as period, COUNT(*) as count')
                        ->whereBetween('created_at', [$from, $to])
                        ->groupBy('period')
                        ->orderBy('period', 'ASC')
                        ->get()
                        ->pluck('count', 'period');

                    // PostgreSQL DOW returns 0-6 (0=Sunday, 6=Saturday)
                    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    $periods = collect();
                    $labels = collect();
                    $ranges = collect();

                    for ($day = 0; $day <= 6; $day++) {
                        $periods->put($day, $day);
                        $labels->push($dayNames[$day]);
                        $ranges->push(null); // No ranges for days of week
                    }
                } else {
                    $data = Activity::selectRaw('DAYOFWEEK(created_at) as period, COUNT(*) as count')
                        ->whereBetween('created_at', [$from, $to])
                        ->groupBy('period')
                        ->orderBy('period', 'ASC')
                        ->get()
                        ->pluck('count', 'period');

                    // MySQL DAYOFWEEK returns 1-7 (1=Sunday, 7=Saturday)
                    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    $periods = collect();
                    $labels = collect();
                    $ranges = collect();

                    for ($day = 1; $day <= 7; $day++) {
                        $periods->put($day, $day);
                        $labels->push($dayNames[$day - 1]);
                        $ranges->push(null); // No ranges for days of week
                    }
                }

                break;

            case 'week':
                if ($isPostgreSQL) {
                    $data = Activity::selectRaw('EXTRACT(WEEK FROM created_at) as period, COUNT(*) as count')
                        ->whereBetween('created_at', [$from, $to])
                        ->groupBy('period')
                        ->orderBy('period', 'ASC')
                        ->get()
                        ->pluck('count', 'period');
                } else {
                    $data = Activity::selectRaw('WEEK(created_at, 1) as period, COUNT(*) as count')
                        ->whereBetween('created_at', [$from, $to])
                        ->groupBy('period')
                        ->orderBy('period', 'ASC')
                        ->get()
                        ->pluck('count', 'period');
                }

                // Generate continuous weeks for the period
                $periods = collect();
                $labels = collect();
                $ranges = collect();
                $current = $from->copy();
                $weekCounter = 1;

                while ($current->lte($to)) {
                    if ($isPostgreSQL) {
                        $weekNumber = $current->weekOfYear;
                    } else {
                        $weekNumber = $current->week;
                    }

                    $weekStart = $current->copy()->startOfWeek();
                    $weekEnd = $current->copy()->endOfWeek();

                    $periods->put($weekNumber, $weekNumber);
                    $labels->push("Week " . $weekCounter);
                    $ranges->push($weekStart->toDateString() . " - " . $weekEnd->toDateString());

                    $current->addWeek();
                    $weekCounter++;
                }

                break;

            case 'month':
                if ($isPostgreSQL) {
                    $data = Activity::selectRaw('EXTRACT(MONTH FROM created_at) as period, COUNT(*) as count')
                        ->whereBetween('created_at', [$from, $to])
                        ->groupBy('period')
                        ->orderBy('period', 'ASC')
                        ->get()
                        ->pluck('count', 'period');
                } else {
                    $data = Activity::selectRaw('MONTH(created_at) as period, COUNT(*) as count')
                        ->whereBetween('created_at', [$from, $to])
                        ->groupBy('period')
                        ->orderBy('period', 'ASC')
                        ->get()
                        ->pluck('count', 'period');
                }

                // Generate continuous months (1-12)
                $periods = collect();
                $labels = collect();
                $ranges = collect();
                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

                for ($month = 1; $month <= 12; $month++) {
                    $periods->put($month, $month);
                    $labels->push($months[$month - 1]);
                    $ranges->push(null); // No ranges for months
                }

                break;
        }

        // Prepare dataset (period data + cumulative)
        $periodData = [];
        $cumulativeData = [];
        $cumulative = 0;

        foreach ($periods->keys() as $periodKey) {
            $count = $data->get($periodKey, 0);
            $cumulative += $count;

            $periodData[] = $count;
            $cumulativeData[] = $cumulative;
        }

        // Prepare metadata
        $totalActivities = Activity::count();
        $totalActivitiesInPeriod = array_sum($periodData);

        // Return response
        return response()->json([
            'status' => 'success',
            'message' => 'Activity trend fetched successfully',
            'data' => [
                'period' => $period,
                'groupBy' => $groupBy,
                'periodData' => $periodData,
                'cumulativeData' => $cumulativeData,
                'labels' => $labels->values()->all(),
                'ranges' => $ranges->values()->all(),
                'totalActivities' => $totalActivities,
                'totalActivitiesInPeriod' => $totalActivitiesInPeriod,
            ],
        ], 200);
    }

    public function courseCompletionStatus(Request $request)
    {
        $period = $request->period ?? 'month';

        // Determine date range
        $from = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $to = match ($period) {
            'today' => now()->endOfDay(),
            'week' => now()->endOfWeek(),
            'month' => now()->endOfMonth(),
            'year' => now()->endOfYear(),
            default => now()->endOfMonth(),
        };

        // Get course_id or fallback to first course
        $courseId = $request->course_id;

        if (!$courseId) {
            $firstCourse = Course::first(); // or ->orderBy('id')->first()

            if (!$firstCourse) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No courses found.',
                ], 404);
            }

            $courseId = $firstCourse->id;
        }

        // Get modules for this course
        $modules = Module::where('course_id', $courseId)->get();

        // Load enrollments for this course only
        $enrollments = Enrollment::where('course_id', $courseId)
            ->whereBetween('completed_at', [$from, $to])
            ->with('modules')
            ->get();

        $data = [];

        foreach ($modules as $module) {
            $completed = $enrollments->filter(function ($enrollment) use ($module) {
                return $enrollment->modules
                    ->where('module_id', $module->id)
                    ->where('status', 'completed')
                    ->isNotEmpty();
            })->count();

            $data[] = [
                'module_id' => $module->id,
                'module_name' => $module->title,
                'completed_enrollments' => $completed,
            ];
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Course completion status fetched successfully',
            'data' => $data,
            'default_course_id' => $courseId, // optional
        ]);
    }
}
