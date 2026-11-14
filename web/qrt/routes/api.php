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
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\AppUpdateController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/locations', [LocationController::class, 'index']);

// In-app updates (public, so the login screen can update too)
Route::get('/app/latest', [AppUpdateController::class, 'latest']);
Route::get('/app/releases/{id}/download', [AppUpdateController::class, 'download'])->whereNumber('id');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [UserController::class, 'me']);
    Route::post('/update-profile', [UserController::class, 'updateProfile']);
    Route::apiResource('reports', ReportController::class);
    Route::apiResource('alarms', AlarmController::class);
    // Team response and mutual resolution (resident confirms "resolved")
    Route::post('/reports/{id}/join', [ReportController::class, 'join'])->whereNumber('id');
    Route::post('/reports/{id}/resolution', [ReportController::class, 'answerResolution'])->whereNumber('id');
    Route::post('/alarms/{id}/join', [AlarmController::class, 'join'])->whereNumber('id');
    Route::post('/alarms/{id}/resolution', [AlarmController::class, 'answerResolution'])->whereNumber('id');
    // Stations with on-duty personnel (resident SOS when no responder is nearby)
    Route::get('/stations/available', [AlarmController::class, 'availableStations']);
    // This route is now protected and will correctly identify the logged-in personnel
    Route::post('/personnel/update-location', [PersonnelController::class, 'updateLocation']);
    Route::get('/personnel/monthly-summary', [PersonnelAttendanceController::class, 'monthlySummary']);
    Route::get('/personnel/attendance/history', [PersonnelAttendanceController::class, 'history']);
    Route::get('/personnel/attendance/status', [PersonnelAttendanceController::class, 'checkStatus']);
    Route::get('/personnel/schedules', [PersonnelAttendanceController::class, 'mySchedules']);
    Route::get('/personnel/colleagues', [PersonnelAttendanceController::class, 'colleagues']);
    
    // Tracking routes for both roles
    Route::get('/tracker/personnel', [UserController::class, 'activePersonnel']);

    // Push notification tokens (Firebase)
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    // Messaging between residents and personnel
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show'])->whereNumber('id');
    Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages'])->whereNumber('id');
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage'])->whereNumber('id');
    Route::get('/conversations/{id}/messages/{messageId}/image', [ConversationController::class, 'messageImage'])->whereNumber(['id', 'messageId']);
    Route::post('/conversations/{id}/read', [ConversationController::class, 'markRead'])->whereNumber('id');

    // Audio/video calls (LiveKit)
    Route::post('/conversations/{id}/calls', [CallController::class, 'start'])->whereNumber('id');
    Route::get('/calls/{id}', [CallController::class, 'show'])->whereNumber('id');
    Route::post('/calls/{id}/accept', [CallController::class, 'accept'])->whereNumber('id');
    Route::post('/calls/{id}/decline', [CallController::class, 'decline'])->whereNumber('id');
    Route::post('/calls/{id}/end', [CallController::class, 'end'])->whereNumber('id');


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