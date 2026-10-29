<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Reusable audit trail trait that integrates SoftDeletes and automatically
 * populates created_by and updated_by columns from the authenticated user.
 *
 * @property int|string|null $created_by
 * @property int|string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
trait HasAuditColumns
{
    use SoftDeletes;

    public static function bootHasAuditColumns(): void
    {
        static::creating(function (Model $model): void {
            if (! Auth::check()) {
                return;
            }

            $userId = Auth::id();

            if (! $model->isDirty('created_by') && $model->getAttribute('created_by') === null) {
                $model->setAttribute('created_by', $userId);
            }

            if (! $model->isDirty('updated_by') && $model->getAttribute('updated_by') === null) {
                $model->setAttribute('updated_by', $userId);
            }
        });

        static::updating(function (Model $model): void {
            if (! Auth::check()) {
                return;
            }

            if (! $model->isDirty('updated_by')) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });

        static::restoring(function (Model $model): void {
            if (! Auth::check()) {
                return;
            }

            $model->setAttribute('updated_by', Auth::id());
        });
    }

    public function initializeHasAuditColumns(): void
    {
        $this->mergeCasts([
            'deleted_at' => 'datetime',
        ]);
    }

    public function creator(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'created_by');
    }

    public function updater(): BelongsTo
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'updated_by');
    }
}

