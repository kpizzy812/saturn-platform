import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../utils/test-utils';

// Mock SettingsLayout
vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

import SlackNotifications from '@/pages/Settings/Notifications/Slack';

const defaultSettings = {
    slack_enabled: false,
    slack_webhook_url: null,
    deployment_success_slack_notifications: false,
    deployment_failure_slack_notifications: true,
    status_change_slack_notifications: false,
    backup_success_slack_notifications: false,
    backup_failure_slack_notifications: true,
    scheduled_task_success_slack_notifications: false,
    scheduled_task_failure_slack_notifications: true,
    docker_cleanup_slack_notifications: false,
    server_disk_usage_slack_notifications: true,
    server_reachable_slack_notifications: false,
    server_unreachable_slack_notifications: true,
    server_patch_slack_notifications: false,
    traefik_outdated_slack_notifications: true,
};

const enabledSettings = {
    ...defaultSettings,
    slack_enabled: true,
    slack_webhook_url: 'https://hooks.slack.com/services/T00/B00/xxx',
};

describe('Slack Notifications Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Slack Notifications title', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText('Slack Notifications')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Send deployment and server notifications to Slack via webhook/)).toBeInTheDocument();
    });

    it('shows Disabled badge when not enabled', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText('Disabled')).toBeInTheDocument();
    });

    it('shows Enabled badge when enabled', () => {
        render(<SlackNotifications settings={enabledSettings} />);
        const badges = screen.getAllByText('Enabled');
        expect(badges.length).toBeGreaterThanOrEqual(1);
    });

    it('renders Enable Slack Notifications checkbox', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable Slack Notifications')).toBeInTheDocument();
    });

    it('renders Webhook URL input with placeholder', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByPlaceholderText('https://hooks.slack.com/services/...')).toBeInTheDocument();
    });

    it('pre-fills webhook URL when provided', () => {
        render(<SlackNotifications settings={enabledSettings} />);
        expect(screen.getByDisplayValue('https://hooks.slack.com/services/T00/B00/xxx')).toBeInTheDocument();
    });

    it('renders all event checkboxes', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText('Deployment Success')).toBeInTheDocument();
        expect(screen.getByText('Deployment Failure')).toBeInTheDocument();
        expect(screen.getByText('Server Unreachable')).toBeInTheDocument();
        expect(screen.getByText('Traefik Outdated')).toBeInTheDocument();
    });

    it('renders Enable All and Disable All buttons', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable All')).toBeInTheDocument();
        expect(screen.getByText('Disable All')).toBeInTheDocument();
    });

    it('renders Send Test Notification button', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText('Send Test Notification')).toBeInTheDocument();
    });

    it('renders Save Settings button', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText('Save Settings')).toBeInTheDocument();
    });

    it('shows last test success status', () => {
        render(
            <SlackNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="success"
            />
        );
        expect(screen.getByText(/✓ Success/)).toBeInTheDocument();
    });

    it('shows last test error status', () => {
        render(
            <SlackNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="error"
            />
        );
        expect(screen.getByText(/✗ Failed/)).toBeInTheDocument();
    });

    it('renders How to Set Up card', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText('How to Set Up')).toBeInTheDocument();
    });

    it('renders setup instructions', () => {
        render(<SlackNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Go to your Slack workspace/)).toBeInTheDocument();
    });
});
