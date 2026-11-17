<?php

use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\PermissionName;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        $actor = $request->user();
        $canViewDirectory = (bool) $actor?->can(PermissionName::EMPLOYEE_VIEW->value);

        $approachingRetirements = [];

        if ($canViewDirectory) {
            $today = CarbonImmutable::now()->startOfDay()->toDateString();
            $sixMonthsAhead = CarbonImmutable::now()->startOfDay()->addDays(180)->toDateString();

            $approachingRetirements = Employee::query()
                ->with(['department:id,code,name', 'position:id,code,title'])
                ->whereIn('status', [
                    EmploymentStatus::PERMANENT->value,
                    EmploymentStatus::PROBATION->value,
                    EmploymentStatus::CONTRACT->value,
                ])
                ->whereNotNull('retirement_date')
                ->whereDate('retirement_date', '>=', $today)
                ->whereDate('retirement_date', '<=', $sixMonthsAhead)
                ->orderBy('retirement_date')
                ->get()
                ->map(static fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'full_name' => $employee->full_name,
                    'department_name' => $employee->department?->name,
                    'position_title' => $employee->position?->title,
                    'date_of_birth' => $employee->date_of_birth?->toDateString(),
                    'retirement_date' => $employee->retirement_date?->toDateString(),
                    'age' => $employee->age,
                    'days_until_retirement' => $employee->days_until_retirement,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('dashboard', [
            'approachingRetirements' => $approachingRetirements,
        ]);
    })->name('dashboard');
});

require __DIR__.'/payroll.php';
require __DIR__.'/attendance.php';
require __DIR__.'/core_hr.php';
require __DIR__.'/rbac.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
