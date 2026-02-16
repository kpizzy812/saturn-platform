import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import StorageIndex from '@/pages/Storage/Index';
import type { S3Storage } from '@/types';

const mockStorages: S3Storage[] = [
    {
        id: 1,
        uuid: 'storage-uuid-1',
        name: 'production-backups',
        description: 'Production database backups',
        key: 'AKIAIOSFODNN7EXAMPLE',
        secret: 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        bucket: 'my-production-backups',
        region: 'us-east-1',
        endpoint: null,
        path: '/backups',
        is_usable: true,
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-15T00:00:00Z',
    },
    {
        id: 2,
        uuid: 'storage-uuid-2',
        name: 'staging-storage',
        description: 'Staging environment storage',
        key: 'AKIATEST123',
        secret: 'test-secret-key',
        bucket: 'staging-bucket',
        region: 'eu-west-1',
        endpoint: 'https://s3.wasabisys.com',
        path: null,
        is_usable: false,
        created_at: '2024-02-01T00:00:00Z',
        updated_at: '2024-02-15T00:00:00Z',
    },
];

describe('Storage Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('Storage')).toBeInTheDocument();
            expect(screen.getByText('Manage S3-compatible backup destinations')).toBeInTheDocument();
        });

        it('should render Add Storage button', () => {
            render(<StorageIndex storages={mockStorages} />);

            const addButton = screen.getByText('Add Storage');
            expect(addButton).toBeInTheDocument();
        });

        it('should render search input', () => {
            render(<StorageIndex storages={mockStorages} />);

            const searchInput = screen.getByPlaceholderText('Search storage...');
            expect(searchInput).toBeInTheDocument();
        });

        it('should render all storages in the grid', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('production-backups')).toBeInTheDocument();
            expect(screen.getByText('staging-storage')).toBeInTheDocument();
        });

        it('should display storage descriptions', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('Production database backups')).toBeInTheDocument();
            expect(screen.getByText('Staging environment storage')).toBeInTheDocument();
        });

        it('should display bucket names', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('my-production-backups')).toBeInTheDocument();
            expect(screen.getByText('staging-bucket')).toBeInTheDocument();
        });

        it('should display region information', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('us-east-1')).toBeInTheDocument();
            expect(screen.getByText('eu-west-1')).toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should render empty state when no storages', () => {
            render(<StorageIndex storages={[]} />);

            expect(screen.getByText('No storage configured')).toBeInTheDocument();
            expect(screen.getByText('Add an S3-compatible storage provider to enable database backups.')).toBeInTheDocument();
        });

        it('should render Add Storage button in empty state', () => {
            render(<StorageIndex storages={[]} />);

            const addButtons = screen.getAllByText('Add Storage');
            expect(addButtons.length).toBeGreaterThan(0);
        });

        it('should not render storage cards in empty state', () => {
            render(<StorageIndex storages={[]} />);

            expect(screen.queryByText('production-backups')).not.toBeInTheDocument();
            expect(screen.queryByText('staging-storage')).not.toBeInTheDocument();
        });
    });

    describe('connection status', () => {
        it('should show Connected status for usable storage', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('Connected')).toBeInTheDocument();
        });

        it('should show Connection Failed status for unusable storage', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('Connection Failed')).toBeInTheDocument();
        });
    });

    describe('search functionality', () => {
        it('should filter storages by name', async () => {
            const { user } = render(<StorageIndex storages={mockStorages} />);

            const searchInput = screen.getByPlaceholderText('Search storage...');
            await user.type(searchInput, 'production');

            expect(screen.getByText('production-backups')).toBeInTheDocument();
            expect(screen.queryByText('staging-storage')).not.toBeInTheDocument();
        });

        it('should show no results message when search has no matches', async () => {
            const { user } = render(<StorageIndex storages={mockStorages} />);

            const searchInput = screen.getByPlaceholderText('Search storage...');
            await user.type(searchInput, 'nonexistent');

            expect(screen.getByText('No storage found')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your search query.')).toBeInTheDocument();
        });
    });

    describe('navigation', () => {
        it('should link to storage detail page', () => {
            render(<StorageIndex storages={mockStorages} />);

            const storageCard = screen.getByText('production-backups').closest('a');
            expect(storageCard).toHaveAttribute('href', '/storage/storage-uuid-1');
        });

        it('should link to create storage page from button', () => {
            render(<StorageIndex storages={mockStorages} />);

            const addButton = screen.getByText('Add Storage').closest('a');
            expect(addButton).toHaveAttribute('href', '/storage/create');
        });
    });

    describe('provider detection', () => {
        it('should detect AWS S3 provider', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('AWS S3')).toBeInTheDocument();
        });

        it('should detect Wasabi provider from endpoint', () => {
            render(<StorageIndex storages={mockStorages} />);

            expect(screen.getByText('Wasabi')).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle storage without description', () => {
            const storageWithoutDesc: S3Storage[] = [
                {
                    ...mockStorages[0],
                    description: null,
                },
            ];

            render(<StorageIndex storages={storageWithoutDesc} />);

            expect(screen.getByText('production-backups')).toBeInTheDocument();
        });

        it('should handle single storage correctly', () => {
            render(<StorageIndex storages={[mockStorages[0]]} />);

            expect(screen.getByText('production-backups')).toBeInTheDocument();
            expect(screen.queryByText('staging-storage')).not.toBeInTheDocument();
        });
    });
});
