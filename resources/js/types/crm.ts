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
};

export type Company = {
    id: number;
    name: string;
    domain: string | null;
    industry: string | null;
    size: string | null;
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

export type SimplePaginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        from: number | null;
        per_page: number;
        to: number | null;
    };
};
