export interface AuditableEntity {
    id: number | string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    created_by: number | string | null;
    updated_by: number | string | null;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: PaginationMeta;
}

