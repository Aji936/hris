<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CoreHR\Enums\EmploymentStatus;
use App\Modules\CoreHR\Models\Announcement;
use App\Modules\CoreHR\Models\AnnouncementRead;
use App\Modules\CoreHR\Requests\StoreAnnouncementRequest;
use App\Modules\CoreHR\Requests\UpdateAnnouncementRequest;
use App\Modules\CoreHR\Resources\AnnouncementResource;
use App\Modules\CoreHR\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcementService,
    ) {}

    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', Announcement::class);

        $user = $request->user();
        $canManage = (bool) $user?->can('create', Announcement::class);
        $hasReadsTable = Schema::hasTable('announcement_reads');

        $query = Announcement::query()->with('creator:id,name');

        if ($hasReadsTable) {
            if ($canManage) {
                $query->with('reads');
            } elseif ($user !== null) {
                $query->with(['reads' => static fn ($q) => $q->where('user_id', $user->id)]);
            }
        }

        if (! $canManage) {
            $query->visible();
        } else {
            $query->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id');
        }

        $items = $query->get();

        if ($canManage && $hasReadsTable) {
            $activeUsers = User::query()
                ->where('is_active', true)
                ->whereDoesntHave('employee', static function ($q): void {
                    $q->whereNotIn('status', [
                        EmploymentStatus::PERMANENT->value,
                        EmploymentStatus::PROBATION->value,
                        EmploymentStatus::CONTRACT->value,
                    ]);
                })
                ->with(['employee.department:id,name', 'employee.position:id,title'])
                ->orderBy('name')
                ->get();

            foreach ($items as $announcement) {
                /** @var \Illuminate\Support\Collection<int, AnnouncementRead> $readsByUser */
                $readsByUser = $announcement->reads->keyBy('user_id');

                $recipientStatuses = $activeUsers->map(static function (User $recipient) use ($readsByUser): array {
                    /** @var AnnouncementRead|null $readRecord */
                    $readRecord = $readsByUser->get($recipient->id);

                    return [
                        'user_id' => $recipient->id,
                        'employee_code' => $recipient->employee?->employee_code ?? '-',
                        'full_name' => $recipient->employee?->full_name ?? $recipient->name,
                        'department_name' => $recipient->employee?->department?->name ?? '-',
                        'position_title' => $recipient->employee?->position?->title ?? '-',
                        'is_read' => $readRecord !== null && $readRecord->read_at !== null,
                        'read_at' => $readRecord?->read_at?->format('Y-m-d H:i'),
                        'is_acknowledged' => $readRecord !== null && $readRecord->acknowledged_at !== null,
                        'acknowledged_at' => $readRecord?->acknowledged_at?->format('Y-m-d H:i'),
                    ];
                })->values()->all();

                $announcement->setAttribute('total_recipients', count($recipientStatuses));
                $announcement->setAttribute('recipient_statuses', $recipientStatuses);
            }
        }

        $announcements = AnnouncementResource::collection($items)->resolve();

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $announcements,
            ]);
        }

        return Inertia::render('CoreHR/Announcements/Index', [
            'announcements' => $announcements,
            'canManageAnnouncements' => $canManage,
        ]);
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse|RedirectResponse
    {
        $announcement = $this->announcementService->createAnnouncement(
            data: $request->validated(),
            attachment: $request->file('attachment'),
        );

        if ($request->wantsJson()) {
            return (new AnnouncementResource($announcement))
                ->response()
                ->setStatusCode(201);
        }

        return back()->with('success', 'Pengumuman perusahaan berhasil diterbitkan.');
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        $updated = $this->announcementService->updateAnnouncement(
            announcement: $announcement,
            data: $request->validated(),
            attachment: $request->file('attachment'),
        );

        if ($request->wantsJson()) {
            return (new AnnouncementResource($updated))->response();
        }

        return back()->with('success', 'Pengumuman perusahaan berhasil diperbarui.');
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $announcement);

        $this->announcementService->deleteAnnouncement($announcement);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Announcement deleted successfully.',
            ]);
        }

        return back()->with('success', 'Pengumuman perusahaan berhasil dihapus.');
    }

    public function downloadAttachment(Request $request, Announcement $announcement): StreamedResponse
    {
        Gate::authorize('view', $announcement);

        if ($announcement->attachment_path === null || ! Storage::disk('local')->exists($announcement->attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return Storage::disk('local')->download($announcement->attachment_path);
    }

    public function markAsRead(Request $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $announcement);

        $userId = (int) $request->user()->id;
        $readRecord = $this->announcementService->markAsRead(
            announcement: $announcement,
            userId: $userId,
            ipAddress: $request->ip(),
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Status baca pengumuman berhasil dicatat.',
                'data' => [
                    'announcement_id' => $announcement->id,
                    'user_id' => $userId,
                    'is_read' => true,
                    'read_at' => $readRecord->read_at?->toISOString(),
                    'read_at_formatted' => $readRecord->read_at?->format('Y-m-d H:i'),
                    'is_acknowledged' => $readRecord->acknowledged_at !== null,
                    'acknowledged_at' => $readRecord->acknowledged_at?->toISOString(),
                    'acknowledged_at_formatted' => $readRecord->acknowledged_at?->format('Y-m-d H:i'),
                ],
            ]);
        }

        return back();
    }

    public function acknowledge(Request $request, Announcement $announcement): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $announcement);

        $userId = (int) $request->user()->id;
        $readRecord = $this->announcementService->acknowledgePolicy(
            announcement: $announcement,
            userId: $userId,
            ipAddress: $request->ip(),
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Konfirmasi persetujuan kebijakan berhasil tercatat.',
                'data' => [
                    'announcement_id' => $announcement->id,
                    'user_id' => $userId,
                    'is_read' => true,
                    'read_at' => $readRecord->read_at?->toISOString(),
                    'read_at_formatted' => $readRecord->read_at?->format('Y-m-d H:i'),
                    'is_acknowledged' => true,
                    'acknowledged_at' => $readRecord->acknowledged_at?->toISOString(),
                    'acknowledged_at_formatted' => $readRecord->acknowledged_at?->format('Y-m-d H:i'),
                ],
            ]);
        }

        return back()->with('success', 'Terima kasih, konfirmasi persetujuan kebijakan telah tercatat.');
    }
}
