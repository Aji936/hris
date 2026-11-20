<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use App\Models\User;
use App\Modules\CoreHR\Enums\AnnouncementCategory;
use App\Shared\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Announcement extends Model
{
    use HasAuditColumns;

    protected $table = 'announcements';

    protected $fillable = [
        'title',
        'category',
        'content',
        'attachment_path',
        'is_pinned',
        'is_active',
        'published_at',
        'expires_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => AnnouncementCategory::class,
            'is_pinned' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class, 'announcement_id');
    }

    public function readRecordFor(?int $userId = null): ?AnnouncementRead
    {
        $targetUserId = $userId ?? (Auth::id() !== null ? (int) Auth::id() : null);
        if ($targetUserId === null) {
            return null;
        }

        if ($this->relationLoaded('reads')) {
            /** @var AnnouncementRead|null $record */
            $record = $this->reads->firstWhere('user_id', $targetUserId);

            return $record;
        }

        if (! Schema::hasTable('announcement_reads')) {
            return null;
        }

        return $this->reads()->where('user_id', $targetUserId)->first();
    }

    public function isReadBy(?int $userId = null): bool
    {
        $record = $this->readRecordFor($userId);

        return $record !== null && $record->read_at !== null;
    }

    public function isAcknowledgedBy(?int $userId = null): bool
    {
        $record = $this->readRecordFor($userId);

        return $record !== null && $record->acknowledged_at !== null;
    }

    public function getIsReadAttribute(): bool
    {
        return $this->isReadBy();
    }

    public function getIsAcknowledgedAttribute(): bool
    {
        return $this->isAcknowledgedBy();
    }

    /**
     * Scope active, published, and non-expired announcements ordered by pinned first.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(static function (Builder $sub) use ($now): void {
                $sub->whereNull('published_at')
                    ->orWhere('published_at', '<=', $now);
            })
            ->where(static function (Builder $sub) use ($now): void {
                $sub->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }
}
