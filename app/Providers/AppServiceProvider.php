<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Modules\Attendance\Contracts\AttendanceRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveBalanceRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveRequestRepositoryInterface;
use App\Modules\Attendance\Contracts\LeaveTypeRepositoryInterface;
use App\Modules\Attendance\Contracts\ShiftRepositoryInterface;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Policies\AttendancePolicy;
use App\Modules\Attendance\Policies\LeaveRequestPolicy;
use App\Modules\Attendance\Repositories\AttendanceRepository;
use App\Modules\Attendance\Repositories\LeaveBalanceRepository;
use App\Modules\Attendance\Repositories\LeaveRequestRepository;
use App\Modules\Attendance\Repositories\LeaveTypeRepository;
use App\Modules\Attendance\Repositories\ShiftRepository;
use App\Modules\CoreHR\Contracts\DepartmentRepositoryInterface;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\CoreHR\Contracts\PositionRepositoryInterface;
use App\Modules\CoreHR\Models\Department;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Position;
use App\Modules\CoreHR\Policies\DepartmentPolicy;
use App\Modules\CoreHR\Policies\EmployeePolicy;
use App\Modules\CoreHR\Policies\PositionPolicy;
use App\Modules\CoreHR\Repositories\DepartmentRepository;
use App\Modules\CoreHR\Repositories\EmployeeRepository;
use App\Modules\CoreHR\Repositories\PositionRepository;
use App\Modules\Payroll\Contracts\EmployeeSalaryRepositoryInterface;
use App\Modules\Payroll\Contracts\PayrollComponentRepositoryInterface;
use App\Modules\Payroll\Contracts\PayrollRepositoryInterface;
use App\Modules\Payroll\Contracts\PayslipRepositoryInterface;
use App\Modules\Payroll\Contracts\SalaryStructureRepositoryInterface;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Models\PayrollComponent;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryStructure;
use App\Modules\Payroll\Policies\PayrollPolicy;
use App\Modules\Payroll\Policies\PayslipPolicy;
use App\Modules\Payroll\Repositories\EmployeeSalaryRepository;
use App\Modules\Payroll\Repositories\PayrollComponentRepository;
use App\Modules\Payroll\Repositories\PayrollRepository;
use App\Modules\Payroll\Repositories\PayslipRepository;
use App\Modules\Payroll\Repositories\SalaryStructureRepository;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Policies\RolePolicy;
use App\Modules\RBAC\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DepartmentRepositoryInterface::class, DepartmentRepository::class);
        $this->app->bind(PositionRepositoryInterface::class, PositionRepository::class);
        $this->app->bind(EmployeeRepositoryInterface::class, EmployeeRepository::class);
        $this->app->bind(ShiftRepositoryInterface::class, ShiftRepository::class);
        $this->app->bind(AttendanceRepositoryInterface::class, AttendanceRepository::class);
        $this->app->bind(LeaveTypeRepositoryInterface::class, LeaveTypeRepository::class);
        $this->app->bind(LeaveBalanceRepositoryInterface::class, LeaveBalanceRepository::class);
        $this->app->bind(LeaveRequestRepositoryInterface::class, LeaveRequestRepository::class);
        $this->app->bind(PayrollComponentRepositoryInterface::class, PayrollComponentRepository::class);
        $this->app->bind(SalaryStructureRepositoryInterface::class, SalaryStructureRepository::class);
        $this->app->bind(EmployeeSalaryRepositoryInterface::class, EmployeeSalaryRepository::class);
        $this->app->bind(PayrollRepositoryInterface::class, PayrollRepository::class);
        $this->app->bind(PayslipRepositoryInterface::class, PayslipRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(PayrollComponent::class, PayrollPolicy::class);
        Gate::policy(SalaryStructure::class, PayrollPolicy::class);
        Gate::policy(EmployeeSalary::class, PayrollPolicy::class);
        Gate::policy(Payroll::class, PayrollPolicy::class);
        Gate::policy(Payslip::class, PayslipPolicy::class);

        Gate::before(static function (User $user, string $ability): ?bool {
            if ($user->hasRole(RoleName::SUPERADMIN->value)) {
                return true;
            }

            return null;
        });
    }
}
