import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../utils/test-utils';

// Mock SettingsLayout
vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

import WebhookNotifications from '@/pages/Settings/Notifications/Webhook';

const defaultSettings = {
    webhook_enabled: false,
    webhook_url: null,
    deployment_success_webhook_notifications: false,
    deployment_failure_webhook_notifications: true,
    status_change_webhook_notifications: false,
    backup_success_webhook_notifications: false,
    backup_failure_webhook_notifications: true,
    scheduled_task_success_webhook_notifications: false,
    scheduled_task_failure_webhook_notifications: true,
    docker_cleanup_success_webhook_notifications: false,
    docker_cleanup_failure_webhook_notifications: false,
    server_disk_usage_webhook_notifications: true,
    server_reachable_webhook_notifications: false,
    server_unreachable_webhook_notifications: true,
    server_patch_webhook_notifications: false,
    traefik_outdated_webhook_notifications: true,
};

const enabledSettings = {
    ...defaultSettings,
    webhook_enabled: true,
    webhook_url: 'https://api.example.com/webhooks/saturn',
};

describe('Webhook Notifications Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Webhook Notifications title', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Webhook Notifications')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Send deployment and server event data to a custom webhook endpoint/)).toBeInTheDocument();
    });

    it('shows Disabled badge when not enabled', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Disabled')).toBeInTheDocument();
    });

    it('shows Enabled badge when enabled', () => {
        render(<WebhookNotifications settings={enabledSettings} />);
        const badges = screen.getAllByText('Enabled');
        expect(badges.length).toBeGreaterThanOrEqual(1);
    });

    it('renders Enable Webhook Notifications checkbox', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable Webhook Notifications')).toBeInTheDocument();
    });

    it('renders Webhook URL input', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByPlaceholderText('https://api.example.com/webhooks/saturn')).toBeInTheDocument();
    });

    it('pre-fills webhook URL when provided', () => {
        render(<WebhookNotifications settings={enabledSettings} />);
        expect(screen.getByDisplayValue('https://api.example.com/webhooks/saturn')).toBeInTheDocument();
    });

    it('renders Payload Format section', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Payload Format')).toBeInTheDocument();
    });

    it('shows example toggle button', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Show Example')).toBeInTheDocument();
    });

    it('toggles payload example visibility', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        fireEvent.click(screen.getByText('Show Example'));
        expect(screen.getByText('Hide Example')).toBeInTheDocument();
        expect(screen.getByText(/deployment_success/)).toBeInTheDocument();
    });

    it('shows webhook metadata', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Content-Type:/)).toBeInTheDocument();
        expect(screen.getByText(/Timeout:/)).toBeInTheDocument();
        expect(screen.getByText(/Retries:/)).toBeInTheDocument();
    });

    it('renders event checkboxes', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Deployment Success')).toBeInTheDocument();
        expect(screen.getByText('Deployment Failure')).toBeInTheDocument();
        expect(screen.getByText('Docker Cleanup Success')).toBeInTheDocument();
        expect(screen.getByText('Docker Cleanup Failure')).toBeInTheDocument();
    });

    it('renders Enable All and Disable All buttons', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable All')).toBeInTheDocument();
        expect(screen.getByText('Disable All')).toBeInTheDocument();
    });

    it('renders Send Test Webhook button', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Send Test Webhook')).toBeInTheDocument();
    });

    it('renders Save Settings button', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Save Settings')).toBeInTheDocument();
    });

    it('shows last test success', () => {
        render(
            <WebhookNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="success"
            />
        );
        expect(screen.getByText(/✓ Success/)).toBeInTheDocument();
    });

    it('shows last test error', () => {
        render(
            <WebhookNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="error"
            />
        );
        expect(screen.getByText(/✗ Failed/)).toBeInTheDocument();
    });

    it('renders Webhook Integration Guide card', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Webhook Integration Guide')).toBeInTheDocument();
    });

    it('shows setup instructions', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Create a POST endpoint that accepts JSON/)).toBeInTheDocument();
    });

    it('shows security recommendations', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Security Recommendations')).toBeInTheDocument();
        expect(screen.getByText(/Use HTTPS for your webhook endpoint/)).toBeInTheDocument();
    });

    it('shows common use cases', () => {
        render(<WebhookNotifications settings={defaultSettings} />);
        expect(screen.getByText('Common Use Cases')).toBeInTheDocument();
        expect(screen.getByText(/Trigger CI\/CD pipelines on deployment events/)).toBeInTheDocument();
    });
});
