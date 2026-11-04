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
        Schema::create('payroll_components', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('type', 30)
                ->default(PayrollComponentType::ALLOWANCE->value)
                ->index();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_fixed')->default(true);
            $table->string('formula', 255)->nullable();
            $table->boolean('is_active')->default(true);
            AuditColumns::apply($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_components');
    }
};
