<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AlarmController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PersonnelAttendanceController;
use App\Http\Controllers\Admin\PersonnelController;
use App\Http\Controllers\Api\LocationController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/locations', [LocationController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'me']);
    Route::post('/update-profile', [UserController::class, 'updateProfile']);
    Route::apiResource('reports', ReportController::class);
    Route::apiResource('alarms', AlarmController::class);
    // This route is now protected and will correctly identify the logged-in personnel
    Route::post('/personnel/update-location', [PersonnelController::class, 'updateLocation']);
    Route::get('/personnel/monthly-summary', [PersonnelAttendanceController::class, 'monthlySummary']);
    Route::get('/personnel/attendance/history', [PersonnelAttendanceController::class, 'history']);
    Route::get('/personnel/attendance/status', [PersonnelAttendanceController::class, 'checkStatus']);
    Route::get('/personnel/schedules', [PersonnelAttendanceController::class, 'mySchedules']);
    Route::get('/personnel/colleagues', [PersonnelAttendanceController::class, 'colleagues']);
    
    // Tracking routes for both roles
    Route::get('/tracker/personnel', [UserController::class, 'activePersonnel']);


    // Attendance Toggle Route
    Route::post('/personnel/attendance/toggle', function (Request $request) {
        $personnel = $request->user()->personnel;

        if (!$personnel) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied: You are not registered as personnel.'
            ], 403);
        }

        return app(PersonnelAttendanceController::class)->toggle($request, $personnel);
    });
});