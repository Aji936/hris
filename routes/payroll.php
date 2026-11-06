<?php

declare(strict_types=1);

use App\Modules\Payroll\Controllers\PayrollBatchController;
use App\Modules\Payroll\Controllers\PayslipController;
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

    // Monthly Payroll Batches & Approval Engine
    Route::get('/batches', [PayrollBatchController::class, 'index'])
        ->middleware('permission:'.PermissionName::PAYROLL_VIEW->value)
        ->name('batches.index');

    Route::post('/batches/generate', [PayrollBatchController::class, 'generate'])
        ->middleware('permission:'.PermissionName::PAYROLL_CREATE->value)
        ->name('batches.generate');

    Route::get('/batches/{id}', [PayrollBatchController::class, 'show'])
        ->whereNumber('id')
        ->middleware('permission:'.PermissionName::PAYROLL_VIEW->value)
        ->name('batches.show');

    Route::post('/batches/{id}/approve', [PayrollBatchController::class, 'approve'])
        ->whereNumber('id')
        ->name('batches.approve');

    // Employee Self-Service (ESS) & Payslip Access
    Route::get('/my-payslips', [PayslipController::class, 'myPayslips'])
        ->name('payslips.mine');

    Route::get('/payslips/{id}', [PayslipController::class, 'show'])
        ->whereNumber('id')
        ->name('payslips.show');

    Route::get('/payslips/{id}/download', [PayslipController::class, 'download'])
        ->whereNumber('id')
        ->name('payslips.download');
});
