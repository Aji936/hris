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
            $table->decimal('total_days', 5, 1)->change();
            $table->decimal('previous_leave_balance', 5, 1)->nullable()->change();
            $table->decimal('current_leave_taken', 5, 1)->nullable()->change();
            $table->decimal('remaining_leave_balance', 5, 1)->nullable()->change();
        });

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->decimal('total_quota', 5, 1)->default(0)->change();
            $table->decimal('used_quota', 5, 1)->default(0)->change();
            $table->decimal('pending_quota', 5, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table): void {
            $table->unsignedInteger('total_days')->change();
            $table->integer('previous_leave_balance')->nullable()->change();
            $table->integer('current_leave_taken')->nullable()->change();
            $table->integer('remaining_leave_balance')->nullable()->change();
        });

        Schema::table('leave_balances', function (Blueprint $table): void {
            $table->unsignedInteger('total_quota')->default(0)->change();
            $table->unsignedInteger('used_quota')->default(0)->change();
            $table->unsignedInteger('pending_quota')->default(0)->change();
        });
    }
};

