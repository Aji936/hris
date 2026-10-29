# HRIS Engineering Rules & AI Guidelines

Dokumen ini adalah sumber kebenaran utama (*single source of truth*) terkait standar penulisan kode, arsitektur aplikasi, keamanan data, dan kualitas perangkat lunak pada proyek HRIS ini. Setiap developer maupun asisten AI **wajib** membaca dan mematuhi aturan di bawah ini sebelum menulis atau memodifikasi kode.

---

## 1. Konvensi Bahasa & Penamaan (Naming Conventions)

1. **Bahasa Kode & Database Wajib Bahasa Inggris**:
   - Seluruh nama variabel, fungsi, *class*, *interface*, tabel database, kolom, *route*, dan pesan *commit* wajib menggunakan **Bahasa Inggris** yang deskriptif.
   - Bahasa Indonesia hanya digunakan pada lapisan teks antarmuka pengguna (*UI localization/labels*) atau komentar penjelas regulasi spesifik lokal (mis. PPh 21 / BPJS).
2. **Standar Case**:
   - **Classes, Interfaces, Traits, Enums, React Components**: `PascalCase` (contoh: `PayrollCalculationService`, `LeaveRequestForm.tsx`).
   - **Methods, Functions, Variables, Props**: `camelCase` (contoh: `calculateNetSalary()`, `activeEmployeeCount`).
   - **Database Tables**: `snake_case` bentuk **plural/jamak** (contoh: `employees`, `leave_requests`, `payroll_components`).
   - **Database Columns**: `snake_case` bentuk **singular/tunggal** (contoh: `hire_date`, `basic_salary`, `approved_at`).
   - **Foreign Keys**: `<singular_table>_id` (contoh: `employee_id`, `department_id`, `created_by`).
   - **Constants & Enum Keys**: `UPPER_SNAKE_CASE` (contoh: `MAX_ANNUAL_LEAVE_DAYS`).
   - **URL / API Endpoints**: `kebab-case` (contoh: `/core-hr/leave-requests`).

---

## 2. Arsitektur Kode (Clean Architecture / Service-Repository Pattern)

Aplikasi ini menerapkan pemisahan tanggung jawab (*Separation of Concerns*) yang ketat dengan alur satu arah:

`Request / Validator -> Controller -> Service ( / Action ) -> Repository -> Model / Database`

### Larangan Keras: *No Business Logic in Controllers*
- **Controller harus tipis (*Thin Controller*)**: Controller **dilarang keras** memuat perhitungan bisnis, *query* database kompleks, percabangan aturan HR, atau manipulasi transaksi database (`DB::transaction`).
- Tugas Controller **hanya**:
  1. Menerima HTTP Request yang sudah divalidasi oleh `FormRequest` / DTO Validator.
  2. Memeriksa otorisasi akses (`Policy` / `Gate`).
  3. Memanggil satu method pada `Service` atau `Action`.
  4. Mengembalikan HTTP Response (Inertia page render / JSON Resource / Redirect).

### Tanggung Jawab Tiap Layer
- **FormRequest / DTO**: Validasi input, sanitasi tipe data, dan normalisasi payload sebelum masuk ke layer bisnis.
- **Service Layer**: Menyimpan seluruh *business logic* (contoh: validasi kuota cuti, kalkulasi lembur, prorata gaji, pembungkus transaksi database, pengiriman notifikasi/event).
- **Repository Layer**: Mengenkapsulasi *query* ke database (filter, *pagination*, *eager loading*, *locking* baris seperti `lockForUpdate()`). Service tidak boleh membangun *query* ORM mentah yang berulang di banyak tempat.
- **Model / Entity**: Definisi relasi antar-tabel, *type casting*, *scopes* dasar, dan atribut sistem.

---

## 3. Standar Keamanan & Integritas Data HR

Data HR dan Payroll bersifat sangat rahasia (*Personally Identifiable Information* & finansial). Setiap fitur wajib mematuhi aturan berikut:

### A. Role-Based Access Control (RBAC) & Data Scoping
1. **Default Deny**: Semua *endpoint* wajib terautentikasi dan memiliki pengecekan izin eksplisit berbasis *permission* (mis. `employees.view`, `payroll.approve`), bukan sekadar pengecekan string nama *role* yang di-*hardcode*.
2. **Row-Level / Ownership Isolation**:
   - Karyawan biasa (`Employee Self-Service`) hanya boleh mengakses profil, absensi, cuti, dan slip gajinya sendiri.
   - Manajer hanya boleh melihat dan menyetujui data bawahan di departemen/timnya.
   - Data sensitif seperti gaji pokok, komponen tunjangan, NIK/NPWP, dan nomor rekening **tidak boleh** ikut terkirim di *payload* API/Inertia umum kecuali pengguna memiliki *permission* eksplisit.

### B. Audit Trail Wajib (`created_by`, `updated_by`, & Activity Log)
1. Seluruh tabel transaksional dan master utama **wajib** menyertakan kolom audit:
   - `created_at` & `updated_at` (Timestamps dengan timezone konsisten UTC / Asia/Jakarta sesuai konfigurasi aplikasi).
   - `created_by` & `updated_by` (Foreign key merujuk ke `users.id`, `nullable` hanya untuk proses sistem/seeder).
2. Perubahan pada data kritikal (perubahan gaji, persetujuan/penolakan cuti, penerbitan payroll, perubahan status kerja) **wajib** tercatat di *Audit Log* (menyimpan `actor_id`, `event`, `old_values`, `new_values`, `ip_address`, dan `created_at`).

### C. Soft Deletes & Retensi Data Historis
1. **Dilarang melakukan Hard Delete (`DELETE FROM`)** pada entitas bisnis utama (`users`, `employees`, `departments`, `positions`, `attendances`, `leave_requests`, `payrolls`, `payroll_components`).
2. Gunakan mekanisme **Soft Deletes** (`deleted_at` dan opsional `deleted_by`) agar histori ketenagakerjaan, audit pajak, dan histori slip gaji tetap utuh.
3. Untuk perhitungan uang/finansial (gaji, potongan, pajak):
   - **Dilarang menggunakan tipe data `FLOAT` atau `DOUBLE`** karena risiko selisih pembulatan titik mengambang (*floating-point precision error*).
   - Gunakan `DECIMAL(15, 2)` (atau `BIGINT` satuan terkecil mata uang secara konsisten) di database dan *library* aritmetika presisi di level kode.
   - Data `Payroll` yang sudah berstatus `PUBLISHED` / `PAID` bersifat **immutable** (tidak boleh diedit langsung; koreksi harus melalui mekanisme *adjustment/retro* di periode berikutnya atau *void* tercatat).

---

## 4. Panduan Testing Dasar (Definition of Done)

Sebuah fitur atau *task* **belum dianggap selesai** sebelum memenuhi kriteria pengujian berikut:

1. **Unit Tests**:
   - Wajib dibuat untuk seluruh perhitungan murni dan aturan bisnis di `Service` layer (mis. hitung potongan keterlambatan, kuota cuti tahunan, prorata gaji karyawan baru/resign, kalkulasi komponen payroll).
2. **Feature / Integration Tests**:
   - Menguji alur utama (*happy path*) untuk setiap *endpoint* baru.
   - Menguji skenario validasi gagal (*invalid input* / kuota tidak cukup / duplikasi absensi).
   - **Wajib menguji skenario otorisasi (RBAC Test)**: memastikan pengguna tanpa izin (mis. karyawan biasa mencoba mengakses data gaji karyawan lain) ditolak dengan status `403 Forbidden`.
3. **Verifikasi Integritas Audit & Soft Delete**:
   - Memastikan aksi tulis mengisi `created_by` / `updated_by` dengan benar dan aksi hapus hanya mengisi `deleted_at`.
4. **Zero Regression & Static Check**:
   - Seluruh rangkaian test (`test suite`), *linter*, dan pengecekan tipe statis (*TypeScript / PHPStan*) harus lulus tanpa *error* sebelum kode digabungkan.

