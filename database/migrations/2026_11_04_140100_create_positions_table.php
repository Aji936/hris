<?php

declare(strict_types=1);

use App\Shared\Database\AuditColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('title', 150);
            $table->string('level', 50);
            $table->text('description')->nullable();
            $table->decimal('base_salary_min', 15, 2)->nullable();
            $table->decimal('base_salary_max', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            AuditColumns::apply($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
