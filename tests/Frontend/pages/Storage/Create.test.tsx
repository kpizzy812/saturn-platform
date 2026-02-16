import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import StorageCreate from '@/pages/Storage/Create';

describe('Storage Create Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<StorageCreate />);

            expect(screen.getByText('Add S3 storage')).toBeInTheDocument();
            expect(screen.getByText('Choose a provider and configure your S3-compatible storage')).toBeInTheDocument();
        });

        it('should render back link', () => {
            render(<StorageCreate />);

            const backLink = screen.getByText('Back to Storage');
            expect(backLink).toBeInTheDocument();
        });

        it('should render progress indicator', () => {
            render(<StorageCreate />);

            expect(screen.getByText('Provider')).toBeInTheDocument();
            expect(screen.getByText('Configure')).toBeInTheDocument();
            expect(screen.getByText('Review')).toBeInTheDocument();
        });
    });

    describe('step 1 - provider selection', () => {
        it('should render all provider options', () => {
            render(<StorageCreate />);

            expect(screen.getByText('AWS S3')).toBeInTheDocument();
            expect(screen.getByText('Wasabi')).toBeInTheDocument();
            expect(screen.getByText('Backblaze B2')).toBeInTheDocument();
            expect(screen.getByText('MinIO')).toBeInTheDocument();
            expect(screen.getByText('Custom S3')).toBeInTheDocument();
        });

        it('should render provider descriptions', () => {
            render(<StorageCreate />);

            expect(screen.getByText('Amazon Web Services S3 storage')).toBeInTheDocument();
            expect(screen.getByText('Hot cloud storage at 1/5th the price')).toBeInTheDocument();
            expect(screen.getByText('Low-cost cloud storage')).toBeInTheDocument();
            expect(screen.getByText('Self-hosted S3-compatible storage')).toBeInTheDocument();
            expect(screen.getByText('Any S3-compatible storage provider')).toBeInTheDocument();
        });

        it('should show provider cards as clickable', () => {
            render(<StorageCreate />);

            const providerButtons = screen.getAllByRole('button');
            expect(providerButtons.length).toBeGreaterThan(4);
        });
    });

    describe('step 2 - configuration', () => {
        it('should move to step 2 when provider is selected', async () => {
            const { user } = render(<StorageCreate />);

            const awsButton = screen.getByText('AWS S3').closest('button');
            if (awsButton) {
                await user.click(awsButton);

                expect(screen.getByText('Storage Name')).toBeInTheDocument();
                expect(screen.getByText('Access Key ID')).toBeInTheDocument();
                expect(screen.getByText('Secret Access Key')).toBeInTheDocument();
            }
        });

        it('should render all configuration fields', async () => {
            const { user } = render(<StorageCreate />);

            const awsButton = screen.getByText('AWS S3').closest('button');
            if (awsButton) {
                await user.click(awsButton);

                expect(screen.getByText('Storage Name')).toBeInTheDocument();
                expect(screen.getByText('Description (Optional)')).toBeInTheDocument();
                expect(screen.getByText('Access Key ID')).toBeInTheDocument();
                expect(screen.getByText('Secret Access Key')).toBeInTheDocument();
                expect(screen.getByText('Bucket Name')).toBeInTheDocument();
                expect(screen.getByText('Region')).toBeInTheDocument();
                expect(screen.getByText('Path (Optional)')).toBeInTheDocument();
            }
        });

        it('should render test connection section', async () => {
            const { user } = render(<StorageCreate />);

            const awsButton = screen.getByText('AWS S3').closest('button');
            if (awsButton) {
                await user.click(awsButton);

                expect(screen.getByText('Test Connection')).toBeInTheDocument();
                expect(screen.getByText('Verify that Saturn Platform can connect to your storage')).toBeInTheDocument();
            }
        });

        it('should render back and continue buttons', async () => {
            const { user } = render(<StorageCreate />);

            const awsButton = screen.getByText('AWS S3').closest('button');
            if (awsButton) {
                await user.click(awsButton);

                expect(screen.getByRole('button', { name: /back/i })).toBeInTheDocument();
                expect(screen.getByRole('button', { name: /continue/i })).toBeInTheDocument();
            }
        });
    });

    describe('step 3 - review', () => {
        it('should show review configuration title', async () => {
            const { user } = render(<StorageCreate />);

            const awsButton = screen.getByText('AWS S3').closest('button');
            if (awsButton) {
                await user.click(awsButton);

                // Fill required fields
                const nameInput = screen.getByPlaceholderText('production-backups');
                const accessKeyInput = screen.getByPlaceholderText('AKIAIOSFODNN7EXAMPLE');
                const secretKeyInput = screen.getByPlaceholderText('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
                const bucketInput = screen.getByPlaceholderText('my-backups');
                const regionInput = screen.getByPlaceholderText('us-east-1');

                await user.type(nameInput, 'test-storage');
                await user.type(accessKeyInput, 'test-access-key');
                await user.type(secretKeyInput, 'test-secret-key');
                await user.type(bucketInput, 'test-bucket');
                await user.type(regionInput, 'us-east-1');

                const continueButton = screen.getByRole('button', { name: /continue/i });
                await user.click(continueButton);

                expect(screen.getByText('Review Configuration')).toBeInTheDocument();
            }
        });

        it('should render what happens next section', async () => {
            const { user } = render(<StorageCreate />);

            const awsButton = screen.getByText('AWS S3').closest('button');
            if (awsButton) {
                await user.click(awsButton);

                const nameInput = screen.getByPlaceholderText('production-backups');
                const accessKeyInput = screen.getByPlaceholderText('AKIAIOSFODNN7EXAMPLE');
                const secretKeyInput = screen.getByPlaceholderText('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
                const bucketInput = screen.getByPlaceholderText('my-backups');
                const regionInput = screen.getByPlaceholderText('us-east-1');

                await user.type(nameInput, 'test-storage');
                await user.type(accessKeyInput, 'test-access-key');
                await user.type(secretKeyInput, 'test-secret-key');
                await user.type(bucketInput, 'test-bucket');
                await user.type(regionInput, 'us-east-1');

                const continueButton = screen.getByRole('button', { name: /continue/i });
                await user.click(continueButton);

                expect(screen.getByText('What happens next?')).toBeInTheDocument();
            }
        });

        it('should render Add Storage button on review', async () => {
            const { user } = render(<StorageCreate />);

            const awsButton = screen.getByText('AWS S3').closest('button');
            if (awsButton) {
                await user.click(awsButton);

                const nameInput = screen.getByPlaceholderText('production-backups');
                const accessKeyInput = screen.getByPlaceholderText('AKIAIOSFODNN7EXAMPLE');
                const secretKeyInput = screen.getByPlaceholderText('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
                const bucketInput = screen.getByPlaceholderText('my-backups');
                const regionInput = screen.getByPlaceholderText('us-east-1');

                await user.type(nameInput, 'test-storage');
                await user.type(accessKeyInput, 'test-access-key');
                await user.type(secretKeyInput, 'test-secret-key');
                await user.type(bucketInput, 'test-bucket');
                await user.type(regionInput, 'us-east-1');

                const continueButton = screen.getByRole('button', { name: /continue/i });
                await user.click(continueButton);

                expect(screen.getByText('Add Storage')).toBeInTheDocument();
            }
        });
    });

    describe('edge cases', () => {
        it('should disable continue button when required fields are empty', async () => {
            const { user } = render(<StorageCreate />);

            const awsButton = screen.getByText('AWS S3').closest('button');
            if (awsButton) {
                await user.click(awsButton);

                const continueButton = screen.getByRole('button', { name: /continue/i });
                expect(continueButton).toBeDisabled();
            }
        });
    });
});
