<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckRetiringEmployeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hris:check-retiring-employees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transition active employees who have reached their retirement date (55 years) to RETIRED status and report upcoming retirements.';

    public function handle(AuditLogService $auditLogService): int
    {
        $today = CarbonImmutable::now()->startOfDay();
        $todayDate = $today->toDateString();
        $warningHorizonDate = $today->addDays(180)->toDateString();

        $dueEmployees = Employee::query()
            ->whereIn('status', [
                EmploymentStatus::PERMANENT->value,
                EmploymentStatus::PROBATION->value,
                EmploymentStatus::CONTRACT->value,
            ])
            ->whereNotNull('retirement_date')
            ->whereDate('retirement_date', '<=', $todayDate)
            ->get();

        $retiredCount = 0;

        foreach ($dueEmployees as $employee) {
            DB::transaction(function () use ($employee, $auditLogService, $todayDate, &$retiredCount): void {
                $previousStatus = $employee->status->value;

                $employee->update([
                    'status' => EmploymentStatus::RETIRED->value,
                ]);

                $auditLogService->record(
                    event: 'employee.retired',
                    auditable: $employee,
                    oldValues: [
                        'status' => $previousStatus,
                        'retirement_date' => $employee->retirement_date?->toDateString(),
                    ],
                    newValues: [
                        'status' => EmploymentStatus::RETIRED->value,
                        'retired_on' => $todayDate,
                    ],
                );

                $retiredCount++;
            });
        }

        $approachingCount = Employee::query()
            ->whereIn('status', [
                EmploymentStatus::PERMANENT->value,
                EmploymentStatus::PROBATION->value,
                EmploymentStatus::CONTRACT->value,
            ])
            ->whereNotNull('retirement_date')
            ->whereDate('retirement_date', '>', $todayDate)
            ->whereDate('retirement_date', '<=', $warningHorizonDate)
            ->count();

        $this->info("Processed retirements: {$retiredCount} transitioned to RETIRED, {$approachingCount} approaching retirement within 180 days.");

        return self::SUCCESS;
    }
}
