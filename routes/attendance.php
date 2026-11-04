<?php

declare(strict_types=1);

use App\Modules\Attendance\Controllers\AttendanceController;
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
});
