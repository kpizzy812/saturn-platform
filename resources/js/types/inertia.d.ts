import type { User, Team } from './models';

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
            appName: string;
            aiChatEnabled: boolean;
            [key: string]: unknown;
        };
    }
}
