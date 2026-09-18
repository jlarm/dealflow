export type ContactStatusValue =
    | 'new'
    | 'contacted'
    | 'replied'
    | 'qualified'
    | 'meeting_booked'
    | 'won'
    | 'lost';

export type StatusOption = {
    value: ContactStatusValue;
    label: string;
    color: string;
};

export type Option = {
    value: string;
    label: string;
};

export type Tag = {
    id: number;
    name: string;
    contacts_count?: number;
};

export type CompanyOption = {
    id: number;
    name: string;
    city?: string | null;
    state?: string | null;
};

export type Company = {
    id: number;
    name: string;
    domain: string | null;
    industry: string | null;
    size: string | null;
    city: string | null;
    state: string | null;
    phone: string | null;
    enriched_at: string | null;
    created_at: string | null;
    contacts_count?: number;
};

export type Contact = {
    id: number;
    company_id: number | null;
    name: string;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    email_status: string | null;
    phone: string | null;
    title: string | null;
    seniority: string | null;
    departments: string | null;
    linkedin_url: string | null;
    source_list: string | null;
    status: ContactStatusValue;
    score: number;
    last_contacted_at: string | null;
    unsubscribed_at: string | null;
    created_at: string | null;
    company?: CompanyOption | null;
    tags?: Tag[];
};

export type Activity = {
    id: number;
    type: string;
    type_label: string;
    payload: Record<string, string | undefined>;
    user?: { id: number; name: string } | null;
    created_at: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        per_page: number;
        to: number | null;
        total: number;
    };
};

export type CursorPaginated<T> = {
    data: T[];
    meta: {
        per_page: number;
        next_cursor: string | null;
        prev_cursor: string | null;
    };
};

export type ImportStatusValue = 'processing' | 'completed' | 'failed';

export type Import = {
    id: number;
    filename: string;
    source_list: string;
    status: ImportStatusValue;
    status_label: string;
    row_count: number;
    processed_rows: number;
    failed_rows: number;
    error: string | null;
    user?: { id: number; name: string } | null;
    created_at: string | null;
};

export type ImportFailure = {
    id: number;
    row_number: number;
    errors: string[];
    raw_row: Record<string, string | null>;
};

export type CompanyDetail = {
    label: string;
    value: string;
};

export type CampaignStatusValue = 'draft' | 'active' | 'paused' | 'completed';

export type Campaign = {
    id: number;
    name: string;
    status: CampaignStatusValue;
    status_label: string;
    steps_count?: number;
    enrollments_count?: number;
    active_enrollments_count?: number;
    created_at: string | null;
};

export type CampaignOption = {
    id: number;
    name: string;
    status: CampaignStatusValue;
};

export type CampaignStep = {
    id: number;
    position: number;
    subject: string;
    body: string;
    delay_days: number;
};

export type CampaignEnrollment = {
    id: number;
    contact?: { id: number; name: string; email: string | null };
    sequence_step: number;
    state: 'active' | 'completed' | 'stopped';
    stop_reason: string | null;
    enrolled_at: string;
    next_send_at: string | null;
};
