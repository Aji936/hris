<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Resources;

use App\Modules\CoreHR\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id !== null ? (int) $request->user()->id : null;
        $userRead = $this->readRecordFor($userId);

        $readCount = $this->relationLoaded('reads')
            ? $this->reads->whereNotNull('read_at')->count()
            : (int) ($this->getAttribute('read_count') ?? 0);

        $acknowledgedCount = $this->relationLoaded('reads')
            ? $this->reads->whereNotNull('acknowledged_at')->count()
            : (int) ($this->getAttribute('acknowledged_count') ?? 0);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'content' => $this->content,
            'attachment_path' => $this->attachment_path,
            'attachment_url' => $this->attachment_path !== null
                ? route('announcements.attachment', ['announcement' => $this->id], false)
                : null,
            'is_pinned' => (bool) $this->is_pinned,
            'is_active' => (bool) $this->is_active,
            'published_at' => $this->published_at?->toISOString(),
            'published_date' => $this->published_at?->format('Y-m-d') ?? $this->created_at?->format('Y-m-d'),
            'expires_at' => $this->expires_at?->toISOString(),
            'expires_date' => $this->expires_at?->format('Y-m-d'),
            'created_by' => $this->created_by,
            'author_name' => $this->whenLoaded('creator', fn () => $this->creator?->name, 'HR Department'),
            'created_at' => $this->created_at?->toISOString(),
            'is_read' => $userRead !== null && $userRead->read_at !== null,
            'read_at' => $userRead?->read_at?->toISOString(),
            'read_at_formatted' => $userRead?->read_at?->format('Y-m-d H:i'),
            'is_acknowledged' => $userRead !== null && $userRead->acknowledged_at !== null,
            'acknowledged_at' => $userRead?->acknowledged_at?->toISOString(),
            'acknowledged_at_formatted' => $userRead?->acknowledged_at?->format('Y-m-d H:i'),
            'read_count' => $readCount,
            'acknowledged_count' => $acknowledgedCount,
            'total_recipients' => (int) ($this->getAttribute('total_recipients') ?? 0),
            'recipient_statuses' => $this->getAttribute('recipient_statuses') ?? [],
        ];
    }
}
