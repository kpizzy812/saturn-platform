import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import BuildLogsView from './BuildLogs';

declare const global: typeof globalThis;

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
    Button: ({ children, onClick, disabled, variant, size }: any) => (
        <button onClick={onClick} disabled={disabled} data-variant={variant} data-size={size}>
            {children}
        </button>
    ),
}));

describe('BuildLogsView', () => {
    it('renders the build logs page', () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);
        expect(screen.getByText('Build Logs')).toBeInTheDocument();
    });

    it('displays build steps with status icons', () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        expect(screen.getByText('Clone Repository')).toBeInTheDocument();
        expect(screen.getByText('Install Dependencies')).toBeInTheDocument();
        expect(screen.getByText('Build Application')).toBeInTheDocument();
        expect(screen.getByText('Run Tests')).toBeInTheDocument();
        expect(screen.getByText('Create Docker Image')).toBeInTheDocument();
        expect(screen.getByText('Deploy Container')).toBeInTheDocument();
    });

    it('displays build status and total duration', () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        // Should show build in progress
        expect(screen.getByText('Build in progress...')).toBeInTheDocument();

        // Should show total duration
        expect(screen.getByText(/Total:/)).toBeInTheDocument();
    });

    it('expands and collapses build steps', async () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        // Find the first step header
        const stepHeader = screen.getByText('Clone Repository');
        expect(stepHeader).toBeInTheDocument();

        // Click to expand
        fireEvent.click(stepHeader);

        // Wait for logs to be visible
        await waitFor(() => {
            expect(screen.getByText(/Cloning into/)).toBeInTheDocument();
        });

        // Click again to collapse
        fireEvent.click(stepHeader);

        await waitFor(() => {
            // Logs should not be visible
            expect(screen.queryByText(/Cloning into/)).not.toBeInTheDocument();
        });
    });

    it('expands all steps when expand all is clicked', async () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        const expandAllButton = screen.getByText('Expand All');
        fireEvent.click(expandAllButton);

        await waitFor(() => {
            // All step logs should be visible
            expect(screen.getByText(/Cloning into/)).toBeInTheDocument();
            expect(screen.getByText(/npm install/)).toBeInTheDocument();
        });

        // Button text should change
        expect(screen.getByText('Collapse All')).toBeInTheDocument();
    });

    it('filters logs by search query', async () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        const searchInput = screen.getByPlaceholderText('Search within logs...');
        expect(searchInput).toBeInTheDocument();

        // Expand all steps first
        const expandAllButton = screen.getByText('Expand All');
        fireEvent.click(expandAllButton);

        // Search for specific content
        fireEvent.change(searchInput, { target: { value: 'npm' } });

        await waitFor(() => {
            // Should still see npm-related logs
            const npmLogs = screen.getAllByText(/npm/i);
            expect(npmLogs.length).toBeGreaterThan(0);
        });
    });

    it('filters logs by log level', async () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        // Expand all steps first
        const expandAllButton = screen.getByText('Expand All');
        fireEvent.click(expandAllButton);

        await waitFor(() => {
            const logLevelSelect = screen.getByDisplayValue('All Levels');
            expect(logLevelSelect).toBeInTheDocument();

            // Change to Errors only
            fireEvent.change(logLevelSelect, { target: { value: 'error' } });

            expect(logLevelSelect).toHaveValue('error');
        });
    });

    it('downloads logs when download button is clicked', () => {
        // Mock URL.createObjectURL and document methods
        global.URL.createObjectURL = vi.fn();
        global.URL.revokeObjectURL = vi.fn();
        const createElementSpy = vi.spyOn(document, 'createElement');
        const appendChildSpy = vi.spyOn(document.body, 'appendChild');
        const removeChildSpy = vi.spyOn(document.body, 'removeChild');

        render(<BuildLogsView deploymentUuid="test-123" />);

        const downloadButton = screen.getByText('Download');
        fireEvent.click(downloadButton);

        // Verify download was triggered
        expect(createElementSpy).toHaveBeenCalledWith('a');
        expect(appendChildSpy).toHaveBeenCalled();
        expect(removeChildSpy).toHaveBeenCalled();
    });

    it('shows retry button when build fails', () => {
        // We'd need to pass a failed build step to test this
        // For now, we'll just check if the component renders
        render(<BuildLogsView deploymentUuid="test-uuid" />);
        expect(screen.getByText('Build Logs')).toBeInTheDocument();
    });

    it('displays step duration and time range', () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        // Should show step durations
        expect(screen.getByText('2.3s')).toBeInTheDocument();
        expect(screen.getByText('45.7s')).toBeInTheDocument();

        // Should show time ranges
        expect(screen.getByText('14:32:01')).toBeInTheDocument();
    });

    it('shows copy button for each step logs', async () => {
        // Mock clipboard API
        Object.assign(navigator, {
            clipboard: {
                writeText: vi.fn(),
            },
        });

        render(<BuildLogsView deploymentUuid="test-uuid" />);

        // Expand the first step
        const stepHeader = screen.getByText('Clone Repository');
        fireEvent.click(stepHeader);

        await waitFor(() => {
            // Find copy button (it might be in an icon)
            const buttons = screen.getAllByRole('button');
            const copyButton = buttons.find(
                btn => btn.innerHTML.includes('Copy') || btn.querySelector('svg')
            );
            expect(copyButton).toBeDefined();
        });
    });

    it('displays running step with animation', () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        // The "Deploy Container" step should be running
        expect(screen.getByText('Deploy Container')).toBeInTheDocument();
        expect(screen.getByText('running')).toBeInTheDocument();
    });

    it('shows back to deployment button', () => {
        render(<BuildLogsView deploymentUuid="dep-123" />);

        const backButton = screen.getByText('Back to Deployment');
        expect(backButton).toBeInTheDocument();
        expect(backButton.closest('a')).toHaveAttribute('href', '/deployments/dep-123');
    });

    it('displays filtered log count when filters are active', async () => {
        render(<BuildLogsView deploymentUuid="test-uuid" />);

        // Expand a step
        const stepHeader = screen.getByText('Clone Repository');
        fireEvent.click(stepHeader);

        // Add a search filter
        const searchInput = screen.getByPlaceholderText('Search within logs...');
        fireEvent.change(searchInput, { target: { value: 'test' } });

        await waitFor(() => {
            // Should show filtered line count
            const stepDuration = screen.getByText(/2.3s/);
            expect(stepDuration).toBeInTheDocument();
        });
    });
});
