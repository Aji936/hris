<?php

declare(strict_types=1);

use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Shared\Database\AuditColumns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('employee_code', 30)->unique();
            $table->string('full_name', 150)->nullable();
            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete();
            $table->foreignId('position_id')
                ->constrained('positions')
                ->restrictOnDelete();
            $table->foreignId('manager_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
            $table->date('join_date');
            $table->string('status', 30)
                ->default(EmploymentStatus::PROBATION->value)
                ->index();
            $table->string('salary_grade', 50)->nullable();
            $table->decimal('basic_salary', 15, 2)->nullable();

            // Encrypted PII & banking columns (stored as encrypted text payloads)
            $table->text('national_id_number')->nullable();
            $table->text('tax_id_number')->nullable();
            $table->text('bank_account_number')->nullable();
            $table->string('bank_name', 100)->nullable();

            AuditColumns::apply($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
