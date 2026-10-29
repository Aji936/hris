# HRIS Development Roadmap

Dokumen ini berisi rencana pengerjaan bertahap (*phased delivery*) untuk membangun aplikasi HRIS dari nol hingga siap produksi. Setiap item dalam *checklist* harus mematuhi standar arsitektur dan *Definition of Done* pada `.ai/rules.md`.

---

## Phase 1: Foundation, RBAC & Core HR
**Tujuan**: Menyiapkan pondasi proyek, infrastruktur arsitektur modular, keamanan RBAC + Audit Trail, serta pengelolaan struktur organisasi dan data induk karyawan.

### 1.1 Project Initialization & Base Infrastructure
- [ ] Inisialisasi proyek Laravel 12 + Inertia.js v2 (React + TypeScript + Tailwind CSS) dan konfigurasi PostgreSQL & Redis.
- [ ] Siapkan struktur direktori modular di `app/Modules/` (`RBAC`, `CoreHR`, `Attendance`, `Payroll`) dan `resources/js/Modules/`.
- [ ] Buat infrastruktur bersama di `app/Shared/`: `BaseRepository`, trait `HasAuditColumns` (`created_by`, `updated_by` otomatis), konfigurasi `SoftDeletes`, dan helper presisi desimal finansial.
- [ ] Konfigurasi *testing environment* (Pest PHP + database testing terisolasi) dan *static analysis* / *linter* (PHPStan / Pint + ESLint / TypeScript check).

### 1.2 Authentication, RBAC & Audit Trail
- [ ] Implementasi autentikasi sesi aman (Login, Logout, Reset Password, proteksi *rate-limiting*).
- [ ] Integrasi manajemen Role & Permission (`super_admin`, `hr_admin`, `finance_payroll`, `manager`, `employee`) beserta *Seeder* izin awal.
- [ ] Implementasi pencatatan *Audit Log* otomatis untuk perubahan data kritikal.
- [ ] Bangun halaman manajemen User & Role (CRUD dengan pembatasan Policy ketat) dan *layout* navigasi dinamis berdasarkan *permission* user yang sedang login.

### 1.3 Organization & Employee Master Data (Core HR)
- [ ] Buat migrasi, Model, Repository, Service, Policy, dan FormRequest untuk `Department` (mendukung hierarki `parent_id`).
- [ ] Buat migrasi, Model, Repository, Service, Policy, dan FormRequest untuk `Position` (termasuk referensi rentang gaji grade).
- [ ] Buat migrasi, Model, Repository, Service, Policy, dan FormRequest untuk `Employee` (termasuk enkripsi kolom sensitif NIK, NPWP, dan nomor rekening).
- [ ] Bangun antarmuka *Employee Directory*, detail profil karyawan, penautan akun `User`, serta tampilan *Employee Self-Service* (karyawan melihat profilnya sendiri tanpa membocorkan data karyawan lain).
- [ ] Tulis Unit Test & Feature Test (termasuk pengujian isolasi RBAC `403 Forbidden`) untuk seluruh modul Phase 1.

---

## Phase 2: Attendance & Leave Management
**Tujuan**: Mengelola pencatatan kehadiran harian karyawan, perhitungan keterlambatan/jam kerja, kuota cuti, serta alur persetujuan (*approval workflow*) berjenjang.

### 2.1 Daily Attendance Management
- [ ] Buat migrasi, Model, Repository, dan `AttendanceService` untuk entitas `Attendance` (dengan *unique constraint* karyawan per tanggal).
- [ ] Implementasi fitur *Clock-In* dan *Clock-Out* mandiri untuk karyawan beserta kalkulasi otomatis `status`, `late_minutes`, dan `work_minutes` di `Service` layer.
- [ ] Bangun halaman pemantauan kehadiran harian & koreksi manual oleh HR Admin (wajib tercatat di *Audit Log*).
- [ ] Siapkan *scheduled command* harian untuk menandai ketidakhadiran atau sinkronisasi status hari cuti (`ON_LEAVE`).

### 2.2 Leave Balance & Approval Workflow
- [ ] Buat skema saldo cuti (`leave_balances`) dan migrasi/model untuk `LeaveRequest`.
- [ ] Implementasi `LeaveRequestService` untuk validasi irisan tanggal (*overlapping dates*), perhitungan hari kerja efektif, dan pengecekan kecukupan kuota cuti.
- [ ] Implementasi alur persetujuan (*Approve / Reject*) oleh atasan langsung (`manager_id`) atau HR Admin menggunakan transaksi database dan *row locking* saat memotong saldo cuti.
- [ ] Bangun UI pengajuan cuti karyawan (termasuk unggah lampiran surat sakit) dan *inbox* persetujuan untuk Manajer/HR.
- [ ] Tulis Unit Test untuk kalkulasi kehadiran & kuota cuti, serta Feature Test untuk alur *approval* dan pembatasan akses antar-departemen.

---

## Phase 3: Payroll Engine & Reporting
**Tujuan**: Mengotomatisasi perhitungan gaji bulanan yang akurat berdasarkan data Core HR + rekap Attendance/Leave, menerbitkan slip gaji, dan menyajikan laporan eksekutif.

### 3.1 Payroll Components & Employee Compensation Setup
- [ ] Buat migrasi, Model, Repository, dan Service untuk `PayrollComponent` (`EARNING` & `DEDUCTION`, tipe kalkulasi `FIXED`, `DAILY_ATTENDANCE`, `PERCENTAGE_OF_BASIC`, `MANUAL`).
- [ ] Buat fitur penugasan komponen tunjangan/potongan rutin per karyawan (`employee_payroll_components`).
- [ ] Bangun halaman konfigurasi komponen payroll dengan proteksi khusus hanya untuk `finance_payroll` & `super_admin`.

### 3.2 Monthly Payroll Processing & Payslip Distribution
- [ ] Buat migrasi dan model untuk `Payroll` dan `PayrollItem` (dengan aturan *immutability* saat status `PUBLISHED` / `PAID`).
- [ ] Bangun `PayrollCalculationService` yang menarik gaji pokok dari `CoreHR` dan rekap hadir/cuti tak berbayar (`UNPAID`) dari `Attendance` menggunakan aritmetika desimal presisi.
- [ ] Implementasi *batch processing* penggajian bulanan via Redis Queue (`ProcessMonthlyPayrollJob`) beserta alur status `DRAFT -> APPROVED -> PUBLISHED -> PAID`.
- [ ] Bangun fitur *Payslip Viewer* dan unduh PDF slip gaji terproteksi untuk masing-masing karyawan di portal *Self-Service*.

### 3.3 HR & Payroll Reporting
- [ ] Bangun *Dashboard* ringkasan metrik HR (total karyawan aktif, tren kehadiran, pengajuan cuti tertunda, ringkasan pengeluaran gaji bulanan).
- [ ] Buat fitur laporan rekapitulasi kehadiran bulanan per departemen dan ekspor ke CSV/Excel.
- [ ] Buat laporan rekapitulasi *Payroll Register* & daftar transfer bank bulanan untuk tim Finance.
- [ ] Lakukan pengujian *end-to-end* menyeluruh (Unit Test presisi kalkulasi gaji, uji konkurensi, audit keamanan RBAC seluruh modul) sebelum rilis v1.0.

