import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../utils/test-utils';

// Mock SettingsLayout
vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

import EmailNotifications from '@/pages/Settings/Notifications/Email';

const defaultSettings = {
    smtp_enabled: false,
    smtp_from_address: null,
    smtp_from_name: null,
    smtp_recipients: null,
    smtp_host: null,
    smtp_port: null,
    smtp_encryption: null,
    smtp_username: null,
    smtp_password: null,
    smtp_timeout: null,
    resend_enabled: false,
    resend_api_key: null,
    use_instance_email_settings: false,
    deployment_success_email_notifications: false,
    deployment_failure_email_notifications: true,
    status_change_email_notifications: false,
    backup_success_email_notifications: false,
    backup_failure_email_notifications: true,
    scheduled_task_success_email_notifications: false,
    scheduled_task_failure_email_notifications: true,
    server_disk_usage_email_notifications: true,
    server_patch_email_notifications: false,
    traefik_outdated_email_notifications: true,
};

const smtpEnabled = {
    ...defaultSettings,
    smtp_enabled: true,
    smtp_from_address: 'noreply@example.com',
    smtp_host: 'smtp.example.com',
    smtp_port: 587,
};

describe('Email Notifications Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Email Notifications title', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Email Notifications')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Send deployment and server notifications via email/)).toBeInTheDocument();
    });

    it('shows Disabled badge when nothing enabled', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Disabled')).toBeInTheDocument();
    });

    it('shows Enabled badge when smtp enabled', () => {
        render(<EmailNotifications settings={smtpEnabled} />);
        const badges = screen.getAllByText('Enabled');
        expect(badges.length).toBeGreaterThanOrEqual(1);
    });

    it('renders Email Provider heading and SMTP tab', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Email Provider')).toBeInTheDocument();
        // Tab buttons rendered as <button>
        const buttons = screen.getAllByRole('button');
        const tabLabels = buttons.map(b => b.textContent?.trim());
        expect(tabLabels).toContain('SMTP');
        expect(tabLabels).toContain('Resend');
    });

    it('shows SMTP fields in default tab', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable SMTP Email Notifications')).toBeInTheDocument();
        expect(screen.getByText('From Address')).toBeInTheDocument();
        expect(screen.getByText('SMTP Host')).toBeInTheDocument();
    });

    it('switches to Resend tab and shows Resend fields', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        const buttons = screen.getAllByRole('button');
        const resendTab = buttons.find(b => b.textContent?.trim() === 'Resend');
        expect(resendTab).toBeDefined();
        fireEvent.click(resendTab!);
        expect(screen.getByText('Enable Resend Email Notifications')).toBeInTheDocument();
    });

    it('does not show Instance Settings tab when canUseInstanceSettings is false', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        const buttons = screen.getAllByRole('button');
        const instanceTab = buttons.find(b => b.textContent?.trim() === 'Instance Settings');
        expect(instanceTab).toBeUndefined();
    });

    it('shows Instance Settings tab when canUseInstanceSettings is true', () => {
        render(<EmailNotifications settings={defaultSettings} canUseInstanceSettings={true} />);
        const buttons = screen.getAllByRole('button');
        const instanceTab = buttons.find(b => b.textContent?.trim() === 'Instance Settings');
        expect(instanceTab).toBeDefined();
    });

    it('renders event checkboxes', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Deployment Success')).toBeInTheDocument();
        expect(screen.getByText('Deployment Failure')).toBeInTheDocument();
        expect(screen.getByText('Server Disk Usage Alert')).toBeInTheDocument();
    });

    it('renders Enable All and Disable All buttons', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable All')).toBeInTheDocument();
        expect(screen.getByText('Disable All')).toBeInTheDocument();
    });

    it('renders Send Test Email button', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Send Test Email')).toBeInTheDocument();
    });

    it('renders Save Settings button', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Save Settings')).toBeInTheDocument();
    });

    it('shows last test success', () => {
        render(
            <EmailNotifications
                settings={smtpEnabled}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="success"
            />
        );
        expect(screen.getByText(/✓ Success/)).toBeInTheDocument();
    });

    it('shows last test error', () => {
        render(
            <EmailNotifications
                settings={smtpEnabled}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="error"
            />
        );
        expect(screen.getByText(/✗ Failed/)).toBeInTheDocument();
    });

    it('renders Email Provider Options help card', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Email Provider Options')).toBeInTheDocument();
    });

    it('shows SMTP provider description in help card', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Use any SMTP server/)).toBeInTheDocument();
    });

    it('shows Resend provider description in help card', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Modern email API with simple setup/)).toBeInTheDocument();
    });

    it('shows Instance Settings description when canUseInstanceSettings', () => {
        render(<EmailNotifications settings={defaultSettings} canUseInstanceSettings={true} />);
        expect(screen.getByText(/Use the email configuration managed by your Saturn Platform administrator/)).toBeInTheDocument();
    });

    it('renders SMTP encryption select', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Encryption')).toBeInTheDocument();
    });

    it('renders SMTP port and timeout fields', () => {
        render(<EmailNotifications settings={defaultSettings} />);
        expect(screen.getByText('Port')).toBeInTheDocument();
        expect(screen.getByText('Timeout (seconds)')).toBeInTheDocument();
    });
});
