import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../utils/test-utils';

// Mock SettingsLayout
vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

import PushoverNotifications from '@/pages/Settings/Notifications/Pushover';

const defaultSettings = {
    pushover_enabled: false,
    pushover_user_key: null,
    pushover_api_token: null,
    deployment_success_pushover_notifications: false,
    deployment_failure_pushover_notifications: true,
    status_change_pushover_notifications: false,
    backup_success_pushover_notifications: false,
    backup_failure_pushover_notifications: true,
    scheduled_task_success_pushover_notifications: false,
    scheduled_task_failure_pushover_notifications: true,
    docker_cleanup_pushover_notifications: false,
    server_disk_usage_pushover_notifications: true,
    server_reachable_pushover_notifications: false,
    server_unreachable_pushover_notifications: true,
    server_patch_pushover_notifications: false,
    traefik_outdated_pushover_notifications: true,
};

const enabledSettings = {
    ...defaultSettings,
    pushover_enabled: true,
    pushover_user_key: 'uQiRzpo4DXghDmr9QzzfQu27cmVRsG',
    pushover_api_token: 'azGDORePK8gMaC0QOYAMyEEuzJnyUi',
};

describe('Pushover Notifications Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Pushover Notifications title', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('Pushover Notifications')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Send deployment and server push notifications to your mobile devices/)).toBeInTheDocument();
    });

    it('shows Disabled badge when not enabled', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('Disabled')).toBeInTheDocument();
    });

    it('shows Enabled badge when enabled', () => {
        render(<PushoverNotifications settings={enabledSettings} />);
        const badges = screen.getAllByText('Enabled');
        expect(badges.length).toBeGreaterThanOrEqual(1);
    });

    it('renders Enable Pushover Notifications checkbox', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable Pushover Notifications')).toBeInTheDocument();
    });

    it('renders User Key input', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('User Key')).toBeInTheDocument();
    });

    it('renders API Token input', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('API Token')).toBeInTheDocument();
    });

    it('renders priority levels info', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('Priority Levels')).toBeInTheDocument();
    });

    it('renders event checkboxes with priority badges', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('Deployment Success')).toBeInTheDocument();
        expect(screen.getByText('Deployment Failure')).toBeInTheDocument();
        // Priority badges
        const lowBadges = screen.getAllByText('low');
        expect(lowBadges.length).toBeGreaterThan(0);
        const highBadges = screen.getAllByText('high');
        expect(highBadges.length).toBeGreaterThan(0);
    });

    it('shows emergency priority for Server Unreachable', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('emergency')).toBeInTheDocument();
    });

    it('renders Enable All and Disable All buttons', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable All')).toBeInTheDocument();
        expect(screen.getByText('Disable All')).toBeInTheDocument();
    });

    it('renders Send Test Notification button', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('Send Test Notification')).toBeInTheDocument();
    });

    it('renders Save Settings button', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('Save Settings')).toBeInTheDocument();
    });

    it('shows last test success status', () => {
        render(
            <PushoverNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="success"
            />
        );
        expect(screen.getByText(/✓ Success/)).toBeInTheDocument();
    });

    it('shows last test error status', () => {
        render(
            <PushoverNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="error"
            />
        );
        expect(screen.getByText(/✗ Failed/)).toBeInTheDocument();
    });

    it('renders How to Set Up card', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText('How to Set Up')).toBeInTheDocument();
    });

    it('shows setup instructions', () => {
        render(<PushoverNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Sign up for a Pushover account/)).toBeInTheDocument();
    });
});
