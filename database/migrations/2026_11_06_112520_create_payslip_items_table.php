<?php

declare(strict_types=1);

use App\Modules\Payroll\Enums\PayrollComponentType;
use App\Shared\Database\AuditColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payslip_id')
                ->constrained('payslips')
                ->cascadeOnDelete();
            $table->foreignId('payroll_component_id')
                ->constrained('payroll_components')
                ->restrictOnDelete();
            $table->string('name', 150);
            $table->string('type', 30)
                ->default(PayrollComponentType::ALLOWANCE->value)
                ->index();
            $table->decimal('amount', 15, 2);
            AuditColumns::apply($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_items');
    }
};
