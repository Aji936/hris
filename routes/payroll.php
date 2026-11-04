<?php

declare(strict_types=1);

use App\Modules\Payroll\Controllers\SalaryManagementController;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('payroll')->name('payroll.')->group(function (): void {
    Route::get('/components', [SalaryManagementController::class, 'listComponents'])
        ->middleware('permission:'.PermissionName::PAYROLL_VIEW->value)
        ->name('components.index');

    Route::post('/components', [SalaryManagementController::class, 'storeComponent'])
        ->middleware('permission:'.PermissionName::PAYROLL_CREATE->value)
        ->name('components.store');

    Route::get('/structures', [SalaryManagementController::class, 'listStructures'])
        ->middleware('permission:'.PermissionName::PAYROLL_VIEW->value)
        ->name('structures.index');

    Route::post('/structures', [SalaryManagementController::class, 'storeStructure'])
        ->middleware('permission:'.PermissionName::PAYROLL_CREATE->value)
        ->name('structures.store');

    Route::get('/employee-salaries', [SalaryManagementController::class, 'listEmployeeSalaries'])
        ->middleware('permission:'.PermissionName::PAYROLL_VIEW->value)
        ->name('employee-salaries.index');

    Route::post('/employee-salaries', [SalaryManagementController::class, 'assignEmployeeSalary'])
        ->middleware('permission:'.PermissionName::PAYROLL_CREATE->value)
        ->name('employee-salaries.store');
});
