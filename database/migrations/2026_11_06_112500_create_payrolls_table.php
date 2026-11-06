<?php

declare(strict_types=1);

use App\Modules\Payroll\Enums\PayrollStatus;
use App\Shared\Database\AuditColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table): void {
            $table->id();
            $table->string('payroll_period', 20)->unique();
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('title', 150);
            $table->string('status', 30)
                ->default(PayrollStatus::DRAFT->value)
                ->index();
            $table->decimal('total_gross_amount', 15, 2)->default(0.00);
            $table->decimal('total_deduction_amount', 15, 2)->default(0.00);
            $table->decimal('total_net_amount', 15, 2)->default(0.00);
            $table->foreignId('generated_by')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            AuditColumns::apply($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};
