<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->date('application_date')->nullable()->after('leave_type_id');
            $table->string('applicant_employee_code', 50)->nullable()->after('application_date');
            $table->date('applicant_hire_date')->nullable()->after('applicant_employee_code');
            $table->string('application_type', 40)->default('ANNUAL_LEAVE')->after('applicant_hire_date');

            $table->string('start_time', 10)->nullable()->after('end_date');
            $table->string('end_time', 10)->nullable()->after('start_time');

            // Calculation of Leave (Fill by HRD)
            $table->integer('previous_leave_balance')->nullable()->after('attachment_path');
            $table->integer('current_leave_taken')->nullable()->after('previous_leave_balance');
            $table->integer('remaining_leave_balance')->nullable()->after('current_leave_taken');

            // Decision & HRD Notes
            $table->boolean('is_rejected')->default(false)->after('status');
            $table->text('hrd_other_notes')->nullable()->after('rejection_reason');

            // 4-Column Signature & Multi-Level Approval Log
            $table->timestamp('applicant_signed_at')->nullable()->after('hrd_other_notes');
            $table->foreignId('approved_1_by')->nullable()->after('applicant_signed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_1_at')->nullable()->after('approved_1_by');
            $table->foreignId('approved_2_by')->nullable()->after('approved_1_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_2_at')->nullable()->after('approved_2_by');
            $table->foreignId('approved_3_by')->nullable()->after('approved_2_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_3_at')->nullable()->after('approved_3_by');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_1_by');
            $table->dropConstrainedForeignId('approved_2_by');
            $table->dropConstrainedForeignId('approved_3_by');

            $table->dropColumn([
                'application_date',
                'applicant_employee_code',
                'applicant_hire_date',
                'application_type',
                'start_time',
                'end_time',
                'previous_leave_balance',
                'current_leave_taken',
                'remaining_leave_balance',
                'is_rejected',
                'hrd_other_notes',
                'applicant_signed_at',
                'approved_1_at',
                'approved_2_at',
                'approved_3_at',
            ]);
        });
    }
};
