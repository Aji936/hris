<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_structure_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('salary_structure_id')
                ->constrained('salary_structures')
                ->cascadeOnDelete();
            $table->foreignId('payroll_component_id')
                ->constrained('payroll_components')
                ->restrictOnDelete();
            $table->decimal('default_amount', 15, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['salary_structure_id', 'payroll_component_id'], 'salary_struct_comp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structure_components');
    }
};
