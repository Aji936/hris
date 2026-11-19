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
        ];
    }
}
