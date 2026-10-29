<?php

declare(strict_types=1);

namespace App\Shared\Database;

use Illuminate\Database\Schema\Blueprint;

/**
 * Helper to consistently append audit trail (created_by, updated_by)
 * and soft delete (deleted_at) columns in database migrations.
 */
final class AuditColumns
{
    /**
     * Add timestamps, softDeletes, and foreign keys for created_by / updated_by.
     */
    public static function apply(Blueprint $table, bool $useUuidUsers = false): void
    {
        $table->timestamps();
        $table->softDeletes();

        if ($useUuidUsers) {
            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            return;
        }

        $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->foreignId('updated_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();
    }
}

