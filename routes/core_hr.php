<?php

declare(strict_types=1);

use App\Modules\CoreHR\Controllers\EmployeeController;
use App\Modules\CoreHR\Controllers\OrganizationMasterController;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('core-hr')->name('core-hr.')->group(function (): void {
    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware('permission:'.PermissionName::EMPLOYEE_VIEW->value)
        ->name('employees.index');

    Route::post('/employees', [EmployeeController::class, 'store'])
        ->middleware('permission:'.PermissionName::EMPLOYEE_CREATE->value)
        ->name('employees.store');

    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
        ->whereNumber('employee')
        ->name('employees.show');

    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
        ->whereNumber('employee')
        ->middleware('permission:'.PermissionName::EMPLOYEE_UPDATE->value)
        ->name('employees.edit');

    Route::match(['put', 'patch'], '/employees/{employee}', [EmployeeController::class, 'update'])
        ->whereNumber('employee')
        ->middleware('permission:'.PermissionName::EMPLOYEE_UPDATE->value)
        ->name('employees.update');

    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
        ->whereNumber('employee')
        ->middleware('permission:'.PermissionName::EMPLOYEE_DELETE->value)
        ->name('employees.destroy');

    Route::match(['put', 'patch'], '/departments/{department}', [OrganizationMasterController::class, 'updateDepartment'])
        ->whereNumber('department')
        ->middleware('permission:'.PermissionName::DEPARTMENT_UPDATE->value)
        ->name('departments.update');

    Route::match(['put', 'patch'], '/positions/{position}', [OrganizationMasterController::class, 'updatePosition'])
        ->whereNumber('position')
        ->middleware('permission:'.PermissionName::POSITION_UPDATE->value)
        ->name('positions.update');
});
