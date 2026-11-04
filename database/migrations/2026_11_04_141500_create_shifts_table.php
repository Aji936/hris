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
        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('late_grace_period_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            AuditColumns::apply($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
