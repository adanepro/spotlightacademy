<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExpertController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
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

        /**
         * Course Endpoints */
        Route::resource('courses', CourseController::class);

        /**
         * Expert Endpoints */
        Route::get('experts', [ExpertController::class, 'index']);
        Route::get('expert/{expert}', [ExpertController::class, 'show']);
        Route::post('expert', [ExpertController::class, 'store']);
        Route::put('expert/{expert}', [ExpertController::class, 'update']);

    });
});
