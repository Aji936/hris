<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Leave &amp; Permit Application #{{ $application->id }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #111827;
            margin: 10px 14px;
        }
        .form-container {
            border: 2px solid #111827;
            padding: 10px 12px;
        }
        .header-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .header-subtitle {
            text-align: center;
            font-size: 10.5px;
            color: #374151;
            margin-bottom: 8px;
            border-bottom: 1.5px solid #111827;
            padding-bottom: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 2.5px 5px;
            vertical-align: top;
        }
        .section-box {
            border: 1px solid #111827;
            margin-top: 7px;
            padding: 6px 8px;
        }
        .section-title {
            font-weight: bold;
            font-size: 10.5px;
            text-transform: uppercase;
            background-color: #f3f4f6;
            border-bottom: 1px solid #111827;
            margin: -6px -8px 6px -8px;
            padding: 4px 8px;
        }
        .checkbox-grid td {
            padding: 3px 5px;
            width: 50%;
        }
        .box-check {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1.2px solid #111827;
            text-align: center;
            line-height: 11px;
            font-weight: bold;
            margin-right: 5px;
        }
        .calc-table th, .calc-table td {
            border: 1px solid #111827;
            padding: 4px 6px;
            text-align: center;
        }
        .calc-table th {
            background-color: #f9fafb;
            font-size: 9.5px;
        }
        .signature-table {
            margin-top: 8px;
        }
        .signature-table th, .signature-table td {
            border: 1px solid #111827;
            width: 25%;
            text-align: center;
            padding: 4px 5px;
        }
        .signature-table th {
            background-color: #f3f4f6;
            font-size: 9.5px;
        }
        .sig-space {
            height: 44px;
            vertical-align: bottom !important;
        }
        .muted {
            color: #4b5563;
            font-size: 9px;
        }
        .note-box {
            border-top: 1.5px solid #111827;
            margin-top: 8px;
            padding-top: 6px;
            font-size: 9.5px;
            line-height: 1.32;
        }
        .note-table td {
            vertical-align: top;
            padding: 1.5px 2px;
        }
        .note-en {
            font-style: italic;
            color: #374151;
        }
    </style>
</head>
<body>
    @php
        $emp = $application->employee;
        $activeType = $application->application_type?->value ?? 'ANNUAL_LEAVE';
    @endphp
    <div class="form-container">
        <div class="header-title">LEAVE &amp; PERMIT APPLICATION</div>
        <div class="header-subtitle">FORMULIR PERMOHONAN CUTI &amp; IZIN — {{ $companyName }}</div>

        <table class="meta-table">
            <tr>
                <td style="width: 22%;"><strong>Date / Tanggal</strong></td>
                <td style="width: 28%;">: {{ $application->application_date?->format('Y-m-d') ?? $application->created_at?->format('Y-m-d') }}</td>
                <td style="width: 22%;"><strong>Employee No / NIK</strong></td>
                <td style="width: 28%;">: {{ $application->applicant_employee_code ?? $emp?->employee_code ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Name / Nama</strong></td>
                <td>: {{ $emp?->full_name ?? '-' }}</td>
                <td><strong>Hire Date / Tgl Masuk</strong></td>
                <td>: {{ $application->applicant_hire_date?->format('Y-m-d') ?? $emp?->join_date?->format('Y-m-d') ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Department / Bagian</strong></td>
                <td>: {{ $emp?->department?->name ?? '-' }}</td>
                <td><strong>Position / Jabatan</strong></td>
                <td>: {{ $emp?->position?->title ?? '-' }}</td>
            </tr>
        </table>

        <div class="section-box">
            <div class="section-title">1. Application Type / Jenis Permohonan</div>
            <table class="checkbox-grid">
                <tr>
                    <td>
                        <span class="box-check">{{ $activeType === 'SICK' ? '✓' : '' }}</span>
                        1. Sick / Sakit
                    </td>
                    <td>
                        <span class="box-check">{{ $activeType === 'LATE_COMING' ? '✓' : '' }}</span>
                        4. Late Coming / Datang Terlambat
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="box-check">{{ $activeType === 'SPECIAL_PERMISSION' ? '✓' : '' }}</span>
                        2. Special Permission / Izin Khusus
                    </td>
                    <td>
                        <span class="box-check">{{ $activeType === 'EARLY_LEAVING' ? '✓' : '' }}</span>
                        5. Early Leaving / Pulang Awal
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="box-check">{{ $activeType === 'ANNUAL_LEAVE' ? '✓' : '' }}</span>
                        3. Annual Leave / Cuti Tahunan
                    </td>
                    <td>
                        <span class="box-check">{{ $activeType === 'NO_ABSENT_RECORD' ? '✓' : '' }}</span>
                        6. No Absent Record / Lupa Absen
                    </td>
                </tr>
            </table>
        </div>

        <div class="section-box">
            <div class="section-title">2. Period &amp; Reason / Waktu &amp; Alasan Permohonan</div>
            <table class="meta-table">
                <tr>
                    <td style="width: 25%;"><strong>Start Date / Tgl Mulai</strong></td>
                    <td style="width: 25%;">: {{ $application->start_date?->format('Y-m-d') }}</td>
                    <td style="width: 25%;"><strong>End Date / Tgl Selesai</strong></td>
                    <td style="width: 25%;">: {{ $application->end_date?->format('Y-m-d') }}</td>
                </tr>
                <tr>
                    <td><strong>Time / Jam</strong></td>
                    <td>: {{ $application->start_time ?? '-' }} s/d {{ $application->end_time ?? '-' }}</td>
                    <td><strong>Total Days / Jumlah Hari</strong></td>
                    <td>: {{ $application->total_days }} day(s) / hari</td>
                </tr>
                <tr>
                    <td><strong>Reason / Contents</strong></td>
                    <td colspan="3">: {{ $application->reason }}</td>
                </tr>
            </table>
        </div>

        <div class="section-box">
            <div class="section-title">3. Calculation of Leave (Fill by HRD) / Perhitungan Cuti (Diisi oleh HRD)</div>
            <table class="calc-table">
                <thead>
                    <tr>
                        <th>Previous Leave Balance<br><span class="muted">Sisa Cuti Sebelumnya</span></th>
                        <th>Current Leave Taken<br><span class="muted">Cuti yang Diambil Saat Ini</span></th>
                        <th>Remaining Leave Balance<br><span class="muted">Sisa Cuti Akhir</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>{{ $application->previous_leave_balance ?? '-' }}</strong> hari</td>
                        <td><strong>{{ $application->current_leave_taken ?? $application->total_days }}</strong> hari</td>
                        <td><strong>{{ $application->remaining_leave_balance ?? '-' }}</strong> hari</td>
                    </tr>
                </tbody>
            </table>

            <table class="meta-table" style="margin-top: 8px;">
                <tr>
                    <td style="width: 35%;">
                        <span class="box-check">{{ $application->is_rejected || $application->status->value === 'REJECTED' ? '✓' : '' }}</span>
                        <strong>REJECTED / Ditolak</strong>
                    </td>
                    <td style="width: 65%;">
                        <strong>Reason / Alasan Penolakan:</strong> {{ $application->rejection_reason ?? '-' }}
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <strong>Others / Lain-lain (HRD Notes):</strong> {{ $application->hrd_other_notes ?? '-' }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="signature-table">
            <thead>
                <tr>
                    <th>Applicant<br><span class="muted">Yang Mengajukan</span></th>
                    <th>Approved I<br><span class="muted">Assistant Head of Department</span></th>
                    <th>Approved II<br><span class="muted">Dept. Manager</span></th>
                    <th>Approve III<br><span class="muted">HRD</span></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="sig-space">
                        <strong>{{ $emp?->full_name ?? '-' }}</strong><br>
                        <span class="muted">{{ $application->applicant_signed_at?->format('Y-m-d H:i') ?? 'Signed' }}</span>
                    </td>
                    <td class="sig-space">
                        <strong>{{ $application->approver1?->name ?? '....................' }}</strong><br>
                        <span class="muted">{{ $application->approved_1_at?->format('Y-m-d H:i') ?? 'Pending' }}</span>
                    </td>
                    <td class="sig-space">
                        <strong>{{ $application->approver2?->name ?? '....................' }}</strong><br>
                        <span class="muted">{{ $application->approved_2_at?->format('Y-m-d H:i') ?? 'Pending' }}</span>
                    </td>
                    <td class="sig-space">
                        <strong>{{ $application->approver3?->name ?? $application->approver?->name ?? '....................' }}</strong><br>
                        <span class="muted">{{ ($application->approved_3_at ?? $application->approved_at)?->format('Y-m-d H:i') ?? 'Pending' }}</span>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="note-box">
            <table class="note-table">
                <tr>
                    <td style="width: 13%; font-weight: bold;">
                        Catatan :<br>
                        <span class="note-en" style="font-weight: normal;">Note :</span>
                    </td>
                    <td style="width: 87%;">
                        <table class="note-table">
                            <tr>
                                <td style="width: 3%;">1.</td>
                                <td style="width: 97%;">
                                    Jumlah hari berdasarkan pada hari kerja yang sebenarnya.<br>
                                    <span class="note-en">Total of the days is base on the actual working days.</span>
                                </td>
                            </tr>
                            <tr>
                                <td>2.</td>
                                <td>
                                    Untuk ijin khusus harap perhatikan peraturan perusahaan dan harus sudah diajukan 1 (satu) hari sebelumnya<br>
                                    <span class="note-en">For Special Permission, please refer to company regulation and must be submitted 1 (one) day before</span>
                                </td>
                            </tr>
                            <tr>
                                <td>3.</td>
                                <td>
                                    Untuk keterlambatan atau pulang awal dapat mengambil cuti tahunan selama 0,5 (setengah) hari<br>
                                    <span class="note-en">For late-coming or early leaving could be taken by half day annual leave</span>
                                </td>
                            </tr>
                            <tr>
                                <td>4.</td>
                                <td>
                                    Pengajuan cuti tahunan harus sudah diajukan tidak kurang dari 5 (lima) hari sebelumnya dan akan disesuaikan dengan kebutuhan perusahaan.<br>
                                    <span class="note-en">Proposal for taking annual leave must be submitted minimum 5 (five) days before and it will be adjusted according to office business</span>
                                </td>
                            </tr>
                            <tr>
                                <td>5.</td>
                                <td>
                                    Karyawan harus terlebih dahulu mendapatkan copy persetujuan aplikasi dari HRD<br>
                                    <span class="note-en">The employee must get the copy of application approval first from HRD</span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>

