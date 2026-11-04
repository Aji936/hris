<?php

declare(strict_types=1);

use App\Modules\Payroll\Enums\PaymentMethod;
use App\Shared\Database\AuditColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->restrictOnDelete();
            $table->foreignId('salary_structure_id')
                ->constrained('salary_structures')
                ->restrictOnDelete();
            $table->date('effective_date')->index();
            $table->decimal('base_salary', 15, 2);
            $table->string('payment_method', 30)
                ->default(PaymentMethod::BANK_TRANSFER->value);
            $table->boolean('is_active')->default(true)->index();
            AuditColumns::apply($table);

            $table->index(['employee_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salaries');
    }
};
