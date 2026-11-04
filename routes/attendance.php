<?php

declare(strict_types=1);

use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\LeaveRequestController;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('attendance')->name('attendance.')->group(function (): void {
    Route::get('/records', [AttendanceController::class, 'index'])
        ->middleware('permission:'.PermissionName::ATTENDANCE_VIEW->value)
        ->name('records.index');

    Route::post('/clock-in', [AttendanceController::class, 'clockIn'])
        ->name('clock-in');

    Route::post('/clock-out', [AttendanceController::class, 'clockOut'])
        ->name('clock-out');

    Route::prefix('leave-requests')->name('leave-requests.')->group(function (): void {
        Route::get('/', [LeaveRequestController::class, 'index'])
            ->name('index');

        Route::post('/', [LeaveRequestController::class, 'store'])
            ->name('store');

        Route::get('/{id}', [LeaveRequestController::class, 'show'])
            ->whereNumber('id')
            ->name('show');

        Route::post('/{id}/approve', [LeaveRequestController::class, 'approve'])
            ->whereNumber('id')
            ->name('approve');

        Route::post('/{id}/reject', [LeaveRequestController::class, 'reject'])
            ->whereNumber('id')
            ->name('reject');
    });
});
