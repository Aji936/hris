<?php

declare(strict_types=1);

use App\Modules\RBAC\Controllers\RoleController;
use App\Modules\RBAC\Enums\PermissionName;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('rbac')->name('rbac.')->group(function (): void {
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:'.PermissionName::ROLE_VIEW->value)
        ->name('roles.index');

    Route::put('/users/{user}/roles', [RoleController::class, 'syncUserRoles'])
        ->middleware('permission:'.PermissionName::ROLE_MANAGE->value)
        ->name('users.roles.sync');
});
