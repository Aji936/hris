<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Payroll\Contracts\PayrollRepositoryInterface;
use App\Modules\Payroll\Models\Payroll;
use App\Modules\Payroll\Requests\GeneratePayrollRequest;
use App\Modules\Payroll\Resources\PayrollResource;
use App\Modules\Payroll\Services\PayrollService;
use App\Shared\Exceptions\DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PayrollBatchController extends Controller
{
    public function __construct(
        private readonly PayrollRepositoryInterface $payrollRepository,
        private readonly PayrollService $payrollService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Payroll::class);

        $batches = $this->payrollRepository->paginateBatches(
            $request->only(['status']),
        );

        return PayrollResource::collection($batches)->response();
    }

    public function show(int|string $id): JsonResponse
    {
        $payroll = $this->payrollRepository->findWithPayslips($id);
        abort_if($payroll === null, 404);

        Gate::authorize('view', $payroll);

        return (new PayrollResource($payroll))->response();
    }

    public function generate(GeneratePayrollRequest $request): JsonResponse
    {
        Gate::authorize('create', Payroll::class);

        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $isAsync = (bool) ($validated['async'] ?? false);

        try {
            $payroll = $isAsync
                ? $this->payrollService->dispatchMonthlyPayroll(
                    period: (string) $validated['payroll_period'],
                    startDate: (string) $validated['start_date'],
                    endDate: (string) $validated['end_date'],
                    adminId: $actor->id,
                    title: $validated['title'] ?? null,
                )
                : $this->payrollService->generatePayroll(
                    period: (string) $validated['payroll_period'],
                    startDate: (string) $validated['start_date'],
                    endDate: (string) $validated['end_date'],
                    adminId: $actor->id,
                    title: $validated['title'] ?? null,
                );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'payroll' => [$exception->getMessage()],
            ]);
        }

        return (new PayrollResource($payroll))
            ->response()
            ->setStatusCode($isAsync ? 202 : 201);
    }

    public function approve(Request $request, int|string $id): JsonResponse
    {
        $payroll = $this->payrollRepository->findWithPayslips($id);
        abort_if($payroll === null, 404);

        Gate::authorize('approve', $payroll);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $approved = $this->payrollService->approvePayroll(
                payrollId: $payroll->id,
                approverId: $actor->id,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'payroll' => [$exception->getMessage()],
            ]);
        }

        return (new PayrollResource($approved))->response();
    }
}
