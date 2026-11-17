<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('phone_number', 30)->nullable()->after('full_name');
            $table->text('address')->nullable()->after('phone_number');
            $table->string('marital_status', 20)->default('SINGLE')->after('address');
            $table->unsignedInteger('dependents_count')->default(0)->after('marital_status');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn([
                'phone_number',
                'address',
                'marital_status',
                'dependents_count',
            ]);
        });
    }
};
