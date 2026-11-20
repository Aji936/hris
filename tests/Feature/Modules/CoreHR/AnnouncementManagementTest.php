<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\CoreHR;

use App\Models\User;
use App\Modules\CoreHR\Enums\AnnouncementCategory;
use App\Modules\CoreHR\Models\Announcement;
use App\Modules\CoreHR\Models\AnnouncementRead;
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

    public function test_employee_can_mark_announcement_as_read_and_acknowledge_policy_with_audit_log_and_hr_tracking(): void
    {
        $hrAdmin = User::factory()->create(['name' => 'HR Admin Utama']);
        $hrAdmin->assignRole(RoleName::HR_ADMIN->value);

        $employeeA = User::factory()->create(['name' => 'Budi Santoso']);
        $employeeA->assignRole(RoleName::EMPLOYEE->value);

        $employeeB = User::factory()->create(['name' => 'Siti Aminah']);
        $employeeB->assignRole(RoleName::EMPLOYEE->value);

        $policyAnnouncement = Announcement::query()->create([
            'title' => 'Kebijakan Kepatuhan Keamanan Data & Jam Kerja 2026',
            'category' => AnnouncementCategory::POLICY->value,
            'content' => 'Seluruh karyawan wajib mematuhi SOP keamanan data perusahaan.',
            'is_pinned' => true,
            'is_active' => true,
            'published_at' => now()->subMinutes(10),
            'created_by' => $hrAdmin->id,
        ]);

        $infoAnnouncement = Announcement::query()->create([
            'title' => 'Info Pemeliharaan Server Internal',
            'category' => AnnouncementCategory::INFO->value,
            'content' => 'Server akan mengalami maintenance hari Sabtu pukul 22:00 WIB.',
            'is_pinned' => false,
            'is_active' => true,
            'published_at' => now()->subMinutes(5),
            'created_by' => $hrAdmin->id,
        ]);

        // Initially unread and unacknowledged for Employee A on Dashboard
        $this->actingAs($employeeA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('dashboard')
                    ->where('announcements.0.id', $policyAnnouncement->id)
                    ->where('announcements.0.is_read', false)
                    ->where('announcements.0.is_acknowledged', false)
            );

        // 1. Employee A opens detail modal -> marks policy announcement as read
        $this->actingAs($employeeA)
            ->postJson(route('announcements.read', $policyAnnouncement->id))
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.is_acknowledged', false);

        // Repeated read call does not duplicate pivot row
        $this->actingAs($employeeA)
            ->postJson(route('announcements.read', $policyAnnouncement->id))
            ->assertOk();

        $this->assertSame(
            1,
            AnnouncementRead::query()
                ->where('announcement_id', $policyAnnouncement->id)
                ->where('user_id', $employeeA->id)
                ->count()
        );

        // 2. Employee A acknowledges POLICY announcement
        $this->actingAs($employeeA)
            ->postJson(route('announcements.acknowledge', $policyAnnouncement->id))
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.is_acknowledged', true);

        $this->assertTrue(
            $policyAnnouncement->fresh()->isReadBy($employeeA->id)
        );
        $this->assertTrue(
            $policyAnnouncement->fresh()->isAcknowledgedBy($employeeA->id)
        );

        $this->assertTrue(
            AuditLog::query()
                ->where('auditable_type', Announcement::class)
                ->where('auditable_id', (string) $policyAnnouncement->id)
                ->where('event', 'announcement.acknowledged')
                ->where('actor_id', $employeeA->id)
                ->exists()
        );

        // 3. Attempting to acknowledge a non-POLICY (INFO) announcement returns 422 validation error
        $this->actingAs($employeeA)
            ->postJson(route('announcements.acknowledge', $infoAnnouncement->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category']);

        // 4. HR Admin views announcements management and gets read/acknowledge counts & recipient statuses
        $hrIndexResponse = $this->actingAs($hrAdmin)
            ->getJson(route('announcements.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $policyAnnouncement->id)
            ->assertJsonPath('data.0.read_count', 1)
            ->assertJsonPath('data.0.acknowledged_count', 1)
            ->assertJsonPath('data.0.total_recipients', 3);

        $recipientStatuses = collect($hrIndexResponse->json('data.0.recipient_statuses'));
        $empAStatus = $recipientStatuses->firstWhere('user_id', $employeeA->id);
        $empBStatus = $recipientStatuses->firstWhere('user_id', $employeeB->id);

        $this->assertNotNull($empAStatus);
        $this->assertTrue((bool) $empAStatus['is_read']);
        $this->assertTrue((bool) $empAStatus['is_acknowledged']);

        $this->assertNotNull($empBStatus);
        $this->assertFalse((bool) $empBStatus['is_read']);
        $this->assertFalse((bool) $empBStatus['is_acknowledged']);
    }
}
