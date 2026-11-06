<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\Payroll;
use App\Shared\Support\MoneyHelper;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollReportService
{
    /**
     * Stream a monthly payroll summary report as an RFC-4180 CSV download for HR & Finance analytics.
     */
    public function streamBatchSummaryCsv(Payroll $payroll): StreamedResponse
    {
        $payroll->loadMissing(['payslips']);

        $periodSlug = preg_replace('/[^A-Za-z0-9\-]/', '', $payroll->payroll_period) ?: 'batch';
        $filename = "payroll-summary-{$periodSlug}.csv";

        return response()->streamDownload(static function () use ($payroll): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'Payslip Number',
                'Payroll Period',
                'Employee Code',
                'Employee Name',
                'Department',
                'Position',
                'Bank Name',
                'Bank Account',
                'Payment Method',
                'Present Days',
                'Late Minutes',
                'Absent Days',
                'Unpaid Leave Days',
                'Basic Salary',
                'Total Allowance',
                'Gross Salary',
                'Total Deduction',
                'Net Salary',
                'Status',
            ]);

            foreach ($payroll->payslips as $payslip) {
                $snapshot = $payslip->employee_snapshot ?? [];
                $attendance = $payslip->attendance_summary ?? [];
                $gross = MoneyHelper::add((string) $payslip->basic_salary, (string) $payslip->total_allowance);

                fputcsv($handle, [
                    $payslip->payslip_number,
                    $payroll->payroll_period,
                    $snapshot['employee_code'] ?? '',
                    $snapshot['full_name'] ?? '',
                    $snapshot['department_name'] ?? '',
                    $snapshot['position_title'] ?? '',
                    $snapshot['bank_name'] ?? '',
                    $snapshot['bank_account_number'] ?? '',
                    $snapshot['payment_method'] ?? '',
                    $attendance['present_days'] ?? 0,
                    $attendance['late_minutes'] ?? 0,
                    $attendance['absent_days'] ?? 0,
                    $attendance['unpaid_leave_days'] ?? 0,
                    (string) $payslip->basic_salary,
                    (string) $payslip->total_allowance,
                    $gross,
                    (string) $payslip->total_deduction,
                    (string) $payslip->net_salary,
                    $payslip->status->value,
                ]);
            }

            fputcsv($handle, [
                'TOTAL',
                $payroll->payroll_period,
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                (string) $payroll->total_gross_amount,
                (string) $payroll->total_deduction_amount,
                (string) $payroll->total_net_amount,
                $payroll->status->value,
            ]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
