import { PaginatedResponse } from '@/types/common';

export type EmploymentStatus = 'PROBATION' | 'PERMANENT' | 'CONTRACT' | 'TERMINATED';

export interface DepartmentSummary {
    id: number;
    code: string;
    name: string;
    parent_id?: number | null;
}

export interface PositionSummary {
    id: number;
    code: string;
    title: string;
    level: string;
}

export interface EmployeeSummary {
    id: number;
    user_id: number;
    employee_code: string;
    full_name: string;
    join_date: string;
    status: EmploymentStatus;
    salary_grade: string | null;
    bank_name: string | null;
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
    filters: {
        department_id?: string;
        status?: EmploymentStatus;
        search?: string;
    };
}

