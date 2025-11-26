<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseQuize;
use App\Models\Enrollment;
use App\Models\EnrollmentLecture;
use App\Models\EnrollmentModule;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\Institution;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectSubmission;
use App\Models\QuizSubmission;
use App\Models\Student;
use App\Models\Trainer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                $groupBy = 'week';
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

        // Return response
        return response()->json([
            'status' => 'success',
            'message' => 'Activity trend fetched successfully',
            'data' => [
                'period' => $period,
                'groupBy' => $groupBy,
                'periodData' => $periodData,
                'labels' => $labels->values()->all(),
                'ranges' => $ranges->values()->all(),
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

    /* =====================================
     *  Engagement Overview
     * ===================================== */
    public function engagementOverview()
    {
        // total activities
        $totalActivities = Activity::count();
        // activities growth in percent this week
        $activitiesGrowth = Activity::whereDate('created_at', '>=', now()->subWeek())->count();
        $activitiesGrowthPercentage = $totalActivities > 0 ? round(($activitiesGrowth / $totalActivities) * 100, 2) : 0;

        // average activities per day
        $days = now()->subWeek()->diffInDays(now()); // will always give 7
        $averageActivitiesPerDay = $days > 0 ? round($totalActivities / $days, 2) : 0;

        // average activities per day from last week geowth
        $averageActivitiesPerDayFromLastWeekGrowth = Activity::whereDate('created_at', '>=', now()->subWeek())->count();
        $averageActivitiesPerDayFromLastWeekGrowthPercentage = $totalActivities > 0 ? round(($averageActivitiesPerDayFromLastWeekGrowth / $totalActivities) * 100, 2) : 0;

        // active students count
        $activeStudents = Student::whereHas('enrollments', function ($query) {
            $query->where('status', 'completed');
            $query->whereDate('completed_at', '>=', now()->subWeek());
        })->count();
        // active students percentage
        $activeStudentsPercentage = $totalActivities > 0 ? round(($activeStudents / $totalActivities) * 100, 2) : 0;

        // most asctive students total count in last 7 days
        $mostActiveStudents = Activity::select('causer_id', DB::raw('count(*) as total_activities'))
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], function ($query) {
                $query->whereHas('student');
            })
            ->whereDate('created_at', '>=', now()->subWeek())
            ->groupBy('causer_id')
            ->orderByDesc('total_activities')
            ->get();
        $mostActiveStudentsCount = $mostActiveStudents->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Engagement overview fetched successfully',
            'data' => [
                'total_activities' => $totalActivities,
                'activities_growth' => $activitiesGrowth,
                'activities_growth_percentage' => $activitiesGrowthPercentage,
                'average_activities_per_day' => $averageActivitiesPerDay,
                'average_activities_per_day_from_last_week_growth' => $averageActivitiesPerDayFromLastWeekGrowth,
                'average_activities_per_day_from_last_week_growth_percentage' => $averageActivitiesPerDayFromLastWeekGrowthPercentage,
                'active_students' => $activeStudents,
                'active_students_percentage' => $activeStudentsPercentage,
                'most_active_students_count' => $mostActiveStudentsCount,
            ],
        ], 200);
    }

    /* =====================================
     *  Learning progress
     * ===================================== */

    public function learningOverview()
    {
        // lectures watched count
        $lecturesWatched = EnrollmentLecture::where('is_watched', true)->count();
        // watch growth from last week
        $lectureWatchGrowth = EnrollmentLecture::where('is_watched', true)
            ->whereDate('updated_at', '>=', now()->subWeek())
            ->count();
        $lectureWatchGrowthPercentage = $lecturesWatched > 0 ? round(($lectureWatchGrowth / $lecturesWatched) * 100, 2) : 0;

        // modules completed count
        $moduleCompletedCount = EnrollmentModule::where('status', 'completed')->count();
        // modules completed growth from last week
        $moduleCompletedGrowth = EnrollmentModule::where('status', 'completed')
            ->whereDate('updated_at', '>=', now()->subWeek())
            ->count();
        $moduleCompletedGrowthPercentage = $moduleCompletedCount > 0 ? round(($moduleCompletedGrowth / $moduleCompletedCount) * 100, 2) : 0;

        // course completion rate
        $courseCompletedCount = Enrollment::where('status', 'completed')->count();
        $totalEnrollmentCount = Enrollment::count();
        $courseCompletionRate = $totalEnrollmentCount > 0 ? round(($courseCompletedCount / $totalEnrollmentCount) * 100, 2) : 0;


        return response()->json([
            'status' => 'success',
            'message' => 'Learning overview fetched successfully',
            'data' => [
                'total_lectures_watched' => $lecturesWatched,
                'watch_growth' => $lectureWatchGrowth,
                'watch_growth_percentage' => $lectureWatchGrowthPercentage,
                'module_completed_count' => $moduleCompletedCount,
                'module_completed_growth' => $moduleCompletedGrowth,
                'module_completed_growth_percentage' => $moduleCompletedGrowthPercentage,
                'course_completion_rate' => $courseCompletionRate,
            ],
        ]);
    }

    public function moduleCompletionRate(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $page = $request->page ?? 1;

        // Get modules with enrollmentModules count
        $modulesQuery = Module::withCount('enrollmentModules');

        // Paginate
        $paginatedModules = $modulesQuery->paginate($perPage, ['*'], 'page', $page);

        // Map the paginated items
        $modules = $paginatedModules->getCollection()->map(function ($module) {
            $completedEnrollments = $module->enrollmentModules()->where('status', 'completed')->count();

            return [
                'module_id' => $module->id,
                'module_name' => $module->title,
                'total_enrollments' => $module->enrollment_modules_count,
                'completed_enrollments' => $completedEnrollments,
                'completion_rate' => $module->enrollment_modules_count > 0
                    ? round(($completedEnrollments / $module->enrollment_modules_count) * 100, 2)
                    : 0,
            ];
        });

        // Replace the collection on the paginator
        $paginatedModules->setCollection($modules);

        return response()->json([
            'status' => 'success',
            'message' => 'Module completion rate fetched successfully',
            'data' => $paginatedModules,
        ]);
    }

    /* =====================================
     *  Assessment Participation
     * ===================================== */

    public function assessmentOverview()
    {
        // where status in submitted or failed
        $quizSubmission = QuizSubmission::whereIn('status', ['submitted', 'failed'])->count();
        // growth from last week
        $quizSubmissionGrowth = QuizSubmission::whereIn('status', ['submitted', 'failed'])
            ->whereDate('created_at', '>=', now()->subWeek())
            ->count();
        $quizSubmissionGrowthPercentage = $quizSubmission > 0 ? round(($quizSubmissionGrowth / $quizSubmission) * 100, 2) : 0;

        // average quiz submissions per student
        $averageQuizSubmissionPerStudent = round($quizSubmission / Student::count(), 2);

        // pass ratio
        $quizPass = QuizSubmission::where('status', 'passed')->count();
        $quizPassRatio = $quizSubmission > 0 ? round(($quizPass / $quizSubmission) * 100, 2) : 0;

        // where status in submitted or failed
        $examSubmission = ExamSubmission::whereIn('status', ['submitted', 'failed'])->count();
        // growth from last week
        $examSubmissionGrowth = ExamSubmission::whereIn('status', ['submitted', 'failed'])
            ->whereDate('created_at', '>=', now()->subWeek())
            ->count();
        $examSubmissionGrowthPercentage = $examSubmission > 0 ? round(($examSubmissionGrowth / $examSubmission) * 100, 2) : 0;

        // average exam submissions per student
        $averageExamSubmissionPerStudent = round($examSubmission / Student::count(), 2);

        $examPass = ExamSubmission::where('status', 'passed')->count();
        $examPassRatio = $examSubmission > 0 ? round(($examPass / $examSubmission) * 100, 2) : 0;


        // where status in submitted or failed
        $projectSubmission = ProjectSubmission::whereIn('status', ['submitted', 'failed'])->count();
        // growth from last week
        $projectSubmissionGrowth = ProjectSubmission::whereIn('status', ['submitted', 'failed'])
            ->whereDate('created_at', '>=', now()->subWeek())
            ->count();
        $projectSubmissionGrowthPercentage = $projectSubmission > 0 ? round(($projectSubmissionGrowth / $projectSubmission) * 100, 2) : 0;

        // average project submissions per student
        $averageProjectSubmissionPerStudent = round($projectSubmission / Student::count(), 2);

        $projectPass = ProjectSubmission::where('status', 'passed')->count();
        $projectPassRatio = $projectSubmission > 0 ? round(($projectPass / $projectSubmission) * 100, 2) : 0;

        return response()->json([
            'status' => 'success',
            'message' => 'Assessment overview fetched successfully',
            'data' => [
                'quiz_submission' => $quizSubmission,
                'quiz_submission_growth' => $quizSubmissionGrowth,
                'quiz_submission_growth_percentage' => $quizSubmissionGrowthPercentage,
                'exam_submission' => $examSubmission,
                'exam_submission_growth' => $examSubmissionGrowth,
                'exam_submission_growth_percentage' => $examSubmissionGrowthPercentage,
                'project_submission' => $projectSubmission,
                'project_submission_growth' => $projectSubmissionGrowth,
                'project_submission_growth_percentage' => $projectSubmissionGrowthPercentage,
                'average_quiz_submission_per_student' => $averageQuizSubmissionPerStudent,
                'average_exam_submission_per_student' => $averageExamSubmissionPerStudent,
                'average_project_submission_per_student' => $averageProjectSubmissionPerStudent,
                'quiz_pass_ratio' => $quizPassRatio,
                'exam_pass_ratio' => $examPassRatio,
                'project_pass_ratio' => $projectPassRatio,
            ],
        ]);
    }

    public function quizParticipation(Request $request)
    {
        $defaultTrainerId = Trainer::first()->id;

        // Use trainer_id from request OR default one
        $trainerId = $request->trainer_id ?? $defaultTrainerId;
        $quizzes = CourseQuize::where('created_by', $trainerId)->get();

        // map with submission count and participation rate
        $data = $quizzes->map(function ($quiz) {
            $submissionCount = QuizSubmission::where('course_quize_id', $quiz->id)->count();
            // passed count
            $passedCount = QuizSubmission::where('course_quize_id', $quiz->id)->where('status', 'passed')->count();

            // failed count
            $failedCount = QuizSubmission::where('course_quize_id', $quiz->id)->where('status', 'failed')->count();

            $passRate = $submissionCount > 0 ? round(($passedCount / $submissionCount) * 100, 2) : 0;


            return [
                'quiz_id' => $quiz->id,
                'quiz_name' => $quiz->title,
                'attmpted' => $submissionCount,
                'passed' => $passedCount,
                'failed' => $failedCount,
                'pass_rate' => $passRate,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Quiz participation fetched successfully',
            'data' => $data,
        ]);
    }

    public function assessmentStatusDistribution()
    {
        // quiz status distribution
        $quizStatusDistribution = QuizSubmission::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        // exam status distribution
        $examStatusDistribution = ExamSubmission::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        // project status distribution
        $projectStatusDistribution = ProjectSubmission::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Assessment status distribution fetched successfully',
            'data' => [
                'quiz_status_distribution' => $quizStatusDistribution,
                'exam_status_distribution' => $examStatusDistribution,
                'project_status_distribution' => $projectStatusDistribution,
            ],
        ]);
    }

    // public function topPerformingStudents(Request $request)
    // {
    //     $requestedLimit = $request->limit ?? 5;
    //     // top performing students based on passed assessments and also add enrollment progress
    //     $topStudents = Student::select('students.id', 'users.full_name', 'enrollments.progress', DB::raw('count(quiz_submissions.id) + count(exam_submissions.id) + count(project_submissions.id) as total_passed_assessments'))
    //         ->leftJoin('enrollments', 'enrollments.student_id', '=', 'students.id')
    //         ->leftJoin('quiz_submissions', function ($join) {
    //             $join->on('students.id', '=', 'quiz_submissions.student_id')
    //                 ->where('quiz_submissions.status', 'passed');
    //         })
    //         ->leftJoin('exam_submissions', function ($join) {
    //             $join->on('students.id', '=', 'exam_submissions.student_id')
    //                 ->where('exam_submissions.status', 'passed');
    //         })
    //         ->leftJoin('project_submissions', function ($join) {
    //             $join->on('students.id', '=', 'project_submissions.student_id')
    //                 ->where('project_submissions.status', 'passed');
    //         })
    //         ->groupBy('students.id', 'students.name')
    //         ->orderByDesc('total_passed_assessments')
    //         ->limit($requestedLimit)
    //         ->get();

    //     return response()->json([
    //         'status' => 'success',
    //         'message' => 'Top performing students fetched successfully',
    //         'data' => $topStudents,
    //     ]);
    // }

    public function topPerformingStudents(Request $request)
    {
        $limit = $request->limit ?? 5;

        $topStudents = Student::query()
            ->select(
                'students.id',
                'users.full_name',
                'enrollments.progress',

                DB::raw("
                SUM(CASE WHEN quiz_submissions.status = 'passed' THEN 1 ELSE 0 END) +
                SUM(CASE WHEN exam_submissions.status = 'passed' THEN 1 ELSE 0 END) +
                SUM(CASE WHEN project_submissions.status = 'passed' THEN 1 ELSE 0 END)
                AS total_passed_assessments
            ")
            )
            ->leftJoin('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('enrollments', 'enrollments.student_id', '=', 'students.id')

            ->leftJoin('quiz_submissions', 'quiz_submissions.student_id', '=', 'students.id')
            ->leftJoin('exam_submissions', 'exam_submissions.student_id', '=', 'students.id')
            ->leftJoin('project_submissions', 'project_submissions.student_id', '=', 'students.id')

            ->groupBy('students.id', 'users.full_name', 'enrollments.progress')
            ->orderByDesc('total_passed_assessments')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Top performing students fetched successfully',
            'data' => $topStudents,
        ]);
    }


    public function assessmentInsights(Request $request)
    {
        $assessmentType = $request->type ?? 'quiz'; // quiz | exam | project

        switch ($assessmentType) {
            case 'quiz':
                $totalSubmissions = QuizSubmission::count();
                $passedSubmissions = QuizSubmission::where('status', 'passed')->count();
                $failedSubmissions = QuizSubmission::where('status', 'failed')->count();
                $averagePassRate = $totalSubmissions > 0 ? round(($passedSubmissions / $totalSubmissions) * 100, 2) : 0;
                $averageFailRate = $totalSubmissions > 0 ? round(($failedSubmissions / $totalSubmissions) * 100, 2) : 0;
                // most challenging quizzes (with highest fail rate)
                $mostChallengingQuizzes = CourseQuize::select(
                    'course_quizes.id',
                    'course_quizes.title',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN quiz_submissions.status = 'failed' THEN 1 ELSE 0 END) as failed_count"),
                    DB::raw("(SUM(CASE WHEN quiz_submissions.status = 'failed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as fail_rate")
                )
                    ->leftJoin('quiz_submissions', 'quiz_submissions.course_quize_id', '=', 'course_quizes.id')
                    ->groupBy('course_quizes.id', 'course_quizes.title')
                    ->having('total', '>', 0)
                    ->orderByDesc('fail_rate')
                    ->limit(5)
                    ->get();

                // most successful quizzes (with highest pass rate)
                $mostSuccessfulQuizzes = CourseQuize::select(
                    'course_quizes.id',
                    'course_quizes.title',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN quiz_submissions.status = 'passed' THEN 1 ELSE 0 END) as passed_count"),
                    DB::raw("(SUM(CASE WHEN quiz_submissions.status = 'passed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as pass_rate")
                )
                    ->leftJoin('quiz_submissions', 'quiz_submissions.course_quize_id', '=', 'course_quizes.id')
                    ->groupBy('course_quizes.id', 'course_quizes.title')
                    ->having('total', '>', 0)
                    ->orderByDesc('pass_rate')
                    ->limit(5)
                    ->get();
                $data = [
                    'total_submissions' => $totalSubmissions,
                    'passed_submissions' => $passedSubmissions,
                    'failed_submissions' => $failedSubmissions,
                    'average_pass_rate' => $averagePassRate,
                    'average_fail_rate' => $averageFailRate,
                    'most_challenging_quizzes' => $mostChallengingQuizzes,
                    'most_successful_quizzes' => $mostSuccessfulQuizzes,
                ];
                return response()->json([
                    'status' => 'success',
                    'message' => 'Assessment insights fetched successfully',
                    'data' => $data,
                ]);
                break;
            case 'exam':
                $totalSubmissions = ExamSubmission::count();
                $passedSubmissions = ExamSubmission::where('status', 'passed')->count();
                $failedSubmissions = ExamSubmission::where('status', 'failed')->count();
                $averagePassRate = $totalSubmissions > 0 ? round(($passedSubmissions / $totalSubmissions) * 100, 2) : 0;
                $averageFailRate = $totalSubmissions > 0 ? round(($failedSubmissions / $totalSubmissions) * 100, 2) : 0;
                // most challenging exams (with highest fail rate)
                $mostChallengingExams = Exam::select(
                    'exams.id',
                    'exams.title',
                    DB::raw('COUNT(exam_submissions.id) as total'),
                    DB::raw('SUM(CASE WHEN exam_submissions.status = "failed" THEN 1 ELSE 0 END) as failed_count'),
                    DB::raw('(SUM(CASE WHEN exam_submissions.status = "failed" THEN 1 ELSE 0 END) / COUNT(exam_submissions.id)) * 100 as fail_rate')
                )
                    ->leftJoin('exam_submissions', 'exam_submissions.exam_id', '=', 'exams.id')
                    ->groupBy('exams.id', 'exams.title')
                    ->having('total', '>', 0)
                    ->orderByDesc('fail_rate')
                    ->limit(5)
                    ->get();
                // most successful exams (with highest pass rate)
                $mostSuccessfulExams = Exam::select(
                    'exams.id',
                    'exams.title',
                    DB::raw('COUNT(exam_submissions.id) as total'),
                    DB::raw('SUM(CASE WHEN exam_submissions.status = "passed" THEN 1 ELSE 0 END) as passed_count'),
                    DB::raw('(SUM(CASE WHEN exam_submissions.status = "passed" THEN 1 ELSE 0 END) / COUNT(exam_submissions.id)) * 100 as pass_rate')
                )
                    ->leftJoin('exam_submissions', 'exam_submissions.exam_id', '=', 'exams.id')
                    ->groupBy('exams.id', 'exams.title')
                    ->having('total', '>', 0)
                    ->orderByDesc('pass_rate')
                    ->limit(5)
                    ->get();
                $data = [
                    'total_submissions' => $totalSubmissions,
                    'passed_submissions' => $passedSubmissions,
                    'failed_submissions' => $failedSubmissions,
                    'average_pass_rate' => $averagePassRate,
                    'average_fail_rate' => $averageFailRate,
                    'most_challenging_exams' => $mostChallengingExams,
                    'most_successful_exams' => $mostSuccessfulExams,
                ];
                return response()->json([
                    'status' => 'success',
                    'message' => 'Assessment insights fetched successfully',
                    'data' => $data,
                ]);
                break;
            case 'project':
                $totalSubmissions = ProjectSubmission::count();
                $passedSubmissions = ProjectSubmission::where('status', 'passed')->count();
                $failedSubmissions = ProjectSubmission::where('status', 'failed')->count();
                $averagePassRate = $totalSubmissions > 0 ? round(($passedSubmissions / $totalSubmissions) * 100, 2) : 0;
                $averageFailRate = $totalSubmissions > 0 ? round(($failedSubmissions / $totalSubmissions) * 100, 2) : 0;
                // most challenging projects (with highest fail rate)
                $mostChallengingProjects = Project::select(
                    'projects.id',
                    'projects.title',
                    DB::raw('COUNT(project_submissions.id) as total'),
                    DB::raw('SUM(CASE WHEN project_submissions.status = "failed" THEN 1 ELSE 0 END) as failed_count'),
                    DB::raw('(SUM(CASE WHEN project_submissions.status = "failed" THEN 1 ELSE 0 END) / COUNT(project_submissions.id)) * 100 as fail_rate')
                )
                    ->leftJoin('project_submissions', 'project_submissions.project_id', '=', 'projects.id')
                    ->groupBy('projects.id', 'projects.title')
                    ->having('total', '>', 0)
                    ->orderByDesc('fail_rate')
                    ->limit(5)
                    ->get();
                // most successful projects (with highest pass rate)
                $mostSuccessfulProjects = Project::select(
                    'projects.id',
                    'projects.title',
                    DB::raw('COUNT(project_submissions.id) as total'),
                    DB::raw('SUM(CASE WHEN project_submissions.status = "passed" THEN 1 ELSE 0 END) as passed_count'),
                    DB::raw('(SUM(CASE WHEN project_submissions.status = "passed" THEN 1 ELSE 0 END) / COUNT(project_submissions.id)) * 100 as pass_rate')
                )
                    ->leftJoin('project_submissions', 'project_submissions.project_id', '=', 'projects.id')
                    ->groupBy('projects.id', 'projects.title')
                    ->having('total', '>', 0)
                    ->orderByDesc('pass_rate')
                    ->limit(5)
                    ->get();
                $data = [
                    'total_submissions' => $totalSubmissions,
                    'passed_submissions' => $passedSubmissions,
                    'failed_submissions' => $failedSubmissions,
                    'average_pass_rate' => $averagePassRate,
                    'average_fail_rate' => $averageFailRate,
                    'most_challenging_projects' => $mostChallengingProjects,
                    'most_successful_projects' => $mostSuccessfulProjects,
                ];
                return response()->json([
                    'status' => 'success',
                    'message' => 'Assessment insights fetched successfully',
                    'data' => $data,
                ]);
                break;
            default:
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid assessment type. Choose quiz, exam, or project.',
                ], 400);
        }
    }

    /* =====================================
     *  Institution Insight
     * ===================================== */

    public function institutionOverview()
    {
        $totalInstitutions = Institution::count();
        $totalStudents = Student::count();

        // Student count per institution
        $studentCountPerInstitution = Institution::withCount('students')->get();

        // Activity count per student (user_id = causer_id)
        $studentActivities = DB::table('activity_log')
            ->select('causer_id', DB::raw('COUNT(*) as activity_count'))
            ->whereNotNull('causer_id')
            ->whereDate('created_at', '>=', now()->subWeek())
            ->groupBy('causer_id')
            ->pluck('activity_count', 'causer_id');

        // Load institutions + students + users
        $institutions = Institution::with(['students.user'])
            ->withCount('students')
            ->get();

        // Compute engagement
        $averageStudentEngagement = $institutions->map(function ($institution) use ($studentActivities) {
            $totalActivities = 0;

            foreach ($institution->students as $student) {
                $userId = $student->user->id;
                $totalActivities += $studentActivities[$userId] ?? 0;
            }

            $averageEngagement = $institution->students_count > 0
                ? round($totalActivities / $institution->students_count, 2)
                : 0;

            return [
                'institution_id' => $institution->id,
                'institution_name' => $institution->name,
                'student_count' => $institution->students_count,
                'average_student_engagement' => $averageEngagement,
            ];
        });

        $topInstitutionsByEngagement = $averageStudentEngagement
            ->sortByDesc('average_student_engagement')
            ->take(5)
            ->values();

        return response()->json([
            'status'   => 'success',
            'message'  => 'Institution overview fetched successfully',
            'data'     => [
                'total_institutions' => $totalInstitutions,
                'total_students' => $totalStudents,
                'student_count_per_institution' => $studentCountPerInstitution,
                'top_institutions_by_engagement' => $topInstitutionsByEngagement,
                'average_student_engagement_per_institution' => $averageStudentEngagement,
            ],
        ]);
    }
}
