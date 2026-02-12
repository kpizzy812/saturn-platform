import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import { router } from '@inertiajs/react';

// Mock Toast
const mockAddToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        addToast: mockAddToast,
    }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Mock useConfirm
const mockConfirm = vi.fn(() => Promise.resolve(true));
vi.mock('@/components/ui/ConfirmationModal', () => ({
    useConfirm: () => mockConfirm,
    ConfirmationProvider: ({ children }: { children: React.ReactNode }) => children,
}));

// Import after mocks
import { DeploymentsTab } from '@/pages/Services/Deployments';
import type { Service } from '@/types';

const mockService: Service = {
    id: 1,
    uuid: 'service-uuid-123',
    name: 'production-api',
    description: 'Main production API service',
    docker_compose_raw: 'version: "3.8"\nservices:\n  api:\n    image: node:18',
    environment_id: 1,
    destination_id: 1,
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-15T00:00:00.000Z',
};

const mockDeployments = [
    {
        id: 1,
        commit_sha: 'a1b2c3d4',
        commit_message: 'Fix bug in user authentication',
        status: 'finished' as const,
        created_at: '2024-01-15T10:00:00.000Z',
        duration: '3m 45s',
        author: 'John Doe',
    },
    {
        id: 2,
        commit_sha: 'e5f6g7h8',
        commit_message: 'Add new feature',
        status: 'failed' as const,
        created_at: '2024-01-14T15:30:00.000Z',
        duration: '2m 10s',
        author: 'Jane Smith',
    },
    {
        id: 3,
        commit_sha: 'i9j0k1l2',
        commit_message: 'Update dependencies',
        status: 'in_progress' as const,
        created_at: '2024-01-14T09:00:00.000Z',
        duration: '-',
        author: 'Bob Johnson',
    },
];

describe('Service Deployments Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockConfirm.mockResolvedValue(true);

        // Mock CSRF token
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'test-csrf-token';
        document.head.appendChild(meta);
    });

    it('renders Deploy Service section', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('Deploy Service')).toBeInTheDocument();
    });

    it('displays Deploy Now button', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('Deploy Now')).toBeInTheDocument();
    });

    it('shows deploy button description', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('Trigger a new deployment from the latest commit')).toBeInTheDocument();
    });

    it('renders filter buttons', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('All')).toBeInTheDocument();
        expect(screen.getByText('Finished')).toBeInTheDocument();
        expect(screen.getByText('Failed')).toBeInTheDocument();
        expect(screen.getByText('In Progress')).toBeInTheDocument();
    });

    it('displays all deployments by default', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('Fix bug in user authentication')).toBeInTheDocument();
        expect(screen.getByText('Add new feature')).toBeInTheDocument();
        expect(screen.getByText('Update dependencies')).toBeInTheDocument();
    });

    it('shows commit hashes', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('a1b2c3d')).toBeInTheDocument();
        expect(screen.getByText('e5f6g7h')).toBeInTheDocument();
        expect(screen.getByText('i9j0k1l')).toBeInTheDocument();
    });

    it('displays author names', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('John Doe')).toBeInTheDocument();
        expect(screen.getByText('Jane Smith')).toBeInTheDocument();
        expect(screen.getByText('Bob Johnson')).toBeInTheDocument();
    });

    it('shows deployment durations', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('3m 45s')).toBeInTheDocument();
        expect(screen.getByText('2m 10s')).toBeInTheDocument();
    });

    it('displays status badges', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('finished')).toBeInTheDocument();
        expect(screen.getByText('failed')).toBeInTheDocument();
        expect(screen.getByText('in progress')).toBeInTheDocument();
    });

    it('shows Rollback button for finished deployments', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        expect(screen.getByText('Rollback')).toBeInTheDocument();
    });

    it('filters deployments when Finished is clicked', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);

        const finishedButton = screen.getByText('Finished');
        fireEvent.click(finishedButton);

        expect(screen.getByText('Fix bug in user authentication')).toBeInTheDocument();
        expect(screen.queryByText('Add new feature')).not.toBeInTheDocument();
        expect(screen.queryByText('Update dependencies')).not.toBeInTheDocument();
    });

    it('filters deployments when Failed is clicked', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);

        const failedButton = screen.getByText('Failed');
        fireEvent.click(failedButton);

        expect(screen.queryByText('Fix bug in user authentication')).not.toBeInTheDocument();
        expect(screen.getByText('Add new feature')).toBeInTheDocument();
        expect(screen.queryByText('Update dependencies')).not.toBeInTheDocument();
    });

    it('filters deployments when In Progress is clicked', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);

        const inProgressButton = screen.getByText('In Progress');
        fireEvent.click(inProgressButton);

        expect(screen.queryByText('Fix bug in user authentication')).not.toBeInTheDocument();
        expect(screen.queryByText('Add new feature')).not.toBeInTheDocument();
        expect(screen.getByText('Update dependencies')).toBeInTheDocument();
    });

    it('shows all deployments when All is clicked after filtering', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);

        const failedButton = screen.getByText('Failed');
        fireEvent.click(failedButton);

        const allButton = screen.getByText('All');
        fireEvent.click(allButton);

        expect(screen.getByText('Fix bug in user authentication')).toBeInTheDocument();
        expect(screen.getByText('Add new feature')).toBeInTheDocument();
        expect(screen.getByText('Update dependencies')).toBeInTheDocument();
    });

    it('triggers deployment when Deploy Now is clicked', async () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);

        const deployButton = screen.getByText('Deploy Now');
        fireEvent.click(deployButton);

        await waitFor(() => {
            expect(router.post).toHaveBeenCalledWith(
                expect.stringContaining(`/api/v1/services/${mockService.uuid}/start`),
                expect.any(Object),
                expect.any(Object)
            );
        });
    });

    it('shows confirmation dialog when rollback is clicked', async () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);

        const rollbackButton = screen.getByText('Rollback');
        fireEvent.click(rollbackButton);

        await waitFor(() => {
            expect(mockConfirm).toHaveBeenCalledWith(
                expect.objectContaining({
                    title: 'Rollback Deployment',
                    confirmText: 'Rollback',
                })
            );
        });
    });

    it('shows empty state when no deployments exist', async () => {
        // Mock fetch to return empty array
        global.fetch = vi.fn(() =>
            Promise.resolve({
                ok: true,
                json: () => Promise.resolve([]),
            } as Response)
        );

        render(<DeploymentsTab service={mockService} deployments={[]} />);

        await waitFor(() => {
            expect(screen.getByText('No deployments found')).toBeInTheDocument();
            expect(screen.getByText('No deployments have been made yet')).toBeInTheDocument();
        });
    });

    it('shows empty state for filtered results', () => {
        const finishedOnly = mockDeployments.filter(d => d.status === 'finished');
        render(<DeploymentsTab service={mockService} deployments={finishedOnly} />);

        const failedButton = screen.getByText('Failed');
        fireEvent.click(failedButton);

        expect(screen.getByText('No deployments found')).toBeInTheDocument();
        expect(screen.getByText('No failed deployments found')).toBeInTheDocument();
    });

    it('disables deploy button while deploying', async () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);

        const deployButton = screen.getByText('Deploy Now');
        fireEvent.click(deployButton);

        expect(deployButton).toBeDisabled();
    });

    it('shows loading state when fetching deployments', () => {
        render(<DeploymentsTab service={mockService} deployments={[]} />);
        expect(screen.getByText('Loading deployments...')).toBeInTheDocument();
    });

    it('renders time ago for deployments', () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);
        // The formatTimeAgo function should render something like "Xm ago", "Xh ago", etc.
        const bodyText = document.body.textContent || '';
        expect(bodyText).toMatch(/ago|Unknown/);
    });

    it('rollback includes commit hash in request', async () => {
        render(<DeploymentsTab service={mockService} deployments={mockDeployments} />);

        const rollbackButton = screen.getByText('Rollback');
        fireEvent.click(rollbackButton);

        await waitFor(() => {
            expect(mockConfirm).toHaveBeenCalled();
        });

        // After confirmation, should call router.post with commit
        await waitFor(() => {
            expect(router.post).toHaveBeenCalledWith(
                expect.stringContaining('/start'),
                expect.objectContaining({
                    commit: expect.any(String),
                }),
                expect.any(Object)
            );
        });
    });
});
