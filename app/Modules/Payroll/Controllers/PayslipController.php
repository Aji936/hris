<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\Payroll\Contracts\PayslipRepositoryInterface;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Resources\PayslipResource;
use App\Modules\Payroll\Services\PayslipPdfService;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PayslipController extends Controller
{
    public function __construct(
        private readonly PayslipRepositoryInterface $payslipRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
        private readonly PayslipPdfService $payslipPdfService,
    ) {}

    public function myPayslips(Request $request): JsonResponse|InertiaResponse
    {
        Gate::authorize('viewAny', Payslip::class);

        /** @var User $actor */
        $actor = $request->user();
        $employee = $this->employeeRepository->findByUserId($actor->id);
        $employee?->loadMissing(['department', 'position']);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee' => ['Authenticated user is not linked to an employee profile.'],
                ]);
            }

            $payslips = $this->payslipRepository->paginatePublishedForEmployee($employee->id);

            return PayslipResource::collection($payslips)->response();
        }

        $paginated = $employee !== null
            ? $this->payslipRepository->paginatePublishedForEmployee($employee->id, 24)
            : null;

        $collection = $paginated?->getCollection() ?? collect();
        $latestPayslip = $collection->first();

        $totalNetReceived = '0.00';
        foreach ($collection as $slip) {
            $totalNetReceived = MoneyHelper::add($totalNetReceived, (string) $slip->net_salary);
        }

        return Inertia::render('Payroll/Payslips/MyPayslips', [
            'hasEmployeeProfile' => $employee !== null,
            'employee' => $employee === null ? null : [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department_name' => $employee->department?->name,
                'position_title' => $employee->position?->title,
            ],
            'summary' => [
                'published_count' => $paginated?->total() ?? 0,
                'latest_net_salary' => $latestPayslip !== null ? (string) $latestPayslip->net_salary : '0.00',
                'latest_period_title' => $latestPayslip?->payroll?->title ?? $latestPayslip?->payroll?->payroll_period,
                'total_net_received' => $totalNetReceived,
            ],
            'payslips' => PayslipResource::collection($collection)->resolve(),
        ]);
    }

    public function show(int|string $id): JsonResponse
    {
        $payslip = $this->payslipRepository->findWithDetails($id);
        abort_if($payslip === null, 404);

        Gate::authorize('view', $payslip);

        return (new PayslipResource($payslip))->response();
    }

    public function download(int|string $id): JsonResponse
    {
        $payslip = $this->payslipRepository->findWithDetails($id);
        abort_if($payslip === null, 404);

        Gate::authorize('download', $payslip);

        return response()->json([
            'data' => (new PayslipResource($payslip))->resolve(),
            'meta' => [
                'document_type' => 'PAYSLIP_STATEMENT',
                'payslip_number' => $payslip->payslip_number,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function downloadPdf(int|string $payslipId): Response
    {
        $payslip = $this->payslipRepository->findWithDetails($payslipId);
        abort_if($payslip === null, 404);

        // Enforce strict domain guard: draft payslips can never be downloaded as official PDFs
        abort_unless(
            $payslip->status->isEmployeeVisible(),
            403,
            'Official PDF payslip is only available once status is PUBLISHED or PAID.',
        );

        Gate::authorize('downloadPdf', $payslip);

        return $this->payslipPdfService->download($payslip);
    }
}
