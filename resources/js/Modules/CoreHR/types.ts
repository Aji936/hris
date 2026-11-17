import { PaginatedResponse } from '@/types/common';

export type EmploymentStatus = 'PROBATION' | 'PERMANENT' | 'CONTRACT' | 'RETIRED' | 'TERMINATED';

export type MaritalStatus = 'SINGLE' | 'MARRIED' | 'DIVORCED' | 'WIDOWED';

export interface DepartmentSummary {
    id: number;
    code: string;
    name: string;
    description?: string | null;
    parent_id?: number | null;
    is_active?: boolean;
}

export interface PositionSummary {
    id: number;
    department_id?: number;
    code: string;
    title: string;
    level: string;
    description?: string | null;
    base_salary_min?: string | null;
    base_salary_max?: string | null;
    is_active?: boolean;
}

export interface LeaveTypeMasterItem {
    id: number;
    code: string;
    name: string;
    default_quota_days: number;
    is_paid: boolean;
    requires_attachment: boolean;
    is_active: boolean;
}

export interface ManagerOption {
    id: number;
    employee_code: string;
    full_name: string;
    department_id: number;
}

export interface EmployeeSummary {
    id: number;
    user_id: number;
    employee_code: string;
    full_name: string;
    date_of_birth: string | null;
    retirement_date: string | null;
    age: number | null;
    days_until_retirement: number | null;
    phone_number: string | null;
    address: string | null;
    marital_status: MaritalStatus;
    dependents_count: number;
    join_date: string;
    status: EmploymentStatus;
    salary_grade: string | null;
    bank_name: string | null;
    national_id_number?: string | null;
    tax_id_number?: string | null;
    bank_account_number?: string | null;
    user?: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
    };
    department?: DepartmentSummary;
    position?: PositionSummary;
    manager?: {
        id: number;
        employee_code: string;
        full_name: string;
    } | null;
}

export interface EmployeeDirectoryPageProps {
    employees: PaginatedResponse<EmployeeSummary>;
    departments: DepartmentSummary[];
    positions?: PositionSummary[];
    leaveTypes?: LeaveTypeMasterItem[];
    managerOptions?: ManagerOption[];
    canManage?: boolean;
    filters: {
        department_id?: string;
        status?: EmploymentStatus;
        search?: string;
    };
}
