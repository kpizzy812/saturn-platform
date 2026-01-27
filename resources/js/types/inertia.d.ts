import type { User, Team } from './models';

// Auth user data shared by HandleInertiaRequests middleware
export interface AuthUser {
    id: number;
    name: string;
    email: string;
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
    };
}

// Team data shared by HandleInertiaRequests middleware
export interface SharedTeam {
    id: number;
    name: string;
    personal_team: boolean;
}

// Use InertiaConfig augmentation for shared page props (Inertia v2 approach)
declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: AuthUser | null;
            team: SharedTeam | null;
            flash: {
                success?: string;
                error?: string;
                warning?: string;
                info?: string;
            };
            appName: string;
            [key: string]: unknown;
        };
    }
}
