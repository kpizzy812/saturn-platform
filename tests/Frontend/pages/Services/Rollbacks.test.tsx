import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

// Mock Toast
const mockAddToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({ addToast: mockAddToast }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Mock statusUtils
vi.mock('@/lib/statusUtils', () => ({
    getStatusLabel: (status: string) => status.charAt(0).toUpperCase() + status.slice(1),
}));

// Mock Modal
vi.mock('@/components/ui', async (importOriginal) => {
    const mod = await importOriginal<typeof import('@/components/ui')>();
    return {
        ...mod,
        useToast: () => ({ addToast: mockAddToast }),
        Modal: ({ isOpen, children, title, description }: any) =>
            isOpen ? (
                <div data-testid="modal">
                    <h2>{title}</h2>
                    <p>{description}</p>
                    {children}
                </div>
            ) : null,
        ModalFooter: ({ children }: any) => <div>{children}</div>,
    };
});

import { RollbacksTab } from '@/pages/Services/Rollbacks';
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

const mockContainers = [
    {
        id: 'abc123',
        name: 'service-app-1',
        image: 'node:18-alpine',
        status: 'Up 2 hours',
        state: 'running',
        created: '2024-01-15T10:00:00Z',
    },
    {
        id: 'def456',
        name: 'service-db-1',
        image: 'postgres:15',
        status: 'Exited (1) 10 minutes ago',
        state: 'exited',
        created: '2024-01-15T09:00:00Z',
    },
];

describe('Service Rollbacks Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders About Service Rollbacks info card', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText('About Service Rollbacks')).toBeInTheDocument();
    });

    it('renders rollback explanation text', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText(/Docker Compose services don't have traditional rollback functionality/)).toBeInTheDocument();
    });

    it('renders Redeploy Service heading', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText('Redeploy Service')).toBeInTheDocument();
    });

    it('renders Redeploy button', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText('Redeploy')).toBeInTheDocument();
    });

    it('renders Current Containers heading', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText('Current Containers')).toBeInTheDocument();
    });

    it('shows container count badge', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText('2 containers')).toBeInTheDocument();
    });

    it('displays container names', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText('service-app-1')).toBeInTheDocument();
        expect(screen.getByText('service-db-1')).toBeInTheDocument();
    });

    it('shows container images', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText('node:18-alpine')).toBeInTheDocument();
        expect(screen.getByText('postgres:15')).toBeInTheDocument();
    });

    it('shows container state badges', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        expect(screen.getByText('running')).toBeInTheDocument();
        expect(screen.getByText('exited')).toBeInTheDocument();
    });

    it('shows empty container state', () => {
        render(<RollbacksTab service={mockService} containers={[]} />);
        expect(screen.getByText(/No containers found/)).toBeInTheDocument();
    });

    it('opens redeploy modal when Redeploy clicked', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        fireEvent.click(screen.getByText('Redeploy'));
        expect(screen.getByText('Pull Latest Images')).toBeInTheDocument();
        expect(screen.getByText('Restart Only')).toBeInTheDocument();
    });

    it('shows warning in redeploy modal', () => {
        render(<RollbacksTab service={mockService} containers={mockContainers} />);
        fireEvent.click(screen.getByText('Redeploy'));
        expect(screen.getByText('This action will:')).toBeInTheDocument();
        expect(screen.getByText('Stop all service containers')).toBeInTheDocument();
    });
});
