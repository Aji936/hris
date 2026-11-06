<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CoreHR\Contracts\EmployeeRepositoryInterface;
use App\Modules\Payroll\Contracts\PayslipRepositoryInterface;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Resources\PayslipResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayslipController extends Controller
{
    public function __construct(
        private readonly PayslipRepositoryInterface $payslipRepository,
        private readonly EmployeeRepositoryInterface $employeeRepository,
    ) {}

    public function myPayslips(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Payslip::class);

        /** @var User $actor */
        $actor = $request->user();
        $employee = $this->employeeRepository->findByUserId($actor->id);

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee' => ['Authenticated user is not linked to an employee profile.'],
            ]);
        }

        $payslips = $this->payslipRepository->paginatePublishedForEmployee($employee->id);

        return PayslipResource::collection($payslips)->response();
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
}
