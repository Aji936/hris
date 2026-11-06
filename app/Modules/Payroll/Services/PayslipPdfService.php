<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayslipItem;
use App\Shared\Exceptions\DomainException;
use App\Shared\Support\MoneyHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PayslipPdfService
{
    /**
     * Render a published or paid payslip into an official A4 PDF download response.
     */
    public function download(Payslip $payslip): Response
    {
        if (! $payslip->status->isEmployeeVisible()) {
            throw new DomainException('Cannot generate official PDF for an unpublished draft payslip.');
        }

        $payslip->loadMissing(['payroll', 'items']);

        $allowances = $payslip->items
            ->filter(static fn (PayslipItem $item): bool => $item->type === PayrollComponentType::ALLOWANCE)
            ->values();

        $deductions = $payslip->items
            ->filter(static fn (PayslipItem $item): bool => $item->type === PayrollComponentType::DEDUCTION)
            ->values();

        $grossSalary = MoneyHelper::add((string) $payslip->basic_salary, (string) $payslip->total_allowance);

        $pdf = Pdf::loadView('payroll.payslip-pdf', [
            'companyName' => (string) config('app.name', 'HRIS Enterprise Indonesia'),
            'payslip' => $payslip,
            'payroll' => $payslip->payroll,
            'snapshot' => $payslip->employee_snapshot ?? [],
            'attendance' => $payslip->attendance_summary ?? [],
            'allowances' => $allowances,
            'deductions' => $deductions,
            'grossSalary' => $grossSalary,
            'generatedAt' => now()->format('d M Y H:i'),
            'formatRupiah' => fn (string|int $amount): string => $this->formatRupiah($amount),
        ])->setPaper('a4', 'portrait');

        $filename = strtolower($payslip->payslip_number).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Format exact DECIMAL(15, 2) string into Indonesian Rupiah currency notation without float conversion.
     */
    public function formatRupiah(string|int $amount): string
    {
        $normalized = MoneyHelper::normalize($amount);
        $isNegative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '-');

        [$integerPart, $decimalPart] = explode('.', $unsigned);
        $formattedInteger = implode('.', str_split(strrev($integerPart), 3));
        $formattedInteger = strrev($formattedInteger);

        return ($isNegative ? '-Rp ' : 'Rp ').$formattedInteger.','.$decimalPart;
    }
}
