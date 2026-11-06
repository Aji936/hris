<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Jobs;

use App\Modules\Payroll\Services\PayrollService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMonthlyPayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly string $period,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $adminId,
        public readonly ?string $title = null,
    ) {
        $this->onQueue('payroll');
    }

    public function handle(PayrollService $payrollService): void
    {
        $payrollService->generatePayroll(
            period: $this->period,
            startDate: $this->startDate,
            endDate: $this->endDate,
            adminId: $this->adminId,
            title: $this->title,
        );
    }
}
