<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Payslip - {{ $payslip->payslip_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 20px;
            line-height: 1.45;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 3px 0;
        }
        .company-subtitle {
            font-size: 10px;
            color: #475569;
            margin: 0;
        }
        .slip-badge {
            text-align: right;
        }
        .slip-title {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }
        .slip-meta {
            font-size: 10px;
            color: #334155;
            margin: 1px 0;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #f1f5f9;
            color: #0f172a;
            padding: 6px 8px;
            border-left: 3px solid #0f172a;
            margin: 14px 0 8px 0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .info-table td {
            padding: 4px 6px;
            vertical-align: top;
            font-size: 10.5px;
        }
        .info-label {
            color: #64748b;
            width: 18%;
        }
        .info-value {
            color: #0f172a;
            font-weight: bold;
            width: 32%;
        }
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .attendance-table th,
        .attendance-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: center;
            font-size: 10px;
        }
        .attendance-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: bold;
        }
        .financial-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .financial-col {
            width: 50%;
            vertical-align: top;
            border: 1px solid #cbd5e1;
            padding: 0;
        }
        .sub-table {
            width: 100%;
            border-collapse: collapse;
        }
        .sub-table th {
            background-color: #0f172a;
            color: #ffffff;
            text-align: left;
            padding: 7px 10px;
            font-size: 10.5px;
            text-transform: uppercase;
        }
        .sub-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10.5px;
        }
        .amount-cell {
            text-align: right;
            white-space: nowrap;
        }
        .subtotal-row td {
            font-weight: bold;
            background-color: #f8fafc;
            border-top: 1px solid #94a3b8;
            padding: 8px 10px;
        }
        .net-pay-box {
            width: 100%;
            border: 2px solid #0f172a;
            background-color: #f8fafc;
            margin-top: 16px;
            border-collapse: collapse;
        }
        .net-pay-box td {
            padding: 12px 14px;
        }
        .net-pay-label {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
        }
        .net-pay-amount {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
        }
        .footer-note {
            margin-top: 22px;
            font-size: 9px;
            color: #64748b;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <p class="company-name">{{ $companyName }}</p>
                <p class="company-subtitle">Human Resource Information &amp; Payroll System — Confidential Statement</p>
            </td>
            <td class="slip-badge" style="width: 45%;">
                <p class="slip-title">Official Payslip</p>
                <p class="slip-meta"><strong>No. Slip:</strong> {{ $payslip->payslip_number }}</p>
                <p class="slip-meta"><strong>Periode:</strong> {{ $payroll?->title ?? $payroll?->payroll_period }}</p>
                <p class="slip-meta"><strong>Status:</strong> {{ $payslip->status->value }}</p>
            </td>
        </tr>
    </table>

    <div class="section-title">Data Karyawan (Snapshot Historis)</div>
    <table class="info-table">
        <tr>
            <td class="info-label">Nama Lengkap</td>
            <td class="info-value">{{ $snapshot['full_name'] ?? '-' }}</td>
            <td class="info-label">Departemen</td>
            <td class="info-value">{{ $snapshot['department_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">NIP / Kode</td>
            <td class="info-value">{{ $snapshot['employee_code'] ?? '-' }}</td>
            <td class="info-label">Jabatan</td>
            <td class="info-value">{{ $snapshot['position_title'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">NIK (KTP)</td>
            <td class="info-value">{{ $snapshot['national_id_number'] ?? '-' }}</td>
            <td class="info-label">Rekening Bank</td>
            <td class="info-value">
                {{ $snapshot['bank_name'] ?? '-' }}
                @if (!empty($snapshot['bank_account_number']))
                    ({{ $snapshot['bank_account_number'] }})
                @endif
            </td>
        </tr>
    </table>

    <div class="section-title">Rekapitulasi Kehadiran</div>
    <table class="attendance-table">
        <thead>
            <tr>
                <th>Hari Hadir</th>
                <th>Cuti Berbayar</th>
                <th>Sakit</th>
                <th>Alpa</th>
                <th>Cuti Tak Berbayar</th>
                <th>Terlambat</th>
                <th>Lembur</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $attendance['present_days'] ?? 0 }} hari</td>
                <td>{{ $attendance['leave_days'] ?? 0 }} hari</td>
                <td>{{ $attendance['sick_days'] ?? 0 }} hari</td>
                <td>{{ $attendance['absent_days'] ?? 0 }} hari</td>
                <td>{{ $attendance['unpaid_leave_days'] ?? 0 }} hari</td>
                <td>{{ $attendance['late_days'] ?? 0 }}x ({{ $attendance['late_minutes'] ?? 0 }} mnt)</td>
                <td>{{ $attendance['overtime_minutes'] ?? 0 }} mnt</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Rincian Pendapatan &amp; Potongan</div>
    <table class="financial-grid">
        <tr>
            <td class="financial-col">
                <table class="sub-table">
                    <thead>
                        <tr>
                            <th>Pendapatan (Earnings)</th>
                            <th class="amount-cell">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Gaji Pokok (Basic Salary)</td>
                            <td class="amount-cell">{{ $formatRupiah($payslip->basic_salary) }}</td>
                        </tr>
                        @foreach ($allowances as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td class="amount-cell">{{ $formatRupiah($item->amount) }}</td>
                            </tr>
                        @endforeach
                        <tr class="subtotal-row">
                            <td>Total Pendapatan Kotor</td>
                            <td class="amount-cell">{{ $formatRupiah($grossSalary) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
            <td class="financial-col">
                <table class="sub-table">
                    <thead>
                        <tr>
                            <th>Potongan (Deductions)</th>
                            <th class="amount-cell">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deductions as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td class="amount-cell">{{ $formatRupiah($item->amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" style="color: #64748b; text-align: center;">Tidak ada potongan pada periode ini</td>
                            </tr>
                        @endforelse
                        <tr class="subtotal-row">
                            <td>Total Potongan</td>
                            <td class="amount-cell">{{ $formatRupiah($payslip->total_deduction) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="net-pay-box">
        <tr>
            <td class="net-pay-label">Total Net Pay (Take Home Pay)</td>
            <td class="net-pay-amount">{{ $formatRupiah($payslip->net_salary) }}</td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen ini diterbitkan secara elektronik oleh sistem {{ $companyName }} pada {{ $generatedAt }} dan sah tanpa tanda tangan basah.
    </div>
</body>
</html>
