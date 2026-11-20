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
    | 'APPROVED_L1'
    | 'APPROVED_L2'
    | 'APPROVED_HRD'
    | 'APPROVED'
    | 'REJECTED'
    | 'CANCELLED';

export type LeavePermitTypeValue =
    | 'SICK'
    | 'SPECIAL_PERMISSION'
    | 'ANNUAL_LEAVE'
    | 'LATE_COMING'
    | 'EARLY_LEAVING'
    | 'NO_ABSENT_RECORD';

export type PayslipStatusValue = 'DRAFT' | 'PUBLISHED' | 'PAID';

export type PayrollComponentTypeValue = 'ALLOWANCE' | 'DEDUCTION';

export type MaritalStatusValue = 'SINGLE' | 'MARRIED' | 'DIVORCED' | 'WIDOWED';

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
    date_of_birth?: string | null;
    retirement_date?: string | null;
    age?: number | null;
    days_until_retirement?: number | null;
    phone_number?: string | null;
    address?: string | null;
    marital_status?: MaritalStatusValue;
    dependents_count?: number;
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
    application_date?: string | null;
    applicant_employee_code?: string | null;
    applicant_hire_date?: string | null;
    application_type?: LeavePermitTypeValue;
    application_type_label?: string;
    start_date: string | null;
    end_date: string | null;
    start_time?: string | null;
    end_time?: string | null;
    total_days: number;
    reason: string;
    attachment_path: string | null;
    previous_leave_balance?: number | null;
    current_leave_taken?: number | null;
    remaining_leave_balance?: number | null;
    status: LeaveRequestStatusValue;
    is_rejected?: boolean;
    approver_id: number | null;
    approved_at: string | null;
    rejection_reason: string | null;
    hrd_other_notes?: string | null;
    applicant_signed_at?: string | null;
    approved_1_by?: number | null;
    approved_1_at?: string | null;
    approved_2_by?: number | null;
    approved_2_at?: string | null;
    approved_3_by?: number | null;
    approved_3_at?: string | null;
    employee?: {
        id?: number;
        employee_code?: string;
        full_name?: string;
        join_date?: string | null;
    };
    leave_type?: LeaveTypeOption;
    approver?: LeaveApproverSummary | null;
    approver1?: LeaveApproverSummary | null;
    approver2?: LeaveApproverSummary | null;
    approver3?: LeaveApproverSummary | null;
}

export interface LeaveApplicantProfile {
    id: number;
    employee_code: string;
    full_name: string;
    join_date: string | null;
    department_name?: string | null;
    position_title?: string | null;
}

export interface EmployeeLeaveBalanceRow {
    employee_id: number;
    employee_code: string;
    full_name: string;
    department_name: string;
    position_title: string;
    status: string;
    year: number;
    has_balance_record: boolean;
    leave_balance_id: number | null;
    total_quota: number;
    used_quota: number;
    pending_quota: number;
    remaining_quota: number;
}

export interface LeaveBalanceProps {
    currentYear: number;
    hasEmployeeProfile: boolean;
    employeeProfile?: LeaveApplicantProfile | null;
    canApprove?: boolean;
    isHrd?: boolean;
    initialTab?: 'requests' | 'balances' | string;
    leaveBalances: LeaveBalanceItem[];
    employeeLeaveBalances?: EmployeeLeaveBalanceRow[];
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
    date_of_birth?: string | null;
    retirement_date?: string | null;
    age?: number | null;
    days_until_retirement?: number | null;
    phone_number?: string | null;
    address?: string | null;
    marital_status?: MaritalStatusValue;
    dependents_count?: number;
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

