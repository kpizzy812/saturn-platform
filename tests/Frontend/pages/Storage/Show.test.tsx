import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import StorageShow from '@/pages/Storage/Show';
import type { S3Storage } from '@/types';

const mockStorage: S3Storage = {
    id: 1,
    uuid: 'storage-uuid-1',
    name: 'production-backups',
    description: 'Production database backups',
    key: 'AKIAIOSFODNN7EXAMPLE',
    secret: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    bucket: 'my-production-backups',
    region: 'us-east-1',
    endpoint: 'https://s3.amazonaws.com',
    path: '/backups',
    is_usable: true,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

const mockBackups = [
    {
        id: 1,
        uuid: 'backup-uuid-1',
        database_name: 'postgres-db',
        database_type: 'postgresql',
        filename: 'backup-2024-01-15.sql.gz',
        size: '1.2 GB',
        status: 'completed' as const,
        created_at: '2024-01-15T00:00:00Z',
    },
    {
        id: 2,
        uuid: 'backup-uuid-2',
        database_name: 'redis-cache',
        database_type: 'redis',
        filename: 'backup-2024-01-16.rdb.gz',
        size: '256 MB',
        status: 'in_progress' as const,
        created_at: '2024-01-16T00:00:00Z',
    },
];

const mockUsageStats = {
    totalBackups: 42,
    totalSize: '10.5 GB',
    lastBackup: '2024-01-16T00:00:00Z',
};

describe('Storage Show Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render storage name in header', () => {
            render(<StorageShow storage={mockStorage} />);

            const nameElements = screen.getAllByText('production-backups');
            expect(nameElements.length).toBeGreaterThan(0);
        });

        it('should render connection status badge', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getAllByText('Connected').length).toBeGreaterThan(0);
        });

        it('should render breadcrumbs', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('Storage')).toBeInTheDocument();
        });
    });

    describe('action buttons', () => {
        it('should render Test Connection button', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('Test Connection')).toBeInTheDocument();
        });

        it('should render Edit button', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('Edit')).toBeInTheDocument();
        });

        it('should render delete button', () => {
            const { container } = render(<StorageShow storage={mockStorage} />);

            const deleteButtons = container.querySelectorAll('button');
            expect(deleteButtons.length).toBeGreaterThan(0);
        });
    });

    describe('tabs', () => {
        it('should render all tabs', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('Overview')).toBeInTheDocument();
            expect(screen.getByText('Backups')).toBeInTheDocument();
            expect(screen.getByText('Usage')).toBeInTheDocument();
            expect(screen.getByText('Settings')).toBeInTheDocument();
        });
    });

    describe('overview tab', () => {
        it('should render connection status information', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getAllByText('Connected').length).toBeGreaterThan(0);
        });

        it('should render storage details', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('my-production-backups')).toBeInTheDocument();
            expect(screen.getByText('us-east-1')).toBeInTheDocument();
        });

        it('should render description when present', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('Production database backups')).toBeInTheDocument();
        });
    });

    describe('backups tab', () => {
        it('should render no backups message when empty', () => {
            render(<StorageShow storage={mockStorage} backups={[]} />);

            expect(screen.getByText('No backups yet')).toBeInTheDocument();
        });

        it('should render backups list when present', () => {
            render(<StorageShow storage={mockStorage} backups={mockBackups} />);

            expect(screen.getByText('postgres-db')).toBeInTheDocument();
            expect(screen.getByText('redis-cache')).toBeInTheDocument();
        });

        it('should display backup sizes', () => {
            render(<StorageShow storage={mockStorage} backups={mockBackups} />);

            expect(screen.getByText(/1.2 GB/)).toBeInTheDocument();
            expect(screen.getByText(/256 MB/)).toBeInTheDocument();
        });
    });

    describe('usage tab', () => {
        it('should render usage stats', () => {
            render(<StorageShow storage={mockStorage} usageStats={mockUsageStats} />);

            expect(screen.getByText('Total Backups')).toBeInTheDocument();
            expect(screen.getByText('Total Size')).toBeInTheDocument();
            expect(screen.getByText('Last Backup')).toBeInTheDocument();
        });

        it('should display usage values', () => {
            render(<StorageShow storage={mockStorage} usageStats={mockUsageStats} />);

            expect(screen.getByText('42')).toBeInTheDocument();
            expect(screen.getByText('10.5 GB')).toBeInTheDocument();
        });

        it('should handle missing usage stats', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('0')).toBeInTheDocument();
            expect(screen.getByText('0 GB')).toBeInTheDocument();
        });
    });

    describe('settings tab', () => {
        it('should render settings section', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('Storage Settings')).toBeInTheDocument();
        });

        it('should render danger zone', () => {
            render(<StorageShow storage={mockStorage} />);

            expect(screen.getByText('Danger Zone')).toBeInTheDocument();
        });
    });

    describe('connection status', () => {
        it('should show connected badge for usable storage', () => {
            render(<StorageShow storage={mockStorage} />);

            const connectedBadges = screen.getAllByText('Connected');
            expect(connectedBadges.length).toBeGreaterThan(0);
        });

        it('should show connection failed badge for unusable storage', () => {
            const unusableStorage = { ...mockStorage, is_usable: false };
            render(<StorageShow storage={unusableStorage} />);

            const failedBadges = screen.getAllByText('Connection Failed');
            expect(failedBadges.length).toBeGreaterThan(0);
        });
    });

    describe('edge cases', () => {
        it('should handle storage without description', () => {
            const storageWithoutDesc = { ...mockStorage, description: null };
            render(<StorageShow storage={storageWithoutDesc} />);

            expect(screen.getAllByText('production-backups').length).toBeGreaterThan(0);
        });

        it('should handle storage without endpoint', () => {
            const storageWithoutEndpoint = { ...mockStorage, endpoint: null };
            render(<StorageShow storage={storageWithoutEndpoint} />);

            expect(screen.getAllByText('production-backups').length).toBeGreaterThan(0);
        });

        it('should handle storage without path', () => {
            const storageWithoutPath = { ...mockStorage, path: null };
            render(<StorageShow storage={storageWithoutPath} />);

            expect(screen.getAllByText('production-backups').length).toBeGreaterThan(0);
        });
    });
});
