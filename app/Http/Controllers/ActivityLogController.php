<?php

namespace App\Http\Controllers;

use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\EnrollmentLecture;
use App\Models\EnrollmentModule;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Institution;
use App\Models\Trainer;
use App\Models\Project;
use App\Models\CourseQuize;
use App\Models\Exam;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = Activity::when($request->search, function ($query, $search) {
            return $query->where('description', 'like', "%$search%");
        })->latest()->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Activity logs fetched successfully',
            'data' => $logs,
        ], 200);
    }

    public function myActivityLog(Request $request)
    {
        $logs = Activity::where('causer_id', Auth::user()->id)
            ->when($request->search, function ($query, $search) {
                return $query->where('description', 'like', "%$search%");
            })
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'Activity logs fetched successfully',
            'data' => $logs,
        ], 200);
    }

    public function getEngagmentOverview(Request $request)
    {
        // Get date filters from request
        $fromDate = $request->from;
        $toDate = $request->to;

        // Total number of activities per student
        $activitiesPerStudent = Activity::select('causer_id', DB::raw('count(*) as total_activities'))
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], function ($query) {
                $query->whereHas('student');
            })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->groupBy('causer_id')
            ->with('causer.student')
            ->get()
            ->map(function ($activity) {
                return [
                    'student_id' => $activity->causer->student->id ?? null,
                    'student_name' => $activity->causer->full_name ?? 'Unknown',
                    'total_activities' => $activity->total_activities,
                    'institution_name' => $activity->causer->student->institution->name ?? 'Unknown',
                ];
            })
            ->filter(function ($item) {
                return $item['student_id'] !== null;
            })
            ->values();

        // Average activities per day
        $firstActivity = Activity::whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], function ($query) {
                $query->whereHas('student');
            })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->oldest()
            ->first();

        $lastActivity = Activity::whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], function ($query) {
                $query->whereHas('student');
            })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->latest()
            ->first();

        $totalActivities = Activity::whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], function ($query) {
                $query->whereHas('student');
            })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->count();

        $averageActivitiesPerDay = 0;
        if ($firstActivity && $lastActivity) {
            $daysDifference = $firstActivity->created_at->diffInDays($lastActivity->created_at);
            $daysDifference = $daysDifference > 0 ? $daysDifference : 1;
            $averageActivitiesPerDay = round($totalActivities / $daysDifference, 2);
        }

        // Most active students (top 5)
        $mostActiveStudents = Activity::select('causer_id', DB::raw('COUNT(*) as total_activities'))
            ->where('causer_type', \App\Models\User::class)
            ->whereIn('causer_id', Student::pluck('user_id')) // ensure only students
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->groupBy('causer_id')
            ->orderByDesc('total_activities')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $student = Student::where('user_id', $item->causer_id)->first();
                return [
                    'student_id' => $student->id ?? null,
                    'student_name' => $student->user->full_name ?? 'Unknown',
                    'total_activities' => $item->total_activities,
                    'institution_name' => $student->institution->name ?? 'Unknown',
                ];
            })
            ->filter(fn($item) => $item['student_id'] !== null)
            ->values();



        // Least active students (bottom 5)
        $leastActiveStudents = Activity::select('causer_id', DB::raw('COUNT(*) as total_activities'))
            ->where('causer_type', \App\Models\User::class)
            ->whereIn('causer_id', Student::pluck('user_id')) // ensure only students
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->groupBy('causer_id')
            ->orderBy('total_activities')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $student = Student::where('user_id', $item->causer_id)->first();
                return [
                    'student_id' => $student->id ?? null,
                    'student_name' => $student->user->full_name ?? 'Unknown',
                    'total_activities' => $item->total_activities,
                    'institution_name' => $student->institution->name ?? 'Unknown',
                ];
            })
            ->filter(fn($item) => $item['student_id'] !== null)
            ->values(); 

        // Most active days (top 7)
        $mostActiveDays = Activity::select(DB::raw('DATE(created_at) as activity_date'), DB::raw('count(*) as total_activities'))
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], function ($query) {
                $query->whereHas('student');
            })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->groupBy('activity_date')
            ->orderBy('total_activities', 'desc')
            ->limit(7)
            ->get()
            ->map(function ($activity) {
                return [
                    'date' => $activity->activity_date,
                    'total_activities' => $activity->total_activities,
                ];
            });

        // Least active days (bottom 7)
        $leastActiveDays = Activity::select(DB::raw('DATE(created_at) as activity_date'), DB::raw('count(*) as total_activities'))
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], function ($query) {
                $query->whereHas('student');
            })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->groupBy('activity_date')
            ->orderBy('total_activities', 'asc')
            ->limit(7)
            ->get()
            ->map(function ($activity) {
                return [
                    'date' => $activity->activity_date,
                    'total_activities' => $activity->total_activities,
                ];
            });

        return response()->json([
            'status' => 'success',
            'message' => 'Engagement overview fetched successfully',
            'filters' => [
                'from' => $fromDate ?? 'all',
                'to' => $toDate ?? 'all',
            ],
            'data' => [
                'total_activities' => $totalActivities,
                'average_activities_per_day' => $averageActivitiesPerDay,
                'activities_per_student' => $activitiesPerStudent,
                'most_active_students' => $mostActiveStudents,
                'least_active_students' => $leastActiveStudents,
                'most_active_days' => $mostActiveDays,
                'least_active_days' => $leastActiveDays,
            ],
        ], 200);
    }

    public function getLearningProgress(Request $request)
    {
        // Get date filters from request
        $fromDate = $request->from;
        $toDate = $request->to;

        // Number of lectures watched
        $lecturesWatchedCount = EnrollmentLecture::where('is_watched', true)
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('updated_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('updated_at', '<=', $toDate);
            })
            ->count();

        // Number of modules completed
        $modulesCompletedCount = EnrollmentModule::where('status', 'completed')
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('completed_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('completed_at', '<=', $toDate);
            })
            ->count();

        // Number of courses completed
        $coursesCompletedCount = Enrollment::where('status', 'completed')
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('completed_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('completed_at', '<=', $toDate);
            })
            ->count();

        // Total number of students
        $totalStudents = Student::count();

        // Number of students who completed at least one course
        $studentsWithCompletedCourses = Student::whereHas('enrollments', function ($query) use ($fromDate, $toDate) {
            $query->where('status', 'completed')
                ->when($fromDate, function ($q, $fromDate) {
                    return $q->whereDate('completed_at', '>=', $fromDate);
                })
                ->when($toDate, function ($q, $toDate) {
                    return $q->whereDate('completed_at', '<=', $toDate);
                });
        })->count();

        // Percentage of students who finished at least one course
        $percentageStudentsCompletedCourse = $totalStudents > 0
            ? round(($studentsWithCompletedCourses / $totalStudents) * 100, 2)
            : 0;

        return response()->json([
            'status' => 'success',
            'message' => 'Learning progress fetched successfully',
            'filters' => [
                'from' => $fromDate ?? 'all',
                'to' => $toDate ?? 'all',
            ],
            'data' => [
                'lectures_watched' => $lecturesWatchedCount,
                'modules_completed' => $modulesCompletedCount,
                'courses_completed' => $coursesCompletedCount,
                'total_students' => $totalStudents,
                'students_with_completed_courses' => $studentsWithCompletedCourses,
                'percentage_students_completed_course' => $percentageStudentsCompletedCourse,
            ],
        ], 200);
    }

    public function getSessionUsageAnalytics(Request $request)
    {
        $fromDate = $request->from;
        $toDate = $request->to;

        $dbDriver = config('database.default');
        $isPostgreSQL = $dbDriver === 'pgsql';

        /*
    |--------------------------------------------------------------------------
    |  DATE FORMATTERS FOR MYSQL & POSTGRESQL
    |--------------------------------------------------------------------------
    */
        $dateFormatDay   = $isPostgreSQL ? "DATE(created_at)"               : "DATE(created_at)";
        $dateFormatWeek  = $isPostgreSQL ? "TO_CHAR(created_at, 'IYYY-IW')" : "DATE_FORMAT(created_at, '%x-%v')";
        $dateFormatMonth = $isPostgreSQL ? "TO_CHAR(created_at, 'YYYY-MM')" : "DATE_FORMAT(created_at, '%Y-%m')";


        /*
    |--------------------------------------------------------------------------
    | LOGIN COUNT PER STUDENT
    |--------------------------------------------------------------------------
    */
        $loginActivities = Activity::select('causer_id', DB::raw('count(*) as login_count'))
            ->whereNotNull('causer_id')
            ->where('causer_type', 'App\Models\User')
            ->where(function ($query) {
                $query->where('description', 'like', '%login%')
                    ->orWhere('description', 'like', '%logged in%');
            })
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->groupBy('causer_id')
            ->get();

        $userIds = $loginActivities->pluck('causer_id')->toArray();

        $users = \App\Models\User::with('student')
            ->whereIn('id', $userIds)
            ->whereHas('student')
            ->get()
            ->keyBy('id');

        $loginCountPerStudent = $loginActivities->map(function ($item) use ($users) {
            $user = $users->get($item->causer_id);
            if (!$user || !$user->student) return null;

            return [
                'student_id'   => $user->student->id,
                'student_name' => $user->full_name,
                'login_count'  => $item->login_count,
            ];
        })->filter()->values();


        /*
    |--------------------------------------------------------------------------
    | AVERAGE SESSION TIME
    |--------------------------------------------------------------------------
    */
        $sessions = Activity::select('causer_id', 'description', 'created_at')
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], fn($q) => $q->whereHas('student'))
            ->where(function ($query) {
                $query->where('description', 'like', '%login%')
                    ->orWhere('description', 'like', '%logged in%')
                    ->orWhere('description', 'like', '%logout%')
                    ->orWhere('description', 'like', '%logged out%');
            })
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->orderBy('causer_id')
            ->orderBy('created_at')
            ->get();

        $sessionDurations = [];
        $currentLogin = null;

        foreach ($sessions as $activity) {
            $isLogin  = stripos($activity->description, 'login') !== false && stripos($activity->description, 'logout') === false;
            $isLogout = stripos($activity->description, 'logout') !== false;

            if ($isLogin) {
                $currentLogin = $activity;
            } elseif ($isLogout && $currentLogin && $currentLogin->causer_id === $activity->causer_id) {
                $duration = $currentLogin->created_at->diffInMinutes($activity->created_at);
                $sessionDurations[] = $duration;
                $currentLogin = null;
            }
        }

        $averageSessionTime = count($sessionDurations)
            ? round(array_sum($sessionDurations) / count($sessionDurations), 2)
            : 0;


        /*
    |--------------------------------------------------------------------------
    | ACTIVE USERS PER DAY
    |--------------------------------------------------------------------------
    */
        $activeUsersPerDay = Activity::select(
            DB::raw("$dateFormatDay as date"),
            DB::raw("COUNT(DISTINCT causer_id) as active_users")
        )
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], fn($q) => $q->whereHas('student'))
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->groupBy(DB::raw("$dateFormatDay"))
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();


        /*
    |--------------------------------------------------------------------------
    | ACTIVE USERS PER WEEK
    |--------------------------------------------------------------------------
    */
        $activeUsersPerWeek = Activity::select(
            DB::raw("$dateFormatWeek as week"),
            DB::raw("COUNT(DISTINCT causer_id) as active_users")
        )
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], fn($q) => $q->whereHas('student'))
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->groupBy(DB::raw("$dateFormatWeek"))
            ->orderBy('week', 'desc')
            ->limit(12)
            ->get();


        /*
    |--------------------------------------------------------------------------
    | ACTIVE USERS PER MONTH
    |--------------------------------------------------------------------------
    */
        $activeUsersPerMonth = Activity::select(
            DB::raw("$dateFormatMonth as month"),
            DB::raw("COUNT(DISTINCT causer_id) as active_users")
        )
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], fn($q) => $q->whereHas('student'))
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->groupBy(DB::raw("$dateFormatMonth"))
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();


        /*
    |--------------------------------------------------------------------------
    | INSTITUTION-WISE ACTIVITY DISTRIBUTION
    |--------------------------------------------------------------------------
    */
        $institutionWiseActivity = Activity::select('causer_id', DB::raw('count(*) as activity_count'))
            ->whereNotNull('causer_id')
            ->whereHasMorph('causer', ['App\Models\User'], fn($q) => $q->whereHas('student'))
            ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
            ->groupBy('causer_id')
            ->with('causer.student.institution')
            ->get()
            ->groupBy(fn($item) => $item->causer->student->institution_id ?? 'unknown')
            ->map(function ($group, $institutionId) {
                $firstItem = $group->first();

                return [
                    'institution_id'   => $institutionId !== 'unknown' ? $institutionId : null,
                    'institution_name' => $firstItem->causer->student->institution->name ?? 'Unknown',
                    'total_activities' => $group->sum('activity_count'),
                    'student_count'    => $group->count(),
                ];
            })
            ->values();


        /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */
        return response()->json([
            'status' => 'success',
            'message' => 'Session and usage analytics fetched successfully',
            'filters' => [
                'from' => $fromDate ?? 'all',
                'to'   => $toDate ?? 'all',
            ],
            'data' => [
                'login_count_per_student'      => $loginCountPerStudent,
                'average_session_time_minutes' => $averageSessionTime,
                'total_sessions_analyzed'      => count($sessionDurations),
                'active_users_per_day'         => $activeUsersPerDay,
                'active_users_per_week'        => $activeUsersPerWeek,
                'active_users_per_month'       => $activeUsersPerMonth,
                'institution_wise_activity'    => $institutionWiseActivity,
            ],
        ], 200);
    }

    public function getInstitutionLevelInsights(Request $request)
    {
        $fromDate = $request->from;
        $toDate = $request->to;

        // Get all institutions
        $institutions = Institution::all();

        $institutionInsights = $institutions->map(function ($institution) use ($fromDate, $toDate) {

            // Active students: students with any activity within date range
            $activeStudentsCount = Student::where('institution_id', $institution->id)
                ->whereHas('user.activities', function ($q) use ($fromDate, $toDate) {
                    $q->when($fromDate, fn($query) => $query->whereDate('created_at', '>=', $fromDate))
                        ->when($toDate, fn($query) => $query->whereDate('created_at', '<=', $toDate));
                })
                ->count();

            // Total students
            $totalStudents = Student::where('institution_id', $institution->id)->count();

            // Total activities by students
            $totalActivities = Activity::whereHasMorph(
                'causer',
                [\App\Models\User::class],
                fn($query) => $query->whereHas('student', fn($q) => $q->where('institution_id', $institution->id))
            )
                ->when($fromDate, fn($query) => $query->whereDate('created_at', '>=', $fromDate))
                ->when($toDate, fn($query) => $query->whereDate('created_at', '<=', $toDate))
                ->count();

            $averageEngagement = $totalStudents > 0 ? round($totalActivities / $totalStudents, 2) : 0;

            // Trainer activity
            $trainerIds = Trainer::where('institution_id', $institution->id)->pluck('id');

            $projectsCreated = Project::whereIn('created_by', $trainerIds)
                ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
                ->count();

            $examsCreated = Exam::whereIn('created_by', $trainerIds)
                ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
                ->count();

            $quizzesAdded = CourseQuize::whereIn('created_by', $trainerIds)
                ->when($fromDate, fn($q) => $q->whereDate('created_at', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->whereDate('created_at', '<=', $toDate))
                ->count();

            return [
                'institution_id' => $institution->id,
                'institution_name' => $institution->name,
                'active_students' => $activeStudentsCount,
                'total_students' => $totalStudents,
                'active_students_percentage' => $totalStudents > 0
                    ? round(($activeStudentsCount / $totalStudents) * 100, 2)
                    : 0,
                'average_student_engagement' => $averageEngagement,
                'total_activities' => $totalActivities,
                'trainer_activity' => [
                    'total_trainers' => $trainerIds->count(),
                    'projects_created' => $projectsCreated,
                    'exams_created' => $examsCreated,
                    'quizzes_added' => $quizzesAdded,
                    'total_content_created' => $projectsCreated + $examsCreated + $quizzesAdded,
                ],
            ];
        });

        // Summary statistics
        $summary = [
            'total_institutions' => $institutions->count(),
            'total_active_students' => $institutionInsights->sum('active_students'),
            'total_students_all_institutions' => $institutionInsights->sum('total_students'),
            'average_engagement_across_institutions' => $institutionInsights->avg('average_student_engagement'),
            'total_projects_created' => $institutionInsights->sum('trainer_activity.projects_created'),
            'total_exams_created' => $institutionInsights->sum('trainer_activity.exams_created'),
            'total_quizzes_added' => $institutionInsights->sum('trainer_activity.quizzes_added'),
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Institution-level insights fetched successfully',
            'filters' => [
                'from' => $fromDate ?? 'all',
                'to' => $toDate ?? 'all',
            ],
            'data' => [
                'summary' => $summary,
                'institutions' => $institutionInsights,
            ],
        ], 200);
    }


    public function getGenderBasedAnalytics(Request $request)
    {
        // Get date filters from request
        $fromDate = $request->from;
        $toDate = $request->to;

        // Total students by gender
        $maleStudents = Student::where('gender', 'male')->count();
        $femaleStudents = Student::where('gender', 'female')->count();
        $totalStudents = $maleStudents + $femaleStudents;

        // Male vs Female engagement ratio
        // Count activities by gender
        $maleActivities = Activity::whereHasMorph('causer', ['App\Models\User'], function ($query) use ($fromDate, $toDate) {
            $query->whereHas('student', function ($q) {
                $q->where('gender', 'male');
            });
        })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->count();

        $femaleActivities = Activity::whereHasMorph('causer', ['App\Models\User'], function ($query) use ($fromDate, $toDate) {
            $query->whereHas('student', function ($q) {
                $q->where('gender', 'female');
            });
        })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->count();

        // Calculate average engagement per student by gender
        $maleAverageEngagement = $maleStudents > 0 ? round($maleActivities / $maleStudents, 2) : 0;
        $femaleAverageEngagement = $femaleStudents > 0 ? round($femaleActivities / $femaleStudents, 2) : 0;

        // Calculate engagement ratio
        $engagementRatio = $femaleAverageEngagement > 0
            ? round($maleAverageEngagement / $femaleAverageEngagement, 2)
            : 0;

        // Course completion by gender
        $maleCoursesCompleted = Enrollment::where('status', 'completed')
            ->whereHas('student', function ($query) {
                $query->where('gender', 'male');
            })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('completed_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('completed_at', '<=', $toDate);
            })
            ->count();

        $femaleCoursesCompleted = Enrollment::where('status', 'completed')
            ->whereHas('student', function ($query) {
                $query->where('gender', 'female');
            })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('completed_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('completed_at', '<=', $toDate);
            })
            ->count();

        // Total enrollments by gender
        $maleTotalEnrollments = Enrollment::whereHas('student', function ($query) {
            $query->where('gender', 'male');
        })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->count();

        $femaleTotalEnrollments = Enrollment::whereHas('student', function ($query) {
            $query->where('gender', 'female');
        })
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('created_at', '>=', $fromDate);
            })
            ->when($toDate, function ($query, $toDate) {
                return $query->whereDate('created_at', '<=', $toDate);
            })
            ->count();

        // Calculate completion rates
        $maleCompletionRate = $maleTotalEnrollments > 0
            ? round(($maleCoursesCompleted / $maleTotalEnrollments) * 100, 2)
            : 0;

        $femaleCompletionRate = $femaleTotalEnrollments > 0
            ? round(($femaleCoursesCompleted / $femaleTotalEnrollments) * 100, 2)
            : 0;

        // Students who completed at least one course by gender
        $maleStudentsWithCompletedCourses = Student::where('gender', 'male')
            ->whereHas('enrollments', function ($query) use ($fromDate, $toDate) {
                $query->where('status', 'completed')
                    ->when($fromDate, function ($q, $fromDate) {
                        return $q->whereDate('completed_at', '>=', $fromDate);
                    })
                    ->when($toDate, function ($q, $toDate) {
                        return $q->whereDate('completed_at', '<=', $toDate);
                    });
            })
            ->count();

        $femaleStudentsWithCompletedCourses = Student::where('gender', 'female')
            ->whereHas('enrollments', function ($query) use ($fromDate, $toDate) {
                $query->where('status', 'completed')
                    ->when($fromDate, function ($q, $fromDate) {
                        return $q->whereDate('completed_at', '>=', $fromDate);
                    })
                    ->when($toDate, function ($q, $toDate) {
                        return $q->whereDate('completed_at', '<=', $toDate);
                    });
            })
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Gender-based analytics fetched successfully',
            'filters' => [
                'from' => $fromDate ?? 'all',
                'to' => $toDate ?? 'all',
            ],
            'data' => [
                'student_distribution' => [
                    'male_students' => $maleStudents,
                    'female_students' => $femaleStudents,
                    'total_students' => $totalStudents,
                    'male_percentage' => $totalStudents > 0 ? round(($maleStudents / $totalStudents) * 100, 2) : 0,
                    'female_percentage' => $totalStudents > 0 ? round(($femaleStudents / $totalStudents) * 100, 2) : 0,
                ],
                'engagement_metrics' => [
                    'male_total_activities' => $maleActivities,
                    'female_total_activities' => $femaleActivities,
                    'male_average_engagement' => $maleAverageEngagement,
                    'female_average_engagement' => $femaleAverageEngagement,
                    'engagement_ratio_male_to_female' => $engagementRatio,
                    'engagement_comparison' => $maleAverageEngagement > $femaleAverageEngagement
                        ? 'Male students are more engaged'
                        : ($femaleAverageEngagement > $maleAverageEngagement
                            ? 'Female students are more engaged'
                            : 'Equal engagement'),
                ],
                'course_completion' => [
                    'male' => [
                        'courses_completed' => $maleCoursesCompleted,
                        'total_enrollments' => $maleTotalEnrollments,
                        'completion_rate_percentage' => $maleCompletionRate,
                        'students_with_completed_courses' => $maleStudentsWithCompletedCourses,
                    ],
                    'female' => [
                        'courses_completed' => $femaleCoursesCompleted,
                        'total_enrollments' => $femaleTotalEnrollments,
                        'completion_rate_percentage' => $femaleCompletionRate,
                        'students_with_completed_courses' => $femaleStudentsWithCompletedCourses,
                    ],
                    'comparison' => [
                        'completion_rate_difference' => round($maleCompletionRate - $femaleCompletionRate, 2),
                        'better_performing_gender' => $maleCompletionRate > $femaleCompletionRate
                            ? 'male'
                            : ($femaleCompletionRate > $maleCompletionRate ? 'female' : 'equal'),
                    ],
                ],
            ],
        ], 200);
    }
}
