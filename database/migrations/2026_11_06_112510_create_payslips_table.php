<?php

declare(strict_types=1);

use App\Modules\Payroll\Enums\PayslipStatus;
use App\Shared\Database\AuditColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_id')
                ->constrained('payrolls')
                ->cascadeOnDelete();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->string('payslip_number', 60)->unique();
            $table->json('employee_snapshot');
            $table->decimal('basic_salary', 15, 2);
            $table->decimal('total_allowance', 15, 2)->default(0.00);
            $table->decimal('total_deduction', 15, 2)->default(0.00);
            $table->decimal('net_salary', 15, 2);
            $table->json('attendance_summary');
            $table->string('status', 30)
                ->default(PayslipStatus::DRAFT->value)
                ->index();
            AuditColumns::apply($table);

            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
