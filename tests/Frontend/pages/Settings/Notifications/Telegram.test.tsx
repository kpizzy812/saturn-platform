import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../../utils/test-utils';

// Mock SettingsLayout
vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

import TelegramNotifications from '@/pages/Settings/Notifications/Telegram';

const defaultSettings = {
    telegram_enabled: false,
    telegram_token: null,
    telegram_chat_id: null,
    deployment_success_telegram_notifications: false,
    deployment_failure_telegram_notifications: true,
    status_change_telegram_notifications: false,
    backup_success_telegram_notifications: false,
    backup_failure_telegram_notifications: true,
    scheduled_task_success_telegram_notifications: false,
    scheduled_task_failure_telegram_notifications: true,
    docker_cleanup_telegram_notifications: false,
    server_disk_usage_telegram_notifications: true,
    server_reachable_telegram_notifications: false,
    server_unreachable_telegram_notifications: true,
    server_patch_telegram_notifications: false,
    traefik_outdated_telegram_notifications: true,
    telegram_notifications_deployment_success_thread_id: null,
    telegram_notifications_deployment_failure_thread_id: null,
    telegram_notifications_status_change_thread_id: null,
    telegram_notifications_backup_success_thread_id: null,
    telegram_notifications_backup_failure_thread_id: null,
    telegram_notifications_scheduled_task_success_thread_id: null,
    telegram_notifications_scheduled_task_failure_thread_id: null,
    telegram_notifications_docker_cleanup_thread_id: null,
    telegram_notifications_server_disk_usage_thread_id: null,
    telegram_notifications_server_reachable_thread_id: null,
    telegram_notifications_server_unreachable_thread_id: null,
    telegram_notifications_server_patch_thread_id: null,
    telegram_notifications_traefik_outdated_thread_id: null,
};

const enabledSettings = {
    ...defaultSettings,
    telegram_enabled: true,
    telegram_token: '123456789:ABCdefGHIjklMNOpqrsTUVwxyz',
    telegram_chat_id: '-1001234567890',
};

describe('Telegram Notifications Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Telegram Notifications title', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Telegram Notifications')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Send deployment and server notifications to Telegram via bot/)).toBeInTheDocument();
    });

    it('shows Disabled badge when not enabled', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Disabled')).toBeInTheDocument();
    });

    it('shows Enabled badge when enabled', () => {
        render(<TelegramNotifications settings={enabledSettings} />);
        const badges = screen.getAllByText('Enabled');
        expect(badges.length).toBeGreaterThanOrEqual(1);
    });

    it('renders Enable Telegram Notifications checkbox', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable Telegram Notifications')).toBeInTheDocument();
    });

    it('renders Bot Token input', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Bot Token')).toBeInTheDocument();
    });

    it('renders Chat ID input', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Chat ID')).toBeInTheDocument();
    });

    it('renders chat ID help info', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText(/How to get your Chat ID:/)).toBeInTheDocument();
    });

    it('renders event checkboxes', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Deployment Success')).toBeInTheDocument();
        expect(screen.getByText('Deployment Failure')).toBeInTheDocument();
        expect(screen.getByText('Server Unreachable')).toBeInTheDocument();
    });

    it('renders Enable All and Disable All buttons', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Enable All')).toBeInTheDocument();
        expect(screen.getByText('Disable All')).toBeInTheDocument();
    });

    it('renders Send Test Message button', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Send Test Message')).toBeInTheDocument();
    });

    it('renders Save Settings button', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('Save Settings')).toBeInTheDocument();
    });

    it('renders advanced settings toggle', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Show Advanced Settings/)).toBeInTheDocument();
    });

    it('shows thread ID fields when advanced settings expanded', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        fireEvent.click(screen.getByText(/Show Advanced Settings/));
        expect(screen.getByText('Deployment Success Thread ID')).toBeInTheDocument();
        expect(screen.getByText('Deployment Failure Thread ID')).toBeInTheDocument();
    });

    it('hides thread ID fields by default', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.queryByText('Deployment Success Thread ID')).not.toBeInTheDocument();
    });

    it('shows last test success status', () => {
        render(
            <TelegramNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="success"
            />
        );
        expect(screen.getByText(/✓ Success/)).toBeInTheDocument();
    });

    it('shows last test error status', () => {
        render(
            <TelegramNotifications
                settings={enabledSettings}
                lastTestAt="2024-01-15T10:30:00Z"
                lastTestStatus="error"
            />
        );
        expect(screen.getByText(/✗ Failed/)).toBeInTheDocument();
    });

    it('renders How to Set Up card', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText('How to Set Up')).toBeInTheDocument();
    });

    it('shows BotFather instructions', () => {
        render(<TelegramNotifications settings={defaultSettings} />);
        expect(screen.getByText(/Open Telegram and search for "BotFather"/)).toBeInTheDocument();
    });
});
