<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Models;

use App\Models\User;
use App\Modules\CoreHR\Enums\AnnouncementCategory;
use App\Shared\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
