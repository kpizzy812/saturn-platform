import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

// Mock statusUtils
vi.mock('@/lib/statusUtils', () => ({
    getStatusIcon: (status: string) => <span data-testid={`status-icon-${status}`}>{status}</span>,
}));

import { BuildLogsTab } from '@/pages/Services/BuildLogs';
import type { Service } from '@/types';

const mockService: Service = {
    id: 1,
    uuid: 'svc-uuid-123',
    name: 'test-service',
    description: 'A test service',
    docker_compose_raw: 'version: "3.8"',
    environment_id: 1,
    destination_id: 1,
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-15T00:00:00.000Z',
};

const mockBuildSteps = [
    {
        id: 1,
        name: 'Pulling images',
        status: 'success' as const,
        duration: '1m 30s',
        logs: ['Pulling node:18...', 'Image pulled successfully'],
        startTime: '10:00:00',
        endTime: '10:01:30',
    },
    {
        id: 2,
        name: 'Building containers',
        status: 'running' as const,
        duration: '0m 45s',
        logs: ['Building app container...', 'Step 1/5: FROM node:18'],
    },
    {
        id: 3,
        name: 'Starting services',
        status: 'failed' as const,
        duration: '0m 5s',
        logs: ['Error: Port 3000 already in use'],
    },
];

describe('Service BuildLogs Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Build Logs heading', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        expect(screen.getByText('Build Logs')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        expect(screen.getByText('View detailed logs for each build step')).toBeInTheDocument();
    });

    it('renders Download Logs button', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        expect(screen.getByText('Download Logs')).toBeInTheDocument();
    });

    it('shows Retry Build button when a step has failed', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        expect(screen.getByText('Retry Build')).toBeInTheDocument();
    });

    it('does not show Retry Build when no failures', () => {
        const successSteps = [{ ...mockBuildSteps[0], status: 'success' as const }];
        render(<BuildLogsTab service={mockService} buildSteps={successSteps} />);
        expect(screen.queryByText('Retry Build')).not.toBeInTheDocument();
    });

    it('shows Build in progress when a step is running', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        expect(screen.getByText('Build in progress...')).toBeInTheDocument();
    });

    it('shows Build failed when steps have failed and none running', () => {
        const failedSteps = [mockBuildSteps[0], mockBuildSteps[2]]; // success + failed
        render(<BuildLogsTab service={mockService} buildSteps={failedSteps} />);
        expect(screen.getByText('Build failed')).toBeInTheDocument();
    });

    it('shows Build completed successfully when all steps succeed', () => {
        const successSteps = [{ ...mockBuildSteps[0] }];
        render(<BuildLogsTab service={mockService} buildSteps={successSteps} />);
        expect(screen.getByText('Build completed successfully')).toBeInTheDocument();
    });

    it('renders step names', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        expect(screen.getByText('Pulling images')).toBeInTheDocument();
        expect(screen.getByText('Building containers')).toBeInTheDocument();
        expect(screen.getByText('Starting services')).toBeInTheDocument();
    });

    it('renders step status badges', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        // Both getStatusIcon mock and badge span render status text
        const successElements = screen.getAllByText('success');
        expect(successElements.length).toBeGreaterThanOrEqual(1);
        const runningElements = screen.getAllByText('running');
        expect(runningElements.length).toBeGreaterThanOrEqual(1);
        const failedElements = screen.getAllByText('failed');
        expect(failedElements.length).toBeGreaterThanOrEqual(1);
    });

    it('renders step durations', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        expect(screen.getByText('1m 30s')).toBeInTheDocument();
        expect(screen.getByText('0m 45s')).toBeInTheDocument();
    });

    it('expands step logs on click', () => {
        render(<BuildLogsTab service={mockService} buildSteps={mockBuildSteps} />);
        fireEvent.click(screen.getByText('Pulling images'));
        expect(screen.getByText('Build Output')).toBeInTheDocument();
        expect(screen.getByText('Pulling node:18...')).toBeInTheDocument();
    });

    it('renders empty state with completed status when no build steps', () => {
        render(<BuildLogsTab service={mockService} />);
        expect(screen.getByText('Build completed successfully')).toBeInTheDocument();
    });
});
