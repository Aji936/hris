export interface FlashMessages {
    success?: string | null;
    error?: string | null;
}

export type AttendanceStatusValue =
    | 'PRESENT'
    | 'LATE'
    | 'ABSENT'
    | 'ON_LEAVE'
    | 'SICK'
    | 'HOLIDAY';

export type LeaveRequestStatusValue =
    | 'DRAFT'
    | 'PENDING'
    | 'APPROVED'
    | 'REJECTED'
    | 'CANCELLED';

export type PayslipStatusValue = 'DRAFT' | 'PUBLISHED' | 'PAID';

export type PayrollComponentTypeValue = 'ALLOWANCE' | 'DEDUCTION';

export interface ShiftSummary {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    late_grace_period_minutes: number;
}

export interface EssEmployeeSummary {
    id: number;
    employee_code: string;
    full_name: string;
    department_name?: string | null;
    position_title?: string | null;
    shift?: ShiftSummary | null;
}

export interface AttendanceRecordItem {
    id: number;
    employee_id: number;
    shift_id: number;
    date: string | null;
    clock_in: string | null;
    clock_out: string | null;
    clock_in_ip: string | null;
    clock_out_ip: string | null;
    clock_in_location: string | null;
    clock_out_location: string | null;
    late_minutes: number;
    early_leave_minutes: number;
    work_duration_minutes: number;
    status: AttendanceStatusValue;
    notes: string | null;
    shift?: ShiftSummary;
}

export interface AttendanceProps {
    employee: EssEmployeeSummary | null;
    todayAttendance: AttendanceRecordItem | null;
    monthlyRecords: AttendanceRecordItem[];
    currentMonthLabel: string;
    serverTime: string;
}

export interface LeaveTypeOption {
    id: number;
    code: string;
    name: string;
    default_quota_days?: number;
    is_paid: boolean;
    requires_attachment: boolean;
}

export interface LeaveBalanceItem {
    id: number;
    leave_type_id: number;
    year: number;
    total_quota: number;
    used_quota: number;
    pending_quota: number;
    remaining_quota: number;
    leave_type: LeaveTypeOption | null;
}

export interface LeaveApproverSummary {
    id: number;
    name: string;
    email: string;
}

export interface LeaveRequestItem {
    id: number;
    employee_id: number;
    leave_type_id: number;
    start_date: string | null;
    end_date: string | null;
    total_days: number;
    reason: string;
    attachment_path: string | null;
    status: LeaveRequestStatusValue;
    approver_id: number | null;
    approved_at: string | null;
    rejection_reason: string | null;
    leave_type?: LeaveTypeOption;
    approver?: LeaveApproverSummary | null;
}

export interface LeaveBalanceProps {
    currentYear: number;
    hasEmployeeProfile: boolean;
    leaveBalances: LeaveBalanceItem[];
    leaveTypes: LeaveTypeOption[];
    leaveRequests: LeaveRequestItem[];
}

export interface PayslipItemData {
    id: number;
    payslip_id: number;
    payroll_component_id: number | null;
    name: string;
    type: PayrollComponentTypeValue;
    amount: string;
}

export interface PayslipEmployeeSnapshot {
    employee_code?: string;
    full_name?: string;
    department?: string;
    position?: string;
    bank_name?: string;
    bank_account_number?: string;
    tax_status?: string;
}

export interface PayslipAttendanceSummary {
    present_days?: number;
    late_days?: number;
    late_minutes?: number;
    leave_days?: number;
    sick_days?: number;
    absent_days?: number;
    work_duration_minutes?: number;
}

export interface PayslipPayrollBatchSummary {
    id: number;
    payroll_period: string;
    title: string;
    status: string;
}

export interface PayslipRecord {
    id: number;
    payroll_id: number;
    employee_id: number;
    payslip_number: string;
    employee_snapshot: PayslipEmployeeSnapshot;
    basic_salary: string;
    total_allowance: string;
    total_deduction: string;
    net_salary: string;
    attendance_summary: PayslipAttendanceSummary | null;
    status: PayslipStatusValue;
    payroll?: PayslipPayrollBatchSummary;
    items?: PayslipItemData[];
}

export interface PayslipSummaryMetrics {
    published_count: number;
    latest_net_salary: string;
    latest_period_title: string | null;
    total_net_received: string;
}

export interface PayslipListProps {
    hasEmployeeProfile: boolean;
    employee: EssEmployeeSummary | null;
    summary: PayslipSummaryMetrics;
    payslips: PayslipRecord[];
}

