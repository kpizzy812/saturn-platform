import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import StorageIndex from '../../../../resources/js/pages/Storage/Index';
import type { S3Storage } from '../../../../resources/js/types';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            get: vi.fn(),
            post: vi.fn(),
            delete: vi.fn(),
        },
        Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
            <a href={href}>{children}</a>
        ),
    };
});

describe('Storage/Index', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const mockStorages: S3Storage[] = [
        {
            id: 1,
            uuid: 'storage-1',
            name: 'Production Backups',
            description: 'Main backup storage',
            bucket: 'prod-backups',
            region: 'us-east-1',
            endpoint: 'https://s3.wasabisys.com',
            is_usable: true,
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-15T00:00:00Z',
        },
        {
            id: 2,
            uuid: 'storage-2',
            name: 'Development Storage',
            description: 'Dev environment',
            bucket: 'dev-storage',
            region: 'us-west-1',
            endpoint: '',
            is_usable: false,
            created_at: '2024-01-10T00:00:00Z',
            updated_at: '2024-01-20T00:00:00Z',
        },
    ];

    it('renders the storage page with heading', () => {
        render(<StorageIndex storages={[]} />);

        expect(screen.getByRole('heading', { level: 1, name: /storage/i })).toBeInTheDocument();
        expect(screen.getByText(/manage s3-compatible backup destinations/i)).toBeInTheDocument();
    });

    it('renders add storage button', () => {
        render(<StorageIndex storages={mockStorages} />);

        // With storages present, only the header "Add Storage" link renders (no empty state)
        const addButton = screen.getByRole('link', { name: /add storage/i });
        expect(addButton).toBeInTheDocument();
        expect(addButton).toHaveAttribute('href', '/storage/create');
    });

    it('renders search input', () => {
        render(<StorageIndex storages={[]} />);

        expect(screen.getByPlaceholderText(/search storage/i)).toBeInTheDocument();
    });

    it('shows empty state when no storages exist', () => {
        render(<StorageIndex storages={[]} />);

        expect(screen.getByText(/no storage configured/i)).toBeInTheDocument();
        expect(screen.getByText(/add an s3-compatible storage provider to enable database backups/i)).toBeInTheDocument();
    });

    it('displays storage cards when storages exist', () => {
        render(<StorageIndex storages={mockStorages} />);

        expect(screen.getByText('Production Backups')).toBeInTheDocument();
        expect(screen.getByText('Development Storage')).toBeInTheDocument();
    });

    it('shows storage provider information', () => {
        render(<StorageIndex storages={mockStorages} />);

        expect(screen.getByText('Wasabi')).toBeInTheDocument();
        expect(screen.getByText('AWS S3')).toBeInTheDocument();
    });

    it('displays storage connection status', () => {
        render(<StorageIndex storages={mockStorages} />);

        expect(screen.getByText('Connected')).toBeInTheDocument();
        expect(screen.getByText('Connection Failed')).toBeInTheDocument();
    });

    it('shows bucket and region information', () => {
        render(<StorageIndex storages={mockStorages} />);

        expect(screen.getByText('prod-backups')).toBeInTheDocument();
        expect(screen.getByText('us-east-1')).toBeInTheDocument();
        expect(screen.getByText('dev-storage')).toBeInTheDocument();
        expect(screen.getByText('us-west-1')).toBeInTheDocument();
    });

    it('displays storage descriptions when provided', () => {
        render(<StorageIndex storages={mockStorages} />);

        expect(screen.getByText('Main backup storage')).toBeInTheDocument();
        expect(screen.getByText('Dev environment')).toBeInTheDocument();
    });

    it('filters storages by search query', async () => {
        const { user } = render(<StorageIndex storages={mockStorages} />);

        const searchInput = screen.getByPlaceholderText(/search storage/i);
        await user.type(searchInput, 'Production');

        expect(screen.getByText('Production Backups')).toBeInTheDocument();
        expect(screen.queryByText('Development Storage')).not.toBeInTheDocument();
    });

    it('filters storages by bucket name', async () => {
        const { user } = render(<StorageIndex storages={mockStorages} />);

        const searchInput = screen.getByPlaceholderText(/search storage/i);
        await user.type(searchInput, 'dev-storage');

        expect(screen.queryByText('Production Backups')).not.toBeInTheDocument();
        expect(screen.getByText('Development Storage')).toBeInTheDocument();
    });

    it('shows no results state when search has no matches', async () => {
        const { user } = render(<StorageIndex storages={mockStorages} />);

        const searchInput = screen.getByPlaceholderText(/search storage/i);
        await user.type(searchInput, 'nonexistent');

        expect(screen.getByText(/no storage found/i)).toBeInTheDocument();
        expect(screen.getByText(/try adjusting your search query/i)).toBeInTheDocument();
    });

    it('creates clickable storage cards with correct links', () => {
        render(<StorageIndex storages={mockStorages} />);

        const links = screen.getAllByRole('link');
        const storageLinks = links.filter(link =>
            link.getAttribute('href')?.includes('/storage/storage-')
        );

        expect(storageLinks).toHaveLength(2);
        expect(storageLinks[0]).toHaveAttribute('href', '/storage/storage-1');
        expect(storageLinks[1]).toHaveAttribute('href', '/storage/storage-2');
    });

    it('displays last updated dates', () => {
        render(<StorageIndex storages={mockStorages} />);

        expect(screen.getAllByText(/updated/i)).toHaveLength(2);
    });
});
