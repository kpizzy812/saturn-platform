import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import StorageShow from '../../../../resources/js/pages/Storage/Show';
import type { S3Storage } from '../../../../resources/js/types';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            post: vi.fn(),
            delete: vi.fn(),
        },
        Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
            <a href={href}>{children}</a>
        ),
    };
});

describe('Storage/Show', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const mockStorage: S3Storage = {
        id: 1,
        uuid: 'storage-123',
        name: 'Production Backups',
        description: 'Main backup storage',
        bucket: 'prod-backups',
        region: 'us-east-1',
        endpoint: 'https://s3.wasabisys.com',
        path: '/backups/saturn',
        is_usable: true,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-15T00:00:00Z',
    };

    const mockBackups = [
        {
            id: 1,
            uuid: 'backup-1',
            database_name: 'postgres-main',
            database_type: 'postgresql',
            filename: 'backup-2024-01-15.sql.gz',
            size: '125 MB',
            status: 'completed' as const,
            created_at: '2024-01-15T10:00:00Z',
        },
        {
            id: 2,
            uuid: 'backup-2',
            database_name: 'redis-cache',
            database_type: 'redis',
            filename: 'backup-2024-01-14.rdb',
            size: '50 MB',
            status: 'in_progress' as const,
            created_at: '2024-01-14T10:00:00Z',
        },
    ];

    const mockUsageStats = {
        totalBackups: 42,
        totalSize: '5.2 GB',
        lastBackup: '2024-01-15T10:00:00Z',
    };

    it('renders storage details with heading', () => {
        render(<StorageShow storage={mockStorage} />);

        expect(screen.getByRole('heading', { level: 1, name: /production backups/i })).toBeInTheDocument();
        expect(screen.getByText(/wasabi • prod-backups/i)).toBeInTheDocument();
    });

    it('displays storage connection status badge', () => {
        render(<StorageShow storage={mockStorage} />);

        const badges = screen.getAllByText('Connected');
        expect(badges.length).toBeGreaterThan(0);
    });

    it('shows connection failed badge when storage is not usable', () => {
        const failedStorage = { ...mockStorage, is_usable: false };
        render(<StorageShow storage={failedStorage} />);

        const badges = screen.getAllByText('Connection Failed');
        expect(badges.length).toBeGreaterThan(0);
    });

    it('renders action buttons', () => {
        render(<StorageShow storage={mockStorage} />);

        expect(screen.getByRole('button', { name: /test connection/i })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /edit/i })).toBeInTheDocument();
    });

    it('shows test connection button that posts to test endpoint', async () => {
        const { user } = render(<StorageShow storage={mockStorage} />);

        const testButton = screen.getByRole('button', { name: /test connection/i });
        await user.click(testButton);

        expect(router.post).toHaveBeenCalledWith('/storage/storage-123/test');
    });

    it('renders tabs for overview, backups, usage, and settings', () => {
        render(<StorageShow storage={mockStorage} />);

        expect(screen.getByText('Overview')).toBeInTheDocument();
        expect(screen.getByText('Backups')).toBeInTheDocument();
        expect(screen.getByText('Usage')).toBeInTheDocument();
        expect(screen.getByText('Settings')).toBeInTheDocument();
    });

    it('displays connection status in overview tab', () => {
        render(<StorageShow storage={mockStorage} />);

        expect(screen.getByText('Connection Status')).toBeInTheDocument();
        expect(screen.getByText('Status')).toBeInTheDocument();
        expect(screen.getByText('Provider')).toBeInTheDocument();
        expect(screen.getByText('Bucket')).toBeInTheDocument();
        expect(screen.getByText('Region')).toBeInTheDocument();
    });

    it('shows endpoint as clickable link when provided', () => {
        render(<StorageShow storage={mockStorage} />);

        const endpointLink = screen.getByRole('link', { name: /s3\.wasabisys\.com/i });
        expect(endpointLink).toHaveAttribute('href', 'https://s3.wasabisys.com');
        expect(endpointLink).toHaveAttribute('target', '_blank');
        expect(endpointLink).toHaveAttribute('rel', 'noopener noreferrer');
    });

    it('displays storage path when provided', () => {
        render(<StorageShow storage={mockStorage} />);

        expect(screen.getByText('Path')).toBeInTheDocument();
        expect(screen.getByText('/backups/saturn')).toBeInTheDocument();
    });

    it('shows storage description when provided', () => {
        render(<StorageShow storage={mockStorage} />);

        expect(screen.getByText('Description')).toBeInTheDocument();
        expect(screen.getByText('Main backup storage')).toBeInTheDocument();
    });

    it('displays created and updated dates', () => {
        render(<StorageShow storage={mockStorage} />);

        expect(screen.getByText('Created')).toBeInTheDocument();
        expect(screen.getByText('Last Updated')).toBeInTheDocument();
    });

    it('shows empty state when no backups exist', async () => {
        const { user } = render(<StorageShow storage={mockStorage} backups={[]} />);

        const backupsTab = screen.getByRole('tab', { name: /backups/i });
        await user.click(backupsTab);

        expect(screen.getByText('No backups yet')).toBeInTheDocument();
        expect(screen.getByText(/backups using this storage will appear here/i)).toBeInTheDocument();
    });

    it('displays backup list when backups exist', async () => {
        const { user } = render(<StorageShow storage={mockStorage} backups={mockBackups} />);

        const backupsTab = screen.getByRole('tab', { name: /backups/i });
        await user.click(backupsTab);

        expect(screen.getByText('postgres-main')).toBeInTheDocument();
        expect(screen.getByText('redis-cache')).toBeInTheDocument();
        expect(screen.getByText('backup-2024-01-15.sql.gz • 125 MB')).toBeInTheDocument();
        expect(screen.getByText('backup-2024-01-14.rdb • 50 MB')).toBeInTheDocument();
    });

    it('shows usage statistics when provided', async () => {
        const { user } = render(<StorageShow storage={mockStorage} usageStats={mockUsageStats} />);

        const usageTab = screen.getByRole('tab', { name: /usage/i });
        await user.click(usageTab);

        expect(screen.getByText('Total Backups')).toBeInTheDocument();
        expect(screen.getByText('42')).toBeInTheDocument();
        expect(screen.getByText('Total Size')).toBeInTheDocument();
        expect(screen.getByText('5.2 GB')).toBeInTheDocument();
        expect(screen.getByText('Last Backup')).toBeInTheDocument();
    });

    it('shows default values when usage stats are not provided', async () => {
        const { user } = render(<StorageShow storage={mockStorage} />);

        const usageTab = screen.getByRole('tab', { name: /usage/i });
        await user.click(usageTab);

        expect(screen.getByText('0')).toBeInTheDocument();
        expect(screen.getByText('0 GB')).toBeInTheDocument();
        expect(screen.getByText('Never')).toBeInTheDocument();
    });

    it('displays danger zone in settings tab', async () => {
        const { user } = render(<StorageShow storage={mockStorage} />);

        const settingsTab = screen.getByRole('tab', { name: /settings/i });
        await user.click(settingsTab);

        expect(screen.getByText('Danger Zone')).toBeInTheDocument();
        expect(screen.getByText(/delete this storage configuration/i)).toBeInTheDocument();
        expect(screen.getByText(/backups will remain in the s3 bucket/i)).toBeInTheDocument();
    });

    it('shows storage settings link in settings tab', async () => {
        const { user } = render(<StorageShow storage={mockStorage} />);

        const settingsTab = screen.getByRole('tab', { name: /settings/i });
        await user.click(settingsTab);

        const settingsLink = screen.getByRole('link', { name: /open storage settings/i });
        expect(settingsLink).toHaveAttribute('href', '/storage/settings');
    });
});
