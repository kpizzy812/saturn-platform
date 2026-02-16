import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import StorageCreate from '../../../../resources/js/pages/Storage/Create';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            post: vi.fn(),
        },
        Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
            <a href={href}>{children}</a>
        ),
    };
});

// Mock fetch for test connection
global.fetch = vi.fn();

describe('Storage/Create', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        document.cookie = 'XSRF-TOKEN=test-token';
    });

    it('renders the storage creation page with heading', () => {
        render(<StorageCreate />);

        expect(screen.getByRole('heading', { level: 1, name: /add s3 storage/i })).toBeInTheDocument();
        expect(screen.getByText(/choose a provider and configure your s3-compatible storage/i)).toBeInTheDocument();
    });

    it('renders back link to storage page', () => {
        render(<StorageCreate />);

        const backLink = screen.getByRole('link', { name: /back to storage/i });
        expect(backLink).toBeInTheDocument();
        expect(backLink).toHaveAttribute('href', '/storage');
    });

    it('shows step indicator with three steps', () => {
        render(<StorageCreate />);

        expect(screen.getByText('Provider')).toBeInTheDocument();
        expect(screen.getByText('Configure')).toBeInTheDocument();
        expect(screen.getByText('Review')).toBeInTheDocument();
    });

    it('displays all provider options on step 1', () => {
        render(<StorageCreate />);

        expect(screen.getByText('AWS S3')).toBeInTheDocument();
        expect(screen.getByText('Amazon Web Services S3 storage')).toBeInTheDocument();

        expect(screen.getByText('Wasabi')).toBeInTheDocument();
        expect(screen.getByText(/hot cloud storage at 1\/5th the price/i)).toBeInTheDocument();

        expect(screen.getByText('Backblaze B2')).toBeInTheDocument();
        expect(screen.getByText('Low-cost cloud storage')).toBeInTheDocument();

        expect(screen.getByText('MinIO')).toBeInTheDocument();
        expect(screen.getByText('Self-hosted S3-compatible storage')).toBeInTheDocument();

        expect(screen.getByText('Custom S3')).toBeInTheDocument();
        expect(screen.getByText('Any S3-compatible storage provider')).toBeInTheDocument();
    });

    it('navigates to step 2 when a provider is selected', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        expect(screen.getByLabelText(/storage name/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/access key id/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/secret access key/i)).toBeInTheDocument();
    });

    it('shows configuration form on step 2', async () => {
        const { user } = render(<StorageCreate />);

        const wasabiButton = screen.getByRole('button', { name: /wasabi/i });
        await user.click(wasabiButton);

        expect(screen.getByLabelText(/storage name/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/description \(optional\)/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/access key id/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/secret access key/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/bucket name/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/region/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/endpoint url/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/path \(optional\)/i)).toBeInTheDocument();
    });

    it('hides endpoint URL field for AWS provider', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        expect(screen.queryByLabelText(/endpoint url/i)).not.toBeInTheDocument();
    });

    it('shows endpoint URL field for non-AWS providers', async () => {
        const { user } = render(<StorageCreate />);

        const wasabiButton = screen.getByRole('button', { name: /wasabi/i });
        await user.click(wasabiButton);

        expect(screen.getByLabelText(/endpoint url/i)).toBeInTheDocument();
    });

    it('shows test connection section', async () => {
        const { user } = render(<StorageCreate />);

        const minioButton = screen.getByRole('button', { name: /minio/i });
        await user.click(minioButton);

        // "Test Connection" appears as both heading h4 and button text
        expect(screen.getAllByText('Test Connection').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText(/verify that saturn platform can connect to your storage/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /test connection/i })).toBeInTheDocument();
    });

    it('disables test connection button when form is incomplete', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        const testButton = screen.getByRole('button', { name: /test connection/i });
        expect(testButton).toBeDisabled();
    });

    it('enables test connection button when form is complete', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        await user.type(screen.getByLabelText(/storage name/i), 'Test Storage');
        await user.type(screen.getByLabelText(/access key id/i), 'AKIATEST');
        await user.type(screen.getByLabelText(/secret access key/i), 'secretkey123');
        await user.type(screen.getByLabelText(/bucket name/i), 'test-bucket');
        await user.type(screen.getByLabelText(/region/i), 'us-east-1');

        const testButton = screen.getByRole('button', { name: /test connection/i });
        expect(testButton).not.toBeDisabled();
    });

    it('navigates to step 3 when continue is clicked', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        await user.type(screen.getByLabelText(/storage name/i), 'Test Storage');
        await user.type(screen.getByLabelText(/access key id/i), 'AKIATEST');
        await user.type(screen.getByLabelText(/secret access key/i), 'secretkey123');
        await user.type(screen.getByLabelText(/bucket name/i), 'test-bucket');

        const continueButton = screen.getByRole('button', { name: /^continue$/i });
        await user.click(continueButton);

        expect(screen.getByText('Review Configuration')).toBeInTheDocument();
    });

    it('shows review information on step 3', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        await user.type(screen.getByLabelText(/storage name/i), 'Production Backups');
        await user.type(screen.getByLabelText(/description \(optional\)/i), 'Main backup storage');
        await user.type(screen.getByLabelText(/access key id/i), 'AKIATEST123456');
        await user.type(screen.getByLabelText(/secret access key/i), 'secretkey123');
        await user.type(screen.getByLabelText(/bucket name/i), 'prod-backups');

        const continueButton = screen.getByRole('button', { name: /^continue$/i });
        await user.click(continueButton);

        expect(screen.getByText('Production Backups')).toBeInTheDocument();
        expect(screen.getByText('Main backup storage')).toBeInTheDocument();
        expect(screen.getByText('prod-backups')).toBeInTheDocument();
        expect(screen.getByText(/AKIATEST\.\.\./i)).toBeInTheDocument();
    });

    it('shows what happens next section on step 3', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        await user.type(screen.getByLabelText(/storage name/i), 'Test Storage');
        await user.type(screen.getByLabelText(/access key id/i), 'AKIATEST');
        await user.type(screen.getByLabelText(/secret access key/i), 'secretkey123');
        await user.type(screen.getByLabelText(/bucket name/i), 'test-bucket');

        await user.click(screen.getByRole('button', { name: /^continue$/i }));

        expect(screen.getByText('What happens next?')).toBeInTheDocument();
        expect(screen.getByText(/storage will be configured and validated/i)).toBeInTheDocument();
        expect(screen.getByText(/available for database backup destinations/i)).toBeInTheDocument();
        expect(screen.getByText(/connection will be tested periodically/i)).toBeInTheDocument();
    });

    it('allows navigation back from step 2 to step 1', async () => {
        const { user } = render(<StorageCreate />);

        const wasabiButton = screen.getByRole('button', { name: /wasabi/i });
        await user.click(wasabiButton);

        expect(screen.getByLabelText(/storage name/i)).toBeInTheDocument();

        const backButton = screen.getAllByRole('button', { name: /back/i })[0];
        await user.click(backButton);

        expect(screen.getByText('AWS S3')).toBeInTheDocument();
        expect(screen.queryByLabelText(/storage name/i)).not.toBeInTheDocument();
    });

    it('allows navigation back from step 3 to step 2', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        await user.type(screen.getByLabelText(/storage name/i), 'Test');
        await user.type(screen.getByLabelText(/access key id/i), 'AKIATEST');
        await user.type(screen.getByLabelText(/secret access key/i), 'secret');
        await user.type(screen.getByLabelText(/bucket name/i), 'bucket');

        await user.click(screen.getByRole('button', { name: /^continue$/i }));
        expect(screen.getByText('Review Configuration')).toBeInTheDocument();

        const backButton = screen.getAllByRole('button', { name: /back/i })[0];
        await user.click(backButton);

        expect(screen.getByLabelText(/storage name/i)).toBeInTheDocument();
    });

    it('submits form data when add storage is clicked', async () => {
        const { user } = render(<StorageCreate />);

        const awsButton = screen.getByRole('button', { name: /aws s3/i });
        await user.click(awsButton);

        await user.type(screen.getByLabelText(/storage name/i), 'Test Storage');
        await user.type(screen.getByLabelText(/access key id/i), 'AKIATEST');
        await user.type(screen.getByLabelText(/secret access key/i), 'secretkey123');
        await user.type(screen.getByLabelText(/bucket name/i), 'test-bucket');

        await user.click(screen.getByRole('button', { name: /^continue$/i }));

        const addButton = screen.getByRole('button', { name: /add storage/i });
        await user.click(addButton);

        expect(router.post).toHaveBeenCalledWith('/storage', expect.objectContaining({
            name: 'Test Storage',
            key: 'AKIATEST',
            secret: 'secretkey123',
            bucket: 'test-bucket',
        }));
    });
});
