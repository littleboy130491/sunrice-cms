export interface User {
    id: number;
    name: string | null;
    email: string | null;
}

export interface NavItem {
    label: string;
    href: string;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

export interface LocaleInfo {
    main: string;
    available: string[];
    names: Record<string, string>;
}

export interface SharedProps {
    auth: { user: User | null };
    permissions: string[] | ['*'];
    navigation: NavGroup[];
    locales: LocaleInfo;
    flash: { success?: string | null; error?: string | null };
    adminPath: string;
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface ColumnDef {
    key: string;
    label: string;
    sortable: boolean;
    type: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
    from: number | null;
    to: number | null;
}

export interface AdminField {
    handle: string;
    type: string;
    label: string;
    required?: boolean;
    display_type?: string;
    config?: Record<string, unknown>;
    fields?: AdminField[];
    fieldsets?: { handle: string; title: string; fields: AdminField[] }[];
}

export interface AdminTab {
    handle: string;
    label: string;
    fields: AdminField[];
}

export type Json = string | number | boolean | null | Json[] | { [key: string]: Json };
export type JsonRecord = { [key: string]: Json };
