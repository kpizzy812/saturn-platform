import type { User, Team } from './models';

declare module '@inertiajs/react' {
    interface PageProps {
        auth: {
            user: User;
            team: Team;
        };
        flash: {
            success?: string;
            error?: string;
            warning?: string;
            info?: string;
        };
    }
}
