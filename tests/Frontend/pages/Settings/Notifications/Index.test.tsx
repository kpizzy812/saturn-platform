import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../utils/test-utils';

// Mock SettingsLayout
vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

// Mock icons
vi.mock('@/components/icons/Discord', () => ({
    Discord: ({ className }: any) => <svg data-testid="discord-icon" className={className} />,
}));
vi.mock('@/components/icons/Slack', () => ({
    Slack: ({ className }: any) => <svg data-testid="slack-icon" className={className} />,
}));
vi.mock('@/components/icons/Telegram', () => ({
    Telegram: ({ className }: any) => <svg data-testid="telegram-icon" className={className} />,
}));

import NotificationsIndex from '@/pages/Settings/Notifications/Index';

const allEnabled = {
    discord: { enabled: true, configured: true },
    slack: { enabled: true, configured: true },
    telegram: { enabled: false, configured: true },
    email: { enabled: false, configured: false },
    webhook: { enabled: true, configured: true },
    pushover: { enabled: false, configured: false },
};

const allDisabled = {
    discord: { enabled: false, configured: false },
    slack: { enabled: false, configured: false },
    telegram: { enabled: false, configured: false },
    email: { enabled: false, configured: false },
    webhook: { enabled: false, configured: false },
    pushover: { enabled: false, configured: false },
};

describe('Notifications Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Notification Channels title', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText('Notification Channels')).toBeInTheDocument();
    });

    it('renders description text', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText(/Configure how you want to receive notifications/)).toBeInTheDocument();
    });

    it('displays all six channel names', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText('Discord')).toBeInTheDocument();
        expect(screen.getByText('Slack')).toBeInTheDocument();
        expect(screen.getByText('Telegram')).toBeInTheDocument();
        expect(screen.getByText('Email')).toBeInTheDocument();
        expect(screen.getByText('Webhook')).toBeInTheDocument();
        expect(screen.getByText('Pushover')).toBeInTheDocument();
    });

    it('shows enabled and configured counts', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        // Counts rendered: 3 enabled, 4 configured
        const counters = screen.getAllByText(/^[0-9]+$/);
        const values = counters.map(el => el.textContent);
        expect(values).toContain('3');
        expect(values).toContain('4');
    });

    it('shows Configured label', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText('Configured')).toBeInTheDocument();
    });

    it('shows Enabled badge for enabled channels', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        const enabledBadges = screen.getAllByText('Enabled');
        // 3 enabled channels + 1 overview label
        expect(enabledBadges.length).toBeGreaterThanOrEqual(3);
    });

    it('shows Disabled badge for disabled channels', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        const disabledBadges = screen.getAllByText('Disabled');
        expect(disabledBadges.length).toBe(3); // telegram, email, pushover
    });

    it('shows Not Configured badge for unconfigured channels', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        const notConfigured = screen.getAllByText('Not Configured');
        expect(notConfigured.length).toBe(2); // email, pushover
    });

    it('shows Configure buttons for all channels', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        const configureButtons = screen.getAllByText('Configure');
        expect(configureButtons.length).toBe(6);
    });

    it('shows Enable/Disable toggle buttons for configured channels', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        // discord, slack, webhook are enabled+configured -> show "Disable"
        const disableButtons = screen.getAllByText('Disable');
        expect(disableButtons.length).toBe(3);
        // telegram is configured but disabled -> show "Enable"
        const enableButtons = screen.getAllByText('Enable');
        expect(enableButtons.length).toBeGreaterThanOrEqual(1);
    });

    it('renders Available Channels section', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText('Available Channels')).toBeInTheDocument();
    });

    it('renders About Notifications section', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText('About Notifications')).toBeInTheDocument();
    });

    it('shows all zero counts when nothing enabled', () => {
        render(<NotificationsIndex channels={allDisabled} />);
        const zeros = screen.getAllByText('0');
        expect(zeros.length).toBe(2); // enabled=0, configured=0
    });

    it('renders event types list', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText('Deployment Success/Failure')).toBeInTheDocument();
        expect(screen.getByText('Application Status Changes')).toBeInTheDocument();
        expect(screen.getByText('Backup Success/Failure')).toBeInTheDocument();
        expect(screen.getByText('Server Reachability Status')).toBeInTheDocument();
    });

    it('renders best practices section', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText('Best Practices')).toBeInTheDocument();
        expect(screen.getByText(/Configure at least one notification channel/)).toBeInTheDocument();
    });

    it('renders channel descriptions', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        expect(screen.getByText(/Send notifications to Discord channels via webhooks/)).toBeInTheDocument();
        expect(screen.getByText(/Send notifications to Slack channels via webhooks/)).toBeInTheDocument();
        expect(screen.getByText(/Send notifications to Telegram via bot/)).toBeInTheDocument();
    });

    it('has configure links pointing to correct paths', () => {
        render(<NotificationsIndex channels={allEnabled} />);
        const links = screen.getAllByRole('link');
        const hrefs = links.map(l => l.getAttribute('href'));
        expect(hrefs).toContain('/settings/notifications/discord');
        expect(hrefs).toContain('/settings/notifications/slack');
        expect(hrefs).toContain('/settings/notifications/telegram');
        expect(hrefs).toContain('/settings/notifications/email');
        expect(hrefs).toContain('/settings/notifications/webhook');
        expect(hrefs).toContain('/settings/notifications/pushover');
    });
});
