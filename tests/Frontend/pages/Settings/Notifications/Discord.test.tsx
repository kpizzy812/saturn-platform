import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../utils/test-utils';

// Mock SettingsLayout
vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

import DiscordNotifications from '@/pages/Settings/Notifications/Discord';

const defaultSettings = {
    discord_enabled: false,
    discord_webhook_url: null,
    discord_ping_enabled: false,
    deployment_success_discord_notifications: false,
    deployment_failure_discord_notifications: true,
    status_change_discord_notifications: false,
    backup_success_discord_notifications: false,
    backup_failure_discord_notifications: true,
    scheduled_task_success_discord_notifications: false,
    scheduled_task_failure_discord_notifications: true,
    docker_cleanup_discord_notifications: false,
    server_disk_usage_discord_notifications: true,
    server_reachable_discord_notifications: false,
    server_unreachable_discord_notifications: true,
    server_patch_discord_notifications: false,
    traefik_outdated_discord_notifications: true,
};

const enabledSettings = {
    ...defaultSettings,
    discord_enabled: true,
    discord_webhook_url: 'https://discord.com/api/webhooks/123/abc',
};

describe('Discord Notifications Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Discord Notifications title', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Discord Notifications')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Send deployment and server notifications to Discord via webhook/)).toBeInTheDocument();
    });

    it('shows Disabled badge when not enabled', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Disabled')).toBeInTheDocument();
    });

    it('shows Enabled badge when enabled', () => {
        render(<DiscordNotifications settings={enabledSettings} />);
        const badges = screen.getAllByText('Enabled');
        expect(badges.length).toBeGreaterThanOrEqual(1);
    });

    it('renders Enable Discord Notifications checkbox', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable Discord Notifications')).toBeInTheDocument();
    });

    it('renders Webhook URL input', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Webhook URL')).toBeInTheDocument();
    });

    it('renders ping checkbox', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Enable @everyone ping for critical notifications/)).toBeInTheDocument();
    });

    it('renders Event Selection heading', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Event Selection')).toBeInTheDocument();
    });

    it('renders all event checkboxes', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Deployment Success')).toBeInTheDocument();
        expect(screen.getByText('Deployment Failure')).toBeInTheDocument();
        expect(screen.getByText('Application Status Change')).toBeInTheDocument();
        expect(screen.getByText('Backup Success')).toBeInTheDocument();
        expect(screen.getByText('Backup Failure')).toBeInTheDocument();
        expect(screen.getByText('Docker Cleanup')).toBeInTheDocument();
        expect(screen.getByText('Server Disk Usage Alert')).toBeInTheDocument();
        expect(screen.getByText('Server Unreachable')).toBeInTheDocument();
        expect(screen.getByText('Traefik Outdated')).toBeInTheDocument();
    });

    it('renders Enable All and Disable All buttons', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable All')).toBeInTheDocument();
        expect(screen.getByText('Disable All')).toBeInTheDocument();
    });

    it('renders Send Test Notification button', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Send Test Notification')).toBeInTheDocument();
    });

    it('renders Save Settings button', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Save Settings')).toBeInTheDocument();
    });

    it('shows last test info when provided', () => {
        render(
            <DiscordNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="success"
            />
        );
        expect(screen.getByText(/Last test:/)).toBeInTheDocument();
        expect(screen.getByText(/✓ Success/)).toBeInTheDocument();
    });

    it('shows failed test status', () => {
        render(
            <DiscordNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="error"
            />
        );
        expect(screen.getByText(/✗ Failed/)).toBeInTheDocument();
    });

    it('renders How to Set Up card', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('How to Set Up')).toBeInTheDocument();
    });

    it('renders Configuration heading', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        expect(screen.getByText('Configuration')).toBeInTheDocument();
    });

    it('shows webhook URL placeholder', () => {
        render(<DiscordNotifications settings={defaultSettings} />);
        const input = screen.getByPlaceholderText('https://discord.com/api/webhooks/...');
        expect(input).toBeInTheDocument();
    });

    it('pre-fills webhook URL when provided', () => {
        render(<DiscordNotifications settings={enabledSettings} />);
        const input = screen.getByDisplayValue('https://discord.com/api/webhooks/123/abc');
        expect(input).toBeInTheDocument();
    });
});
