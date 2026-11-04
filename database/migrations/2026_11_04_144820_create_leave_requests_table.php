<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\LeaveRequestStatus;
use App\Shared\Database\AuditColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->restrictOnDelete();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->unsignedInteger('total_days');
            $table->text('reason');
            $table->string('attachment_path', 255)->nullable();
            $table->string('status', 30)
                ->default(LeaveRequestStatus::PENDING->value)
                ->index();
            $table->foreignId('approver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            AuditColumns::apply($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
