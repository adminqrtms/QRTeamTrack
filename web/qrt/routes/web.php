<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\PersonnelController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\ResidentController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Models\Personnel;
use App\Models\Location;
use App\Models\Schedule;
use App\Models\Report;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('admin.dashboard');
    }
    return view('home');
});

Auth::routes();

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('admin.dashboard');
    })->name('dashboard');

    Route::get('/home', function () {
        return redirect()->route('admin.dashboard');
    })->name('home');

    // Admin Routes
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', function () {
            return view('admin.dashboard', [
                'personnelCount' => Personnel::count(),
                'locationCount' => Location::count(),
                'scheduleCount' => Schedule::count(),
                'residentCount' => \App\Models\User::where('role', 'resident')->count(),
                'recentReports' => Report::latest()->take(5)->get(),
            ]);
        })->name('dashboard');

        Route::get('personnels/batch-create', [PersonnelController::class, 'batchCreate'])->name('personnels.batch_create');
        Route::post('personnels/batch-store', [PersonnelController::class, 'batchStore'])->name('personnels.batchStore');
        Route::get('personnels/exportGeneratedAccounts', [PersonnelController::class, 'exportGeneratedAccounts'])->name('personnels.exportGeneratedAccounts');
        Route::get('personnels/monitor', [PersonnelController::class, 'monitor'])->name('personnels.monitor');
        Route::post('personnels/{id}/toggle-status', [PersonnelController::class, 'toggleStatus'])->name('personnels.toggleStatus');
        Route::get('personnels/{id}/attendance', [PersonnelController::class, 'attendance'])->name('personnels.attendance');
        Route::resource('personnels', PersonnelController::class);

        // Resident Management
        Route::resource('residents', ResidentController::class)->only(['index', 'show', 'destroy']);

        // Schedule Routes
        Route::resource('schedules', ScheduleController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        // Location Routes
        Route::resource('locations', LocationController::class);

        // Report Routes
        Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
        Route::patch('reports/{id}/status', [ReportController::class, 'updateStatus'])->name('reports.updateStatus');
        Route::resource('reports', ReportController::class)->only(['index']);
        
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
        Route::post('reports/{report}/status', [ReportController::class, 'updateStatus'])->name('reports.update-status');
        Route::get('reports-monthly', [ReportController::class, 'monthly'])->name('reports.monthly');

        // Audit Logs
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // Personnel Routes
    Route::prefix('personnel')->group(function () {
        // Add personnel routes here
    });

    // Resident Routes
    Route::prefix('resident')->group(function () {
        // Add resident routes here
    });
});
