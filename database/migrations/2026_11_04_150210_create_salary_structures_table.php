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
        Schema::create('salary_structures', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            AuditColumns::apply($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_structures');
    }
};
