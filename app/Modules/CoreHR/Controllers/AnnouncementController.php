<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CoreHR\Models\Announcement;
use App\Modules\CoreHR\Requests\StoreAnnouncementRequest;
use App\Modules\CoreHR\Requests\UpdateAnnouncementRequest;
use App\Modules\CoreHR\Resources\AnnouncementResource;
use App\Modules\CoreHR\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        $canManage = (bool) $request->user()?->can('create', Announcement::class);

        $query = Announcement::query()->with('creator:id,name');

        if (! $canManage) {
            $query->visible();
        } else {
            $query->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('id');
        }

        $announcements = AnnouncementResource::collection($query->get())->resolve();

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
}
