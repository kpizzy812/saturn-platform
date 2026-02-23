import type { User, Team, Notification } from './models';

// Notifications data shared by HandleInertiaRequests middleware
export interface SharedNotifications {
    unreadCount: number;
    recent: Notification[];
}

// System notifications data (superadmin only)
export interface SharedSystemNotifications {
    unreadCount: number;
}

// Auth user data shared by HandleInertiaRequests middleware
export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    email_verified_at: string | null;
    is_superadmin: boolean;
    two_factor_enabled: boolean;
    role: string;
    permissions: {
        isAdmin: boolean;
        isOwner: boolean;
        isMember: boolean;
        isDeveloper: boolean;
        isViewer: boolean;
        granular: Record<string, boolean>;
    };
}

// Team data shared by HandleInertiaRequests middleware
export interface SharedTeam {
    id: number;
    name: string;
    personal_team: boolean;
}

// Team item in teams list (includes role)
export interface SharedTeamItem {
    id: number;
    name: string;
    personal_team: boolean;
    role: string;
}

// Helper type to cast typed objects to Inertia router-compatible payload.
// Inertia's router.post/put/patch expects Record<string, FormDataConvertible>,
// but TypeScript interfaces don't satisfy index signatures.
// Usage: router.post('/path', data as RouterPayload)
export type RouterPayload = Record<string, import('@inertiajs/core').FormDataConvertible>;

// Use InertiaConfig augmentation for shared page props (Inertia v2 approach)
declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: AuthUser | null;
            team: SharedTeam | null;
            teams: SharedTeamItem[];
            flash: {
                success?: string;
                error?: string;
                warning?: string;
                info?: string;
            };
            notifications: SharedNotifications;
            systemNotifications: SharedSystemNotifications | null;
            appName: string;
            aiChatEnabled: boolean;
            [key: string]: unknown;
        };
    }
}
