<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AdminDashBoardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\ExpertControllers\CourseContentWizardController;
use App\Http\Controllers\ExpertControllers\CourseLectureController;
use App\Http\Controllers\ExpertControllers\CourseLectureMaterialController;
use App\Http\Controllers\ExpertControllers\CourseModuleController;
use App\Http\Controllers\ExpertControllers\ExpertDashboardController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentControllers\EnrollmentController;
use App\Http\Controllers\StudentControllers\ExamSubmissionController;
use App\Http\Controllers\StudentControllers\ProjectSubmissionController;
use App\Http\Controllers\StudentControllers\QuizSubmissionController;
use App\Http\Controllers\StudentControllers\ScheduleController;
use App\Http\Controllers\StudentControllers\StudentDashboardController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\TrainerControllers\CourseQuizeController;
use App\Http\Controllers\TrainerControllers\EvaluationController;
use App\Http\Controllers\TrainerControllers\ExamController;
use App\Http\Controllers\TrainerControllers\ProjectController;
use App\Http\Controllers\TrainerControllers\TrainerDashboardController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'api',
], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('subscribe', [AuthController::class, 'register']);
    Route::post('unsubscribe', [AuthController::class, 'unsubscribe']);

    /**
     *  Other AuthController routes
     */
    Route::post('create-password', [AuthController::class, 'create_password'])->middleware('auth:api');
    Route::post('update-profile-image', [AuthController::class, 'update_profile_image'])->middleware('auth:api');
    Route::post('remove-profile-image', [AuthController::class, 'remove_profile_image'])->middleware('auth:api');

    Route::post('update-profile', [AuthController::class, 'update_profile'])->middleware('auth:api');

    Route::post('reset-password', [ResetPasswordController::class, 'reset_password']);
    Route::post('change-password', [ResetPasswordController::class, 'change_password'])->middleware('auth:api');

    Route::post('verify-otp', [OTPController::class, 'verify_otp']);
    Route::post('resend-otp', [OTPController::class, 'resend_otp']);

    Route::group([
        'middleware' => 'auth:api',
    ], function () {
        /**
         * Common Endpoints */
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/my-profile', [AuthController::class, 'profile']);

        Route::get('my-activity-logs', [ActivityLogController::class, 'myActivityLog']);


        Route::post('test-notification', [NotificationController::class, 'testNotification']);

        Route::get('my-notifications', [NotificationController::class, 'getNotification']);
        Route::post('read-all-notifications', [NotificationController::class, 'readNotifications']);
        Route::post('read-notification/{id}', [NotificationController::class, 'readNotification']);


        /**
         * Admin Endpoints */
        Route::resource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index']);


        Route::post('roles/{role}/attach-permissions', [RolePermissionController::class, 'attachPermission']);
        Route::post('roles/{role}/detach-permissions', [RolePermissionController::class, 'detachPermission']);
        Route::post('assign-role/{user}', [RoleController::class, 'assignRole']);

        Route::get('get-activity-logs', [ActivityLogController::class, 'index']);
        Route::get('engagement-overview', [ActivityLogController::class, 'getEngagmentOverview']);
        Route::get('learning-progress', [ActivityLogController::class, 'getLearningProgress']);
        Route::get('session-usage-analytics', [ActivityLogController::class, 'getSessionUsageAnalytics']);
        Route::get('gender-based-analytics', [ActivityLogController::class, 'getGenderBasedAnalytics']);

        Route::get('status-overview', [AdminDashBoardController::class, 'getStatusOverview']);
        Route::get('latest-courses', [AdminDashBoardController::class, 'getLatestCourses']);

        /**
         * Admin Controller Course Endpoints */
        Route::resource('courses', CourseController::class);
        Route::get('courses/expert/{expert}', [CourseController::class, 'getCourseByExpert']);
        Route::get('courses/trainer/{trainer}', [CourseController::class, 'getCourseByTrainer']);

        /**
         * Admin Controller Expert Endpoints */
        Route::resource('experts', ExpertController::class);
        Route::get('experts/course/{course}', [ExpertController::class, 'getExpertByCourse']);
        Route::get('expert/index-experts-without-course', [ExpertController::class, 'indexExpertsWithoutCourse']);

        /**
         * Admin ControllerInstitution Endpoints */
        Route::resource('institutions', InstitutionController::class);
        Route::post('institutions/{institution}/bulk-import-students', [InstitutionController::class, 'bulkImportStudent']);
        Route::post('institutions/{institution}/add-student', [InstitutionController::class, 'addStudent']);
        Route::get('institutions/{institution}/students', [InstitutionController::class, 'getStudents']);
        Route::post('institutions/{institution}/add-trainer', [InstitutionController::class, 'addTrainer']);
        Route::post('institutions/{institution}/bulk-import-trainers', [InstitutionController::class, 'bulkImportTrainer']);
        Route::get('institutions/{institution}/trainers', [InstitutionController::class, 'getTrainers']);

        /**
         * Admin Controller Trainer Endpoints */
        Route::resource('trainers', TrainerController::class);
        Route::post('trainers/bulk-import', [TrainerController::class, 'bulkImportTrainer']);
        Route::get('trainers/institution/{institution}', [TrainerController::class, 'getTrainerByInstitution']);
        Route::get('trainers/course/{course}', [TrainerController::class, 'getTrainersByCourse']);
        Route::get('trainer/index-trainers-without-course', [TrainerController::class, 'indexTrainersWithoutCourse']);
        Route::get('trainer/index-trainers-without-institution', [TrainerController::class, 'indexTrainerWithoutInstitution']);

        /**
         * Admin Controller Student Endpoints */
        Route::resource('students', StudentController::class);
        Route::post('students/bulk-import', [StudentController::class, 'bulkImport']);
        Route::get('students/institution/{institution}', [StudentController::class, 'getStudentByInstitution']);


        /**
         * Expert Endpoints  */

        /**
         * Dashboard Controller */

        Route::get('expert/status-overview', [ExpertDashboardController::class, 'getStatusOverview']);
        Route::get('expert/assigned-courses', [ExpertDashboardController::class, 'getAssignedCourses']);
        Route::get('expert/courses/{course}', [ExpertDashboardController::class, 'show']);

        /**
         * Course Content Wizard Controller */
        Route::post('expert/courses/{course}/content-wizard', [CourseContentWizardController::class, 'storeWizard']);

        /**
         * Course Module Controller */

        Route::get('expert/courses/{course}/modules', [CourseModuleController::class, 'index']);
        Route::post('expert/courses/{course}/modules', [CourseModuleController::class, 'store']);
        Route::get('expert/courses/{course}/modules/{module}', [CourseModuleController::class, 'show']);
        Route::put('expert/courses/{course}/modules/{module}', [CourseModuleController::class, 'update']);
        Route::delete('expert/course/{course}/module/{module}', [CourseModuleController::class, 'destroy']);

        /**
         * Course Lecture Controller */
        Route::get('expert/courses/{course}/modules/{module}/lectures', [CourseLectureController::class, 'index']);
        Route::post('expert/courses/{course}/modules/{module}/lectures', [CourseLectureController::class, 'store']);
        Route::get('expert/courses/{course}/modules/{module}/lectures/{lecture}', [CourseLectureController::class, 'show']);
        Route::put('expert/courses/{course}/modules/{module}/lectures/{lecture}', [CourseLectureController::class, 'update']);
        Route::delete('expert/courses/{course}/modules/{module}/lectures/{lecture}', [CourseLectureController::class, 'destroy']);


        /**
         * Course Lecture Material Controller
         */

        Route::get('expert/courses/{course}/modules/{module}/lectures/{lecture}/materials', [CourseLectureMaterialController::class, 'index']);
        Route::post('expert/courses/{course}/modules/{module}/lectures/{lecture}/materials', [CourseLectureMaterialController::class, 'store']);
        Route::get('expert/courses/{course}/modules/{module}/lectures/{lecture}/materials/{material}', [CourseLectureMaterialController::class, 'show']);
        Route::put('expert/courses/{course}/modules/{module}/lectures/{lecture}/materials/{material}', [CourseLectureMaterialController::class, 'update']);
        Route::delete('expert/courses/{course}/modules/{module}/lectures/{lecture}/materials/{material}', [CourseLectureMaterialController::class, 'destroy']);


        /**
         * Trainer Endpoints  */
        Route::get('trainer/status-overview', [TrainerDashboardController::class, 'getStatusOverview']);
        Route::get('trainer/assigned-courses', [TrainerDashboardController::class, 'getAssignedCourses']);
        Route::get('trainer/courses/{course}', [TrainerDashboardController::class, 'show']);
        Route::get('trainer/assessments-overview', [TrainerDashboardController::class, 'getAssessmentsOveriew']);


        /**
         * Course Quiz Controller
         */

        Route::get('trainer/quizzes', [CourseQuizeController::class, 'allQuizzes']);
        Route::get('trainer/courses/{course}/quizzes', [CourseQuizeController::class, 'getQuizzesByCourse']);
        Route::get('trainer/courses/{course}/modules/{module}/quizzes', [CourseQuizeController::class, 'index']);
        Route::post('trainer/courses/{course}/modules/{module}/quizzes', [CourseQuizeController::class, 'store']);
        Route::get('trainer/courses/{course}/modules/{module}/quizzes/{quiz}', [CourseQuizeController::class, 'show']);
        Route::put('trainer/courses/{course}/modules/{module}/quizzes/{quiz}', [CourseQuizeController::class, 'update']);
        Route::delete('trainer/courses/{course}/modules/{module}/quizzes/{quiz}', [CourseQuizeController::class, 'destroy']);


        /**
         * Course Project Controller
         */
        Route::get('trainer/courses/{course}/projects', [ProjectController::class, 'index']);
        Route::post('trainer/courses/{course}/projects', [ProjectController::class, 'store']);
        Route::get('trainer/courses/{course}/projects/{project}', [ProjectController::class, 'show']);
        Route::put('trainer/courses/{course}/projects/{project}', [ProjectController::class, 'update']);
        Route::delete('trainer/courses/{course}/projects/{project}', [ProjectController::class, 'destroy']);

        Route::get('trainer/projects', [ProjectController::class, 'allProjects']);
        Route::get('trainer/projects/{project}/details', [ProjectController::class, 'getProjectDetails']);
        Route::get('trainer/projects/evaluated', [ProjectController::class, 'getEvaluatedProjects']);

        /**
         * Course Exam Controller
         */
        Route::get('trainer/courses/{course}/exams', [ExamController::class, 'index']);
        Route::get('trainer/exams', [ExamController::class, 'allExams']);
        Route::post('trainer/courses/{course}/exams', [ExamController::class, 'store']);
        Route::get('trainer/courses/{course}/exams/{exam}', [ExamController::class, 'show']);
        Route::put('trainer/courses/{course}/exams/{exam}', [ExamController::class, 'update']);
        Route::delete('trainer/courses/{course}/exams/{exam}', [ExamController::class, 'destroy']);

        Route::get('trainer/exams/{exam}/details', [ExamController::class, 'getEvaluatedExamDetails']);
        Route::get('trainer/exams/evaluated', [ExamController::class, 'getEvaluatedExams']);
        Route::get('trainer/exams/failed-students', [ExamController::class, 'getFailedStudentOnExam']);
        /**
         * Evaluation Controller
         */
        Route::get('trainer/projects/{project}/submissions', [EvaluationController::class, 'getProjectSubmissions']);
        Route::post('trainer/projects/submissions/{enrollmentProject}/evaluate', [EvaluationController::class, 'evaluateProject']);
        Route::get('trainer/failed-students/projects', [EvaluationController::class, 'getFailedStudentOnProject']);
        Route::post('trainer/assign-remedial-project', [EvaluationController::class, 'assignRemedialProject']);

        Route::get('trainer/quizzes/{quiz}/submissions', [EvaluationController::class, 'getQuizSubmissions']);
        Route::post('trainer/quizzes/submissions/{enrollmentQuiz}/evaluate', [EvaluationController::class, 'evaluateQuiz']);
        Route::get('trainer/failed-students/quizzes', [EvaluationController::class, 'getFailedStudentOnQuiz']);
        Route::post('trainer/assign-remedial-quiz', [EvaluationController::class, 'assignRemedialQuiz']);

        Route::get('trainer/exams/{exam}/submissions', [EvaluationController::class, 'getExamSubmissions']);
        Route::post('trainer/exams/submissions/{enrollmentExam}/evaluate', [EvaluationController::class, 'evaluateExam']);
        Route::get('trainer/failed-students/exams', [EvaluationController::class, 'getFailedStudentOnExam']);
        Route::post('trainer/assign-remedial-exam', [EvaluationController::class, 'assignRemedialExam']);

        Route::get('trainer/schedule', [TrainerDashboardController::class, 'getSchedule']);
        Route::get('trainer/student-progress', [TrainerDashboardController::class, 'getStudentProgress']);


        /**
         * Student Endpoints  */

        /**
         * Student Dashboard Controller
         */

        Route::get('students/dashboard/status-overview', [StudentDashboardController::class, 'getStatusOverview']);
        Route::get('student/courses', [StudentDashboardController::class, 'courseIndex']);
        Route::get('students/courses/{course}/details', [StudentDashboardController::class, 'courseShow']);
        Route::get('students/dashboard/progress-average', [StudentDashboardController::class, 'getCourseProgressAverage']);

        /**
         * Enrollment Controller
         */
        Route::post('students/start-learning', [EnrollmentController::class, 'enroll']);
        Route::get('students/my-enrollments', [EnrollmentController::class, 'myEnrollment']);
        Route::get('students/enrollments/{enrollment}/progress', [EnrollmentController::class, 'getProgress']);
        Route::post('students/enrollments/{enrollment}/lectures/{enrollmentLecture}/watch', [EnrollmentController::class, 'watchLecture']);
        Route::post('students/enrollments/{enrollment}/materials/{material}/view', [EnrollmentController::class, 'viewMaterial']);
        Route::post('enrollments/{enrollment}/materials/{material}/download', [EnrollmentController::class, 'downloadMaterial']);

        Route::get('student/schedule', [ScheduleController::class, 'getSchedule']);

        /**
         * Project Submission Controller
         */
        Route::get('student/project-submissions', [ProjectSubmissionController::class, 'index']);
        Route::get('student/projects', [ProjectSubmissionController::class, 'allProjects']);
        Route::get('student/upcoming-projects', [ProjectSubmissionController::class, 'getUpcomingProjects']);
        Route::get('student/projects/evaluated', [ProjectSubmissionController::class, 'getEvaluatedProjects']);
        Route::post('student/enrollment-projects/{enrollmentProject}/submit', [ProjectSubmissionController::class, 'submit']);
        Route::get('student/enrollment-projects/{enrollmentProject}/submission', [ProjectSubmissionController::class, 'show']);
        Route::get('student/projects/remedial', [ProjectSubmissionController::class, 'getRemedialProjects']);

        /**
         * Exam Submission Controller
         */
        Route::get('student/exam-submissions', [ExamSubmissionController::class, 'index']);
        Route::get('student/exams', [ExamSubmissionController::class, 'allExams']);
        Route::get('student/exams/evaluated', [ExamSubmissionController::class, 'getEvaluatedExams']);
        Route::post('student/enrollment-exams/{enrollmentExam}/submit', [ExamSubmissionController::class, 'submit']);
        Route::get('student/enrollment-exams/{enrollmentExam}/submission', [ExamSubmissionController::class, 'show']);
        Route::get('student/exams/remedial', [ExamSubmissionController::class, 'getRemedialExams']);

        /**
         * Quiz Submission Controller
         */
        Route::get('student/quiz-submissions', [QuizSubmissionController::class, 'index']);
        Route::get('student/quizzes', [QuizSubmissionController::class, 'allQuizzes']);
        Route::get('student/quizzes/evaluated', [QuizSubmissionController::class, 'getEvaluatedQuizzes']);
        Route::post('student/enrollment-quizzes/{enrollmentQuiz}/submit', [QuizSubmissionController::class, 'submit']);
        Route::get('student/enrollment-quizzes/{enrollmentQuiz}/submission', [QuizSubmissionController::class, 'show']);
    });
});
