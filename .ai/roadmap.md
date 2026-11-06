# HRIS Development Roadmap

Dokumen ini berisi rencana pengerjaan bertahap (*phased delivery*) untuk membangun aplikasi HRIS dari nol hingga siap produksi. Setiap item dalam *checklist* mematuhi standar arsitektur dan *Definition of Done* pada `.ai/rules.md`.

---

## Phase 1: Foundation, RBAC & Core HR — ✅ COMPLETED
**Tujuan**: Menyiapkan pondasi proyek, infrastruktur arsitektur modular, keamanan RBAC + Audit Trail, serta pengelolaan struktur organisasi dan data induk karyawan.

### 1.1 Project Initialization & Base Infrastructure
- [x] Inisialisasi proyek Laravel 12 + Inertia.js v2 (React + TypeScript + Tailwind CSS) dan konfigurasi PostgreSQL & Redis.
- [x] Siapkan struktur direktori modular di `app/Modules/` (`RBAC`, `CoreHR`, `Attendance`, `Payroll`) dan `resources/js/Modules/`.
- [x] Buat infrastruktur bersama di `app/Shared/`: `BaseRepository`, trait `HasAuditColumns` (`created_by`, `updated_by` otomatis), konfigurasi `SoftDeletes`, dan helper presisi desimal finansial (`MoneyHelper`).
- [x] Konfigurasi *testing environment* (PHPUnit + database testing terisolasi) dan *static analysis* / *linter* (Pint + ESLint / TypeScript check).

### 1.2 Authentication, RBAC & Audit Trail
- [x] Implementasi autentikasi sesi aman (Login, Logout, Reset Password, proteksi *rate-limiting*).
- [x] Integrasi manajemen Role & Permission (`Superadmin`, `HR Admin`, `Manager`, `Employee`) beserta `RoleAndPermissionSeeder`.
- [x] Implementasi pencatatan *Audit Log* otomatis (`AuditLogService`) untuk perubahan data kritikal.
- [x] Bangun manajemen Role & User dengan pembatasan Policy ketat dan *layout* navigasi dinamis berdasarkan *permission* user yang sedang login.

### 1.3 Organization & Employee Master Data (Core HR)
- [x] Buat migrasi, Model, Repository, Policy, dan relasi untuk `Department` (mendukung hierarki `parent_id`).
- [x] Buat migrasi, Model, Repository, Policy, dan relasi untuk `Position` (termasuk referensi rentang gaji grade).
- [x] Buat migrasi, Model, Repository, `EmployeeOnboardingService`, Policy, dan FormRequest untuk `Employee` (termasuk enkripsi kolom sensitif NIK, NPWP, dan nomor rekening).
- [x] Bangun antarmuka *Employee Directory*, detail profil karyawan, penautan akun `User`, serta isolasi data *Employee Self-Service*.
- [x] Tulis Feature Test lengkap (termasuk pengujian isolasi RBAC `403 Forbidden`) untuk seluruh modul Phase 1.

---

## Phase 2: Attendance & Leave Management — ✅ COMPLETED
**Tujuan**: Mengelola pencatatan kehadiran harian karyawan, perhitungan keterlambatan/jam kerja, kuota cuti, serta alur persetujuan (*approval workflow*) berjenjang.

### 2.1 Daily Attendance & Shift Engine
- [x] Buat migrasi, Model, Repository, dan `AttendanceService` untuk entitas `Shift` dan `Attendance` (dengan *unique constraint* karyawan per tanggal).
- [x] Implementasi fitur *Clock-In* dan *Clock-Out* mandiri untuk karyawan beserta kalkulasi otomatis `status`, `late_minutes`, `early_leave_minutes`, dan `work_duration_minutes` di `Service` layer.
- [x] Bangun endpoint pemantauan kehadiran harian dengan proteksi Policy & Audit Log.

### 2.2 Leave Balance & Approval Workflow
- [x] Buat skema master tipe cuti (`leave_types`), saldo cuti (`leave_balances`), dan pengajuan cuti (`leave_requests`).
- [x] Implementasi `LeaveService` untuk validasi irisan tanggal (*overlapping dates*), perhitungan durasi hari cuti, pengecekan kecukupan kuota, dan validasi lampiran wajib.
- [x] Implementasi alur persetujuan (*Approve / Reject*) oleh atasan langsung (`manager_id`) atau HR Admin menggunakan transaksi database dan `lockForUpdate()` saat mengunci/memotong saldo cuti.
- [x] Tulis Feature Test komprehensif untuk kalkulasi kehadiran, kuota cuti, *approval workflow*, dan isolasi akses antar-karyawan/tim.

---

## Phase 3: Payroll Engine & Reporting — ✅ COMPLETED
**Tujuan**: Mengotomatisasi perhitungan gaji bulanan yang akurat berdasarkan data Core HR + rekap Attendance/Leave, menerbitkan slip gaji, dan menyajikan laporan eksekutif.

### 3.1 Payroll Components & Employee Compensation Setup
- [x] Buat migrasi, Model, Repository, dan `SalaryAssignmentService` untuk `PayrollComponent`, `SalaryStructure`, dan `EmployeeSalary` (`DECIMAL(15, 2)`).
- [x] Implementasi pengarsipan otomatis riwayat gaji sebelumnya saat terdapat kenaikan/penyesuaian kompensasi baru.
- [x] Proteksi endpoint manajemen struktur gaji hanya untuk HR Admin & Superadmin.

### 3.2 Monthly Payroll Processing & Payslip Snapshot Engine
- [x] Buat migrasi dan model untuk `Payroll`, `Payslip`, dan `PayslipItem` (dengan aturan *immutability* ketat saat status `APPROVED` / `PUBLISHED` / `PAID`).
- [x] Bangun `PayrollService` yang menyimpan *historical employee snapshot*, mengevaluasi komponen tetap & formula dinamis, serta memotong ketidakhadiran/cuti tak berbayar (`UNPAID`) menggunakan `MoneyHelper` (`bcmath`).
- [x] Implementasi *batch processing* penggajian bulanan via Queue (`ProcessMonthlyPayrollJob`) beserta *approval lock* oleh Superadmin / Finance Director.

### 3.3 PDF Payslip Generator & HR Analytic Reports
- [x] Integrasi `barryvdh/laravel-dompdf` dan pembuatan template resmi `resources/views/payroll/payslip-pdf.blade.php`.
- [x] Bangun `PayslipPdfService` & endpoint unduh PDF terproteksi (`403 Forbidden` bagi non-pemilik dan slip berstatus `DRAFT`).
- [x] Bangun `PayrollReportService` untuk ekspor laporan rekapitulasi payroll bulanan ke CSV terproteksi.

---

## Phase 4: Frontend UI / Inertia React Portal — ✅ COMPLETED
**Tujuan**: Menyediakan antarmuka web modern, responsif, dan *strictly-typed* (React 19 + TypeScript + Tailwind CSS v4) untuk karyawan dan pengelola HR.

### 4.1 Employee Self-Service (ESS) Portal & HR Directory
- [x] Konfigurasi navigasi modular dinamis berbasis RBAC (`AppSidebar`, `NavMain`, `Dashboard`) dan resolusi halaman modular Vite (`resolveModulePage` + `app.blade.php`).
- [x] Bangun halaman Presensi Harian (`resources/js/Modules/Attendance/Pages/Index.tsx`) dengan jam digital *real-time*, aksi Clock-In/Out bergeolokasi, dan tabel riwayat bulanan.
- [x] Bangun halaman Pengajuan & Saldo Cuti (`resources/js/Modules/Attendance/Pages/Leave/Index.tsx`) lengkap dengan kartu kuota, modal pengajuan berlampiran file, dan riwayat status persetujuan.
- [x] Bangun halaman Slip Gaji Mandiri (`resources/js/Modules/Payroll/Pages/Payslips/MyPayslips.tsx`) dengan rincian pendapatan vs potongan dan unduh slip PDF resmi.
- [x] Verifikasi `tsc --noEmit` bebas `any` (`0` error) dan Feature Test portal Inertia (`EmployeeSelfServicePortalTest`).

---

## Phase 5: Production Readiness, Security Hardening & End-to-End Verification — ✅ COMPLETED
**Tujuan**: Memastikan kesiapan rilis produksi melalui penguatan keamanan HTTP/RBAC/Immutability, data demo komprehensif, dan pengujian integrasi hulu-ke-hilir.

### 5.1 Demo Seeder, Security Hardening & E2E Suite
- [x] Buat `ProductionDemoSeeder` idempoten (4 Departemen hierarkis, 8 Posisi, 1 Superadmin, 1 HR Admin, 2 Manager, 10 Employee dengan NIK/NPWP/Rekening terenkripsi, saldo cuti 2026, riwayat presensi 30 hari, dan 1 batch payroll bulan lalu berstatus `APPROVED` / `PUBLISHED`).
- [x] Audit anti-IDOR di seluruh Policy & Controller, perketat *immutability hook* (`updating`, `deleting`, `forceDeleting`) pada `Payroll`, `Payslip`, dan `PayslipItem`, serta tambahkan `SecurityHeadersMiddleware`.
- [x] Buat pengujian integrasi menyeluruh `tests/Feature/EndToEndHrisWorkflowTest.php` dari *onboarding* hingga unduh PDF slip gaji.
- [x] Verifikasi akhir seluruh test suite (`php artisan test`), *code style* (`pint`), TypeScript (`npm run types`), dan *production bundle* (`npm run build`).
