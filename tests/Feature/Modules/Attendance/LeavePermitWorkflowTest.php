<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Attendance;

use App\Models\User;
use App\Modules\Attendance\Enums\LeavePermitType;
use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Modules\Attendance\Models\LeaveBalance;
use App\Modules\Attendance\Models\LeaveRequest;
use App\Modules\Attendance\Models\LeaveType;
use App\Modules\Attendance\Services\LeaveService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeavePermitWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private LeaveService $leaveService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->leaveService = $this->app->make(LeaveService::class);
    }

    public function test_submit_all_six_leave_and_permit_application_types_populates_applicant_snapshot_and_time_window(): void
    {
        $employeeUser = User::factory()->create(['name' => 'Budi Santoso']);
        $employeeUser->assignRole(RoleName::EMPLOYEE->value);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP-2026-088',
            'full_name' => 'Budi Santoso',
            'join_date' => '2022-03-15',
        ]);

        $annualType = LeaveType::factory()->create([
            'code' => 'ANNUAL_LEAVE',
            'name' => 'Annual Leave / Cuti Tahunan',
            'default_quota_days' => 12,
            'is_paid' => true,
            'requires_attachment' => false,
        ]);

        LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 2,
            'pending_quota' => 0,
        ]);

        $cases = [
            [
                'application_type' => LeavePermitType::SICK->value,
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-01',
                'start_time' => null,
                'end_time' => null,
                'reason' => 'Medical rest due to fever',
            ],
            [
                'application_type' => LeavePermitType::SPECIAL_PERMISSION->value,
                'start_date' => '2026-11-03',
                'end_date' => '2026-11-03',
                'start_time' => null,
                'end_time' => null,
                'reason' => 'Marriage of immediate family member',
            ],
            [
                'application_type' => LeavePermitType::ANNUAL_LEAVE->value,
                'leave_type_id' => $annualType->id,
                'start_date' => '2026-11-05',
                'end_date' => '2026-11-06',
                'start_time' => null,
                'end_time' => null,
                'reason' => 'Annual family holiday',
            ],
            [
                'application_type' => LeavePermitType::LATE_COMING->value,
                'start_date' => '2026-11-09',
                'end_date' => '2026-11-09',
                'start_time' => '08:00',
                'end_time' => '09:30',
                'reason' => 'Traffic accident on toll road',
            ],
            [
                'application_type' => LeavePermitType::EARLY_LEAVING->value,
                'start_date' => '2026-11-11',
                'end_date' => '2026-11-11',
                'start_time' => '15:00',
                'end_time' => '17:00',
                'reason' => 'Picking up child from school clinic',
            ],
            [
                'application_type' => LeavePermitType::NO_ABSENT_RECORD->value,
                'start_date' => '2026-11-13',
                'end_date' => '2026-11-13',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'reason' => 'Biometric scanner power outage at gate',
            ],
        ];

        foreach ($cases as $payload) {
            $response = $this->actingAs($employeeUser)
                ->postJson(route('attendance.leave-requests.store'), $payload)
                ->assertCreated();

            $requestId = (int) $response->json('data.id');
            $record = LeaveRequest::query()->findOrFail($requestId);

            $this->assertSame($payload['application_type'], $record->application_type?->value);
            $this->assertSame('EMP-2026-088', $record->applicant_employee_code);
            $this->assertSame('2022-03-15', $record->applicant_hire_date?->toDateString());
            $this->assertNotNull($record->application_date);
            $this->assertNotNull($record->applicant_signed_at);
            $this->assertSame($payload['start_time'], $record->start_time);
            $this->assertSame($payload['end_time'], $record->end_time);
        }

        $this->assertSame(6, LeaveRequest::query()->where('employee_id', $employee->id)->count());
    }

    public function test_three_level_approval_workflow_and_hrd_calculation_deducts_annual_leave_and_renders_pdf(): void
    {
        $assistantHeadUser = User::factory()->create(['name' => 'Rina Astuti (Asst. Head)']);
        $assistantHeadUser->assignRole(RoleName::MANAGER->value);
        $assistantHeadEmployee = Employee::factory()->create(['user_id' => $assistantHeadUser->id]);

        $deptManagerUser = User::factory()->create(['name' => 'Hendra Wijaya (Dept. Manager)']);
        $deptManagerUser->assignRole(RoleName::HR_ADMIN->value);
        Employee::factory()->create(['user_id' => $deptManagerUser->id]);

        $hrdUser = User::factory()->create(['name' => 'Siti Rahma (HRD)']);
        $hrdUser->assignRole(RoleName::HR_ADMIN->value);
        Employee::factory()->create(['user_id' => $hrdUser->id]);

        $employeeUser = User::factory()->create(['name' => 'Andi Pratama']);
        $employeeUser->assignRole(RoleName::EMPLOYEE->value);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'manager_id' => $assistantHeadEmployee->id,
            'employee_code' => 'EMP-2026-101',
            'full_name' => 'Andi Pratama',
            'join_date' => '2021-06-01',
        ]);

        $annualType = LeaveType::factory()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'default_quota_days' => 12,
            'is_paid' => true,
        ]);

        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 2,
            'pending_quota' => 0,
        ]);

        // Employee submits 3-day Annual Leave application
        $createResponse = $this->actingAs($employeeUser)
            ->postJson(route('attendance.leave-requests.store'), [
                'leave_type_id' => $annualType->id,
                'application_type' => LeavePermitType::ANNUAL_LEAVE->value,
                'start_date' => '2026-11-18',
                'end_date' => '2026-11-20',
                'total_days' => 3,
                'reason' => 'Annual family gathering in Yogyakarta',
            ])
            ->assertCreated();

        $leaveRequestId = (int) $createResponse->json('data.id');

        $balance->refresh();
        $this->assertSame(3, $balance->pending_quota);
        $this->assertSame(2, $balance->used_quota);

        // Step 1: Approved I (Assistant Head of Department)
        $this->actingAs($assistantHeadUser)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequestId), [
                'level' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', LeaveRequestStatus::APPROVED_L1->value)
            ->assertJsonPath('data.approved_1_by', $assistantHeadUser->id);

        // Step 2: Approved II (Dept. Manager)
        $this->actingAs($deptManagerUser)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequestId), [
                'level' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', LeaveRequestStatus::APPROVED_L2->value)
            ->assertJsonPath('data.approved_2_by', $deptManagerUser->id);

        // Quota is still in pending_quota until Level 3 HRD finalization
        $balance->refresh();
        $this->assertSame(3, $balance->pending_quota);
        $this->assertSame(2, $balance->used_quota);

        // Step 3: Approved III (HRD) with Calculation of Leave (Fill by HRD)
        $this->actingAs($hrdUser)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequestId), [
                'level' => 3,
                'previous_leave_balance' => 10,
                'current_leave_taken' => 3,
                'remaining_leave_balance' => 7,
                'hrd_other_notes' => 'Verified against 2026 annual entitlement ledger.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', LeaveRequestStatus::APPROVED_HRD->value)
            ->assertJsonPath('data.approved_3_by', $hrdUser->id)
            ->assertJsonPath('data.previous_leave_balance', 10)
            ->assertJsonPath('data.current_leave_taken', 3)
            ->assertJsonPath('data.remaining_leave_balance', 7)
            ->assertJsonPath('data.hrd_other_notes', 'Verified against 2026 annual entitlement ledger.');

        $balance->refresh();
        $this->assertSame(0, $balance->pending_quota);
        $this->assertSame(5, $balance->used_quota);
        $this->assertSame(7, $balance->remainingQuota());

        // Verify AuditLogs exist for all 3 levels
        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_id', (string) $leaveRequestId)
                ->where('event', 'leave_request.approved_l1')
                ->exists()
        );
        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_id', (string) $leaveRequestId)
                ->where('event', 'leave_request.approved_l2')
                ->exists()
        );
        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_id', (string) $leaveRequestId)
                ->where('event', 'leave_request.approved_hrd')
                ->exists()
        );

        // Verify official printable PDF endpoint
        $pdfResponse = $this->actingAs($employeeUser)
            ->get(route('attendance.leave-permits.print', $leaveRequestId))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $pdfResponse->getContent());
    }

    public function test_hrd_rejection_via_level_three_restores_pending_quota_and_marks_is_rejected(): void
    {
        $hrdUser = User::factory()->create();
        $hrdUser->assignRole(RoleName::HR_ADMIN->value);

        $employeeUser = User::factory()->create();
        $employeeUser->assignRole(RoleName::EMPLOYEE->value);
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id]);

        $annualType = LeaveType::factory()->create(['code' => 'ANNUAL']);
        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 4,
            'pending_quota' => 0,
        ]);

        $leaveRequest = $this->leaveService->submitRequest($employee->id, [
            'leave_type_id' => $annualType->id,
            'application_type' => LeavePermitType::ANNUAL_LEAVE->value,
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-11',
            'total_days' => 2,
            'reason' => 'Year-end personal trip',
        ]);

        $this->actingAs($hrdUser)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequest->id), [
                'level' => 3,
                'is_rejected' => true,
                'rejection_reason' => 'Year-end audit period freeze',
                'hrd_other_notes' => 'Reschedule to January',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', LeaveRequestStatus::REJECTED->value)
            ->assertJsonPath('data.is_rejected', true)
            ->assertJsonPath('data.rejection_reason', 'Year-end audit period freeze')
            ->assertJsonPath('data.hrd_other_notes', 'Reschedule to January');

        $balance->refresh();
        $this->assertSame(0, $balance->pending_quota);
        $this->assertSame(4, $balance->used_quota);
    }

    public function test_late_coming_or_early_leaving_can_take_half_day_annual_leave_and_pdf_contains_bilingual_notes(): void
    {
        $hrdUser = User::factory()->create(['name' => 'HRD Approver']);
        $hrdUser->assignRole(RoleName::HR_ADMIN->value);

        $employeeUser = User::factory()->create(['name' => 'Dewi Lestari']);
        $employeeUser->assignRole(RoleName::EMPLOYEE->value);
        $employee = Employee::factory()->create([
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP-2026-205',
            'full_name' => 'Dewi Lestari',
        ]);

        $annualType = LeaveType::factory()->create([
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'default_quota_days' => 12,
            'is_paid' => true,
        ]);

        $balance = LeaveBalance::factory()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $annualType->id,
            'year' => 2026,
            'total_quota' => 12,
            'used_quota' => 2,
            'pending_quota' => 0,
        ]);

        // Submit Late Coming with 0.5-day annual leave option
        $createResponse = $this->actingAs($employeeUser)
            ->postJson(route('attendance.leave-requests.store'), [
                'leave_type_id' => $annualType->id,
                'application_type' => LeavePermitType::LATE_COMING->value,
                'start_date' => '2026-11-23',
                'end_date' => '2026-11-23',
                'start_time' => '08:00',
                'end_time' => '11:30',
                'total_days' => 0.5,
                'reason' => 'Keperluan mendadak di pagi hari (potong cuti 0,5 hari)',
            ])
            ->assertCreated()
            ->assertJsonPath('data.total_days', 0.5)
            ->assertJsonPath('data.previous_leave_balance', 10)
            ->assertJsonPath('data.current_leave_taken', 0.5)
            ->assertJsonPath('data.remaining_leave_balance', 9.5);

        $leaveRequestId = (int) $createResponse->json('data.id');

        $balance->refresh();
        $this->assertSame(0.5, $balance->pending_quota);
        $this->assertSame(9.5, $balance->remainingQuota());

        // HRD finalizes Level 3 approval with 0.5 day calculation
        $this->actingAs($hrdUser)
            ->postJson(route('attendance.leave-requests.approve', $leaveRequestId), [
                'level' => 3,
                'previous_leave_balance' => 10,
                'current_leave_taken' => 0.5,
                'remaining_leave_balance' => 9.5,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', LeaveRequestStatus::APPROVED_HRD->value)
            ->assertJsonPath('data.current_leave_taken', 0.5)
            ->assertJsonPath('data.remaining_leave_balance', 9.5);

        $balance->refresh();
        $this->assertSame(0, $balance->pending_quota);
        $this->assertSame(2.5, $balance->used_quota);
        $this->assertSame(9.5, $balance->remainingQuota());

        // Verify Blade HTML view contains the exact Indonesian-first bilingual Catatan / Note block
        $renderedHtml = view('attendance.leave-permit-pdf', [
            'application' => LeaveRequest::query()->findOrFail($leaveRequestId),
            'permitTypes' => LeavePermitType::cases(),
            'companyName' => 'PT HRIS Nusantara',
        ])->render();

        $this->assertStringContainsString('Catatan :', $renderedHtml);
        $this->assertStringContainsString('Jumlah hari berdasarkan pada hari kerja yang sebenarnya.', $renderedHtml);
        $this->assertStringContainsString('Total of the days is base on the actual working days.', $renderedHtml);
        $this->assertStringContainsString('Untuk keterlambatan atau pulang awal dapat mengambil cuti tahunan selama 0,5 (setengah) hari', $renderedHtml);
        $this->assertStringContainsString('For late-coming or early leaving could be taken by half day annual leave', $renderedHtml);
        $this->assertStringContainsString('Karyawan harus terlebih dahulu mendapatkan copy persetujuan aplikasi dari HRD', $renderedHtml);
    }

    public function test_hr_admin_can_set_initial_leave_balance_on_employee_and_manage_single_or_bulk_leave_balances(): void
    {
        $hrdUser = User::factory()->create(['name' => 'HRD Manager']);
        $hrdUser->assignRole(RoleName::HR_ADMIN->value);

        $empA = Employee::factory()->create([
            'employee_code' => 'EMP-BAL-001',
            'full_name' => 'Karyawan Satu',
        ]);
        $empB = Employee::factory()->create([
            'employee_code' => 'EMP-BAL-002',
            'full_name' => 'Karyawan Dua',
        ]);

        // 1. Bulk initialize annual leave balances (12 days) for all active employees without a balance record
        $this->actingAs($hrdUser)
            ->postJson(route('attendance.leave-balances.bulk-init'), [
                'default_quota' => 12,
                'overwrite_existing' => false,
                'adjustment_reason' => 'Saldo awal tahun berjalan',
            ])
            ->assertOk()
            ->assertJsonPath('data.initialized_count', 2);

        // 2. Single adjustment with 0.5-day decimal precision and audit reason
        $this->actingAs($hrdUser)
            ->putJson(route('attendance.leave-balances.upsert', $empA->id), [
                'total_quota' => 14.5,
                'used_quota' => 2.0,
                'adjustment_reason' => 'Tambahan cuti loyalitas 2,5 hari',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_quota', 14.5)
            ->assertJsonPath('data.used_quota', 2)
            ->assertJsonPath('data.remaining_quota', 12.5);

        $this->assertTrue(
            AuditLog::query()
                ->where('event', 'leave_balance.adjusted')
                ->exists()
        );

        // 3. When Emp A submits a 1-day annual leave request, Previous Leave Balance automatically reads 12.5 and Remaining becomes 11.5
        $empA->user->assignRole(RoleName::EMPLOYEE->value);
        $this->actingAs($empA->user)
            ->postJson(route('attendance.leave-requests.store'), [
                'application_type' => LeavePermitType::ANNUAL_LEAVE->value,
                'start_date' => '2026-11-25',
                'end_date' => '2026-11-25',
                'total_days' => 1,
                'reason' => 'Urusan keluarga',
            ])
            ->assertCreated()
            ->assertJsonPath('data.previous_leave_balance', 12.5)
            ->assertJsonPath('data.current_leave_taken', 1)
            ->assertJsonPath('data.remaining_leave_balance', 11.5);
    }
}
