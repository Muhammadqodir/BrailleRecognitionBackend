export interface AuthUser {
    id: number;
    name: string;
    email: string;
}

export interface AppRoutes {
    login: string;
    logout: string;
    dashboard: string;
    users: string;
    translations: string;
    subscriptions: string;
    settingsPassword: string;
    settingsPasswordUpdate: string;
}

export interface SharedProps {
    auth: {
        user: AuthUser | null;
    };
    routes: AppRoutes;
    [key: string]: unknown;
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
    from: number | null;
    to: number | null;
    links: PaginationLink[];
    prev_page_url: string | null;
    next_page_url: string | null;
}
