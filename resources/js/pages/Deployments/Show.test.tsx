import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import DeploymentShow from './Show';

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }: any) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children, title }: any) => (
        <div data-testid="app-layout" data-title={title}>
            {children}
        </div>
    ),
}));

// Mock UI components
vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardHeader: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardTitle: ({ children, className }: any) => <h3 className={className}>{children}</h3>,
    Badge: ({ children, variant }: any) => <span data-variant={variant}>{children}</span>,
    Button: ({ children, onClick, disabled, variant, size }: any) => (
        <button onClick={onClick} disabled={disabled} data-variant={variant} data-size={size}>
            {children}
        </button>
    ),
    Tabs: ({ children }: any) => <div>{children}</div>,
}));

// Mock utils
vi.mock('@/lib/utils', () => ({
    formatRelativeTime: (_date: string) => '30 minutes ago',
}));

describe('DeploymentShow', () => {
    it('renders the deployment detail page', () => {
        render(<DeploymentShow />);
        expect(screen.getByText('feat: Add user authentication and JWT tokens')).toBeInTheDocument();
    });

    it('displays deployment overview information', () => {
        render(<DeploymentShow />);

        // Check for commit hash
        expect(screen.getByText('a1b2c3d4e5f6g7h8')).toBeInTheDocument();

        // Check for author
        expect(screen.getByText('John Doe')).toBeInTheDocument();

        // Check for trigger
        expect(screen.getByText('push')).toBeInTheDocument();

        // Check for duration
        expect(screen.getByText('3m 45s')).toBeInTheDocument();
    });

    it('displays status badge with correct variant', () => {
        render(<DeploymentShow />);

        const badge = screen.getByText('finished');
        expect(badge).toBeInTheDocument();
        expect(badge).toHaveAttribute('data-variant', 'success');
    });

    it('switches between tabs', async () => {
        render(<DeploymentShow />);

        // Check that Build Logs tab is visible
        expect(screen.getByText('Build Logs')).toBeInTheDocument();

        // Click on Deploy Logs tab
        const deployLogsTab = screen.getByText('Deploy Logs');
        fireEvent.click(deployLogsTab);

        // Wait for deploy logs to be displayed
        await waitFor(() => {
            // The deploy logs should contain deployment-specific content
            expect(screen.getByText(/Starting deployment.../)).toBeInTheDocument();
        });
    });

    it('displays build logs correctly', () => {
        render(<DeploymentShow />);

        // Build logs should be displayed by default
        expect(screen.getByText(/Cloning into/)).toBeInTheDocument();
        expect(screen.getByText(/npm install/)).toBeInTheDocument();
    });

    it('shows download logs button', () => {
        render(<DeploymentShow />);

        const downloadButtons = screen.getAllByText('Download');
        expect(downloadButtons.length).toBeGreaterThan(0);
    });

    it('filters logs by search query', async () => {
        render(<DeploymentShow />);

        // Find search input
        const searchInput = screen.getByPlaceholderText('Search logs...');
        expect(searchInput).toBeInTheDocument();

        // Search for specific log content
        fireEvent.change(searchInput, { target: { value: 'npm' } });

        // The filtered logs should still be visible
        await waitFor(() => {
            expect(screen.getByText(/npm install/)).toBeInTheDocument();
        });
    });

    it('filters logs by log level', async () => {
        render(<DeploymentShow />);

        // Find log level selector
        const logLevelSelect = screen.getByDisplayValue('All Levels');
        expect(logLevelSelect).toBeInTheDocument();

        // Change to Errors only
        fireEvent.change(logLevelSelect, { target: { value: 'error' } });

        await waitFor(() => {
            // The log level should be updated
            expect(logLevelSelect).toHaveValue('error');
        });
    });

    it('displays environment variables', async () => {
        render(<DeploymentShow />);

        // Click on Environment tab
        const environmentTab = screen.getByText('Environment');
        fireEvent.click(environmentTab);

        await waitFor(() => {
            expect(screen.getByText('NODE_ENV')).toBeInTheDocument();
            expect(screen.getByText('production')).toBeInTheDocument();
            expect(screen.getByText('API_URL')).toBeInTheDocument();
        });
    });

    it('displays artifacts', async () => {
        render(<DeploymentShow />);

        // Click on Artifacts tab
        const artifactsTab = screen.getByText('Artifacts');
        fireEvent.click(artifactsTab);

        await waitFor(() => {
            expect(screen.getByText('build-output.tar.gz')).toBeInTheDocument();
            expect(screen.getByText('12.4 MB')).toBeInTheDocument();
            expect(screen.getByText('docker-image.tar')).toBeInTheDocument();
        });
    });

    it('shows rollback button for finished deployments', () => {
        render(<DeploymentShow />);

        const rollbackButton = screen.getByText('Rollback');
        expect(rollbackButton).toBeInTheDocument();
    });

    it('shows redeploy button for finished deployments', () => {
        render(<DeploymentShow />);

        const redeployButton = screen.getByText('Redeploy');
        expect(redeployButton).toBeInTheDocument();
    });

    it('shows view diff button when previous deployment exists', () => {
        render(<DeploymentShow />);

        const viewDiffButton = screen.getByText('View Diff');
        expect(viewDiffButton).toBeInTheDocument();
    });

    it('displays back to deployments button', () => {
        render(<DeploymentShow />);

        const backButton = screen.getByText('Back to Deployments');
        expect(backButton).toBeInTheDocument();
    });

    it('shows auto-scroll checkbox for logs', () => {
        render(<DeploymentShow />);

        const autoScrollCheckbox = screen.getByLabelText('Auto-scroll');
        expect(autoScrollCheckbox).toBeInTheDocument();
        expect(autoScrollCheckbox).toBeChecked();
    });
});
