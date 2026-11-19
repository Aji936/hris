<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CoreHR;

use App\Models\User;
use App\Modules\CoreHR\Enums\AnnouncementCategory;
use App\Modules\CoreHR\Models\Announcement;
use App\Modules\RBAC\Enums\RoleName;
use App\Modules\RBAC\Models\AuditLog;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_hr_admin_can_create_update_pin_and_delete_announcements_with_audit_trail(): void
    {
        $hrAdmin = User::factory()->create(['name' => 'Nadia HR Admin']);
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        // 1. Create announcement
        $createResponse = $this->actingAs($hrAdmin)
            ->postJson(route('announcements.store'), [
                'title' => 'Sosialisasi Kebijakan WFH & Presensi GPS',
                'category' => AnnouncementCategory::POLICY->value,
                'content' => 'Mulai bulan depan seluruh presensi menggunakan GPS aplikasi HRIS.',
                'is_pinned' => true,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Sosialisasi Kebijakan WFH & Presensi GPS')
            ->assertJsonPath('data.category', AnnouncementCategory::POLICY->value)
            ->assertJsonPath('data.is_pinned', true);

        $announcementId = (int) $createResponse->json('data.id');

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', Announcement::class)
                ->where('auditable_id', (string) $announcementId)
                ->where('event', 'announcement.created')
                ->exists()
        );

        // 2. Update announcement
        $this->actingAs($hrAdmin)
            ->putJson(route('announcements.update', $announcementId), [
                'title' => 'Sosialisasi Kebijakan WFH & Presensi GPS (Diperbarui)',
                'category' => AnnouncementCategory::URGENT->value,
                'content' => 'Harap memperbarui aplikasi Android HRIS ke versi terbaru.',
                'is_pinned' => false,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Sosialisasi Kebijakan WFH & Presensi GPS (Diperbarui)')
            ->assertJsonPath('data.category', AnnouncementCategory::URGENT->value)
            ->assertJsonPath('data.is_pinned', false);

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', Announcement::class)
                ->where('auditable_id', (string) $announcementId)
                ->where('event', 'announcement.updated')
                ->exists()
        );

        // 3. Soft delete announcement
        $this->actingAs($hrAdmin)
            ->deleteJson(route('announcements.destroy', $announcementId))
            ->assertOk();

        $this->assertSoftDeleted('announcements', ['id' => $announcementId]);
        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', Announcement::class)
                ->where('auditable_id', (string) $announcementId)
                ->where('event', 'announcement.deleted')
                ->exists()
        );
    }

    public function test_visible_announcements_appear_on_dashboard_ordered_by_pinned_first_and_exclude_expired_or_inactive(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $employee = User::factory()->create();
        $employee->assignRole(RoleName::EMPLOYEE->value);

        // Regular active announcement (older)
        Announcement::query()->create([
            'title' => 'Pengumuman Reguler',
            'category' => AnnouncementCategory::INFO->value,
            'content' => 'Isi pengumuman reguler.',
            'is_pinned' => false,
            'is_active' => true,
            'published_at' => now()->subHours(2),
            'created_by' => $hrAdmin->id,
        ]);

        // Pinned active announcement (should appear first even if published earlier)
        Announcement::query()->create([
            'title' => 'Pengumuman Penting Disematkan',
            'category' => AnnouncementCategory::URGENT->value,
            'content' => 'Isi pengumuman yang disematkan di atas.',
            'is_pinned' => true,
            'is_active' => true,
            'published_at' => now()->subDays(1),
            'created_by' => $hrAdmin->id,
        ]);

        // Expired announcement (should be excluded from visible)
        Announcement::query()->create([
            'title' => 'Pengumuman Sudah Kedaluwarsa',
            'category' => AnnouncementCategory::EVENT->value,
            'content' => 'Acara bulan lalu.',
            'is_pinned' => true,
            'is_active' => true,
            'published_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
            'created_by' => $hrAdmin->id,
        ]);

        // Inactive archived announcement (should be excluded from visible)
        Announcement::query()->create([
            'title' => 'Pengumuman Diarsipkan',
            'category' => AnnouncementCategory::INFO->value,
            'content' => 'Sudah tidak aktif.',
            'is_pinned' => false,
            'is_active' => false,
            'published_at' => now()->subHour(),
            'created_by' => $hrAdmin->id,
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('dashboard')
                    ->has('announcements', 2)
                    ->where('announcements.0.title', 'Pengumuman Penting Disematkan')
                    ->where('announcements.1.title', 'Pengumuman Reguler')
                    ->where('canManageAnnouncements', false)
            );
    }

    public function test_regular_employee_and_manager_can_read_announcements_but_cannot_create_update_or_delete(): void
    {
        $hrAdmin = User::factory()->create();
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $employee = User::factory()->create();
        $employee->assignRole(RoleName::EMPLOYEE->value);

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::MANAGER->value);

        $announcement = Announcement::query()->create([
            'title' => 'Info Jadwal Libur Nasional',
            'category' => AnnouncementCategory::INFO->value,
            'content' => 'Libur nasional jatuh pada hari Jumat.',
            'is_pinned' => true,
            'is_active' => true,
            'published_at' => now(),
            'created_by' => $hrAdmin->id,
        ]);

        // Employee & Manager can view announcements list (200 OK)
        $this->actingAs($employee)
            ->getJson(route('announcements.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($manager)
            ->getJson(route('announcements.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Employee & Manager cannot create, update, or delete (403 Forbidden)
        foreach ([$employee, $manager] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->postJson(route('announcements.store'), [
                    'title' => 'Unauthorized Announcement',
                    'category' => AnnouncementCategory::INFO->value,
                    'content' => 'Should be blocked.',
                ])
                ->assertForbidden();

            $this->actingAs($unauthorizedUser)
                ->putJson(route('announcements.update', $announcement->id), [
                    'title' => 'Tampered Title',
                    'category' => AnnouncementCategory::INFO->value,
                    'content' => 'Should be blocked.',
                ])
                ->assertForbidden();

            $this->actingAs($unauthorizedUser)
                ->deleteJson(route('announcements.destroy', $announcement->id))
                ->assertForbidden();
        }
    }
}
