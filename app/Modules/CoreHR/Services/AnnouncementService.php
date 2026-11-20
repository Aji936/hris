<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Services;

use App\Modules\CoreHR\Enums\AnnouncementCategory;
use App\Modules\CoreHR\Models\Announcement;
use App\Modules\CoreHR\Models\AnnouncementRead;
use App\Modules\RBAC\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AnnouncementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAnnouncement(array $data, ?UploadedFile $attachment = null): Announcement
    {
        return DB::transaction(function () use ($data, $attachment): Announcement {
            $attachmentPath = $attachment?->store('announcements', 'local');
            $actorId = Auth::id();

            $publishedAt = ! empty($data['published_at'])
                ? CarbonImmutable::parse((string) $data['published_at'])
                : now();

            $expiresAt = ! empty($data['expires_at'])
                ? CarbonImmutable::parse((string) $data['expires_at'])->endOfDay()
                : null;

            /** @var Announcement $announcement */
            $announcement = Announcement::query()->create([
                'title' => (string) $data['title'],
                'category' => isset($data['category'])
                    ? (string) $data['category']
                    : AnnouncementCategory::INFO->value,
                'content' => (string) $data['content'],
                'attachment_path' => $attachmentPath,
                'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
                'published_at' => $publishedAt,
                'expires_at' => $expiresAt,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->auditLogService->record(
                event: 'announcement.created',
                auditable: $announcement,
                oldValues: null,
                newValues: [
                    'title' => $announcement->title,
                    'category' => $announcement->category->value,
                    'is_pinned' => $announcement->is_pinned,
                    'is_active' => $announcement->is_active,
                    'expires_at' => $announcement->expires_at?->toDateTimeString(),
                ],
                actorId: $actorId !== null ? (int) $actorId : null,
            );

            return $announcement->load('creator:id,name');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAnnouncement(Announcement $announcement, array $data, ?UploadedFile $attachment = null): Announcement
    {
        return DB::transaction(function () use ($announcement, $data, $attachment): Announcement {
            $oldValues = [
                'title' => $announcement->title,
                'category' => $announcement->category->value,
                'content' => $announcement->content,
                'is_pinned' => $announcement->is_pinned,
                'is_active' => $announcement->is_active,
                'expires_at' => $announcement->expires_at?->toDateTimeString(),
            ];

            $attachmentPath = $announcement->attachment_path;
            if ($attachment !== null) {
                if ($attachmentPath !== null && Storage::disk('local')->exists($attachmentPath)) {
                    Storage::disk('local')->delete($attachmentPath);
                }
                $attachmentPath = $attachment->store('announcements', 'local');
            }

            $expiresAt = array_key_exists('expires_at', $data)
                ? (! empty($data['expires_at']) ? CarbonImmutable::parse((string) $data['expires_at'])->endOfDay() : null)
                : $announcement->expires_at;

            $announcement->update([
                'title' => (string) ($data['title'] ?? $announcement->title),
                'category' => (string) ($data['category'] ?? $announcement->category->value),
                'content' => (string) ($data['content'] ?? $announcement->content),
                'attachment_path' => $attachmentPath,
                'is_pinned' => isset($data['is_pinned']) ? (bool) $data['is_pinned'] : $announcement->is_pinned,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $announcement->is_active,
                'expires_at' => $expiresAt,
                'updated_by' => Auth::id(),
            ]);

            $this->auditLogService->record(
                event: 'announcement.updated',
                auditable: $announcement,
                oldValues: $oldValues,
                newValues: [
                    'title' => $announcement->title,
                    'category' => $announcement->category->value,
                    'content' => $announcement->content,
                    'is_pinned' => $announcement->is_pinned,
                    'is_active' => $announcement->is_active,
                    'expires_at' => $announcement->expires_at?->toDateTimeString(),
                ],
                actorId: Auth::id() !== null ? (int) Auth::id() : null,
            );

            return $announcement->fresh(['creator:id,name']) ?? $announcement;
        });
    }

    public function deleteAnnouncement(Announcement $announcement): void
    {
        DB::transaction(function () use ($announcement): void {
            $oldValues = [
                'title' => $announcement->title,
                'category' => $announcement->category->value,
                'is_pinned' => $announcement->is_pinned,
            ];

            $this->auditLogService->record(
                event: 'announcement.deleted',
                auditable: $announcement,
                oldValues: $oldValues,
                newValues: null,
                actorId: Auth::id() !== null ? (int) Auth::id() : null,
            );

            $announcement->delete();
        });
    }

    public function markAsRead(Announcement $announcement, int $userId, ?string $ipAddress = null): AnnouncementRead
    {
        return DB::transaction(function () use ($announcement, $userId, $ipAddress): AnnouncementRead {
            /** @var AnnouncementRead $readRecord */
            $readRecord = AnnouncementRead::query()->firstOrNew([
                'announcement_id' => $announcement->id,
                'user_id' => $userId,
            ]);

            if ($readRecord->read_at === null) {
                $readRecord->read_at = now();
            }

            if ($ipAddress !== null) {
                $readRecord->ip_address = $ipAddress;
            }

            $readRecord->save();

            return $readRecord;
        });
    }

    public function acknowledgePolicy(Announcement $announcement, int $userId, ?string $ipAddress = null): AnnouncementRead
    {
        if ($announcement->category !== AnnouncementCategory::POLICY) {
            throw ValidationException::withMessages([
                'category' => 'Konfirmasi persetujuan hanya berlaku untuk pengumuman dengan kategori Kebijakan HR (POLICY).',
            ]);
        }

        return DB::transaction(function () use ($announcement, $userId, $ipAddress): AnnouncementRead {
            /** @var AnnouncementRead $readRecord */
            $readRecord = AnnouncementRead::query()->firstOrNew([
                'announcement_id' => $announcement->id,
                'user_id' => $userId,
            ]);

            $now = now();

            if ($readRecord->read_at === null) {
                $readRecord->read_at = $now;
            }

            $wasAlreadyAcknowledged = $readRecord->acknowledged_at !== null;
            if (! $wasAlreadyAcknowledged) {
                $readRecord->acknowledged_at = $now;
            }

            if ($ipAddress !== null) {
                $readRecord->ip_address = $ipAddress;
            }

            $readRecord->save();

            $this->auditLogService->record(
                event: 'announcement.acknowledged',
                auditable: $announcement,
                oldValues: null,
                newValues: [
                    'announcement_id' => $announcement->id,
                    'title' => $announcement->title,
                    'category' => $announcement->category->value,
                    'user_id' => $userId,
                    'read_at' => $readRecord->read_at?->toDateTimeString(),
                    'acknowledged_at' => $readRecord->acknowledged_at?->toDateTimeString(),
                    'ip_address' => $readRecord->ip_address,
                ],
                actorId: $userId,
            );

            return $readRecord;
        });
    }
}
