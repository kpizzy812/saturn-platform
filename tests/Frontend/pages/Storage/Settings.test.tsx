import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import StorageSettings from '@/pages/Storage/Settings';
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
];

const mockSettings = {
    default_storage_uuid: 'storage-uuid-1',
    default_retention_days: 30,
    encryption_enabled: true,
    compression_enabled: true,
    auto_cleanup_enabled: false,
    auto_cleanup_days: 90,
};

describe('Storage Settings Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Storage Settings')).toBeInTheDocument();
            expect(screen.getByText('Configure global storage settings, retention policies, and encryption options')).toBeInTheDocument();
        });

        it('should render breadcrumbs', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Storage')).toBeInTheDocument();
            expect(screen.getByText('Settings')).toBeInTheDocument();
        });
    });

    describe('default storage section', () => {
        it('should render default storage section', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Default Storage')).toBeInTheDocument();
            expect(screen.getByText('Default Backup Storage')).toBeInTheDocument();
        });

        it('should render storage options in select', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('production-backups (my-production-backups)')).toBeInTheDocument();
        });

        it('should show warning when no storage providers', () => {
            render(<StorageSettings storages={[]} settings={mockSettings} />);

            expect(screen.getByText('No storage providers configured. Add a storage provider to enable backups.')).toBeInTheDocument();
        });
    });

    describe('retention policies section', () => {
        it('should render retention policies section', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Retention Policies')).toBeInTheDocument();
            expect(screen.getByText('Default Retention Period')).toBeInTheDocument();
            expect(screen.getByText('Auto-Cleanup After')).toBeInTheDocument();
        });

        it('should render retention policy rules', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Retention Policy Rules')).toBeInTheDocument();
            expect(screen.getByText('• Backups older than the retention period will be marked for deletion')).toBeInTheDocument();
        });

        it('should render enable automatic cleanup checkbox', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Enable Automatic Cleanup')).toBeInTheDocument();
        });
    });

    describe('encryption section', () => {
        it('should render encryption section', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Encryption & Compression')).toBeInTheDocument();
            expect(screen.getByText('Enable Encryption')).toBeInTheDocument();
            expect(screen.getByText('Enable Compression')).toBeInTheDocument();
        });

        it('should render security information', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Security Information')).toBeInTheDocument();
            expect(screen.getByText('• Encryption uses AES-256-CBC with a unique key per backup')).toBeInTheDocument();
        });
    });

    describe('save button', () => {
        it('should render save button', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            expect(screen.getByText('Save Settings')).toBeInTheDocument();
        });
    });

    describe('form inputs', () => {
        it('should render all form inputs', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            const inputs = screen.getAllByRole('textbox');
            expect(inputs.length).toBeGreaterThan(0);
        });

        it('should populate settings from props', () => {
            render(<StorageSettings storages={mockStorages} settings={mockSettings} />);

            const retentionInput = screen.getByDisplayValue('30');
            expect(retentionInput).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle missing settings', () => {
            render(<StorageSettings storages={mockStorages} />);

            expect(screen.getByText('Storage Settings')).toBeInTheDocument();
        });

        it('should handle empty storages array', () => {
            render(<StorageSettings storages={[]} settings={mockSettings} />);

            expect(screen.getByText('No storage providers configured. Add a storage provider to enable backups.')).toBeInTheDocument();
        });
    });
});
