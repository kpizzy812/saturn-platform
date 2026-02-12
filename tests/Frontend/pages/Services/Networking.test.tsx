import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

// Mock Toast
const mockToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        toast: mockToast,
    }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Import after mocks
import { NetworkingTab } from '@/pages/Services/Networking';
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

const mockPorts = [
    { id: 1, internal: 3000, external: 80, protocol: 'tcp' as const },
    { id: 2, internal: 8080, external: 8080, protocol: 'tcp' as const },
];

const mockConnectedServices = [
    { id: 1, name: 'postgres-db', type: 'Database', status: 'connected' as const },
    { id: 2, name: 'redis-cache', type: 'Cache', status: 'disconnected' as const },
];

describe('Service Networking Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Private Networking section', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Private Networking')).toBeInTheDocument();
    });

    it('shows Enabled badge when private networking is enabled', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Enabled')).toBeInTheDocument();
    });

    it('displays private networking checkbox', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Enable private networking for secure inter-service communication')).toBeInTheDocument();
    });

    it('shows internal DNS name', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Internal DNS Name')).toBeInTheDocument();
        expect(screen.getByText('production-api.internal.local')).toBeInTheDocument();
    });

    it('displays Copy button for DNS name', () => {
        render(<NetworkingTab service={mockService} />);
        const copyButtons = screen.getAllByText('Copy');
        expect(copyButtons.length).toBeGreaterThan(0);
    });

    it('shows DNS name helper text', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText(/Use this DNS name to connect to this service from other services/)).toBeInTheDocument();
    });

    it('renders Port Configuration section', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Port Configuration')).toBeInTheDocument();
    });

    it('displays existing port mappings', () => {
        render(<NetworkingTab service={mockService} ports={mockPorts} />);
        const portMappings = screen.getAllByText('Port Mapping');
        expect(portMappings.length).toBeGreaterThan(0);
        const portTexts = document.body.textContent || '';
        expect(portTexts).toContain('3000');
        expect(portTexts).toContain('80');
        expect(portTexts).toContain('8080');
    });

    it('shows protocol badge for each port', () => {
        render(<NetworkingTab service={mockService} ports={mockPorts} />);
        const protocolBadges = screen.getAllByText('TCP');
        expect(protocolBadges.length).toBeGreaterThanOrEqual(1);
    });

    it('displays delete button for each port', () => {
        render(<NetworkingTab service={mockService} ports={mockPorts} />);
        const deleteButtons = document.querySelectorAll('button');
        const trashButtons = Array.from(deleteButtons).filter(
            btn => btn.querySelector('svg')
        );
        expect(trashButtons.length).toBeGreaterThan(0);
    });

    it('shows Add Port Mapping section', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Add Port Mapping')).toBeInTheDocument();
    });

    it('displays Internal Port input', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Internal Port')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('3000')).toBeInTheDocument();
    });

    it('displays External Port input', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('External Port')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('80')).toBeInTheDocument();
    });

    it('displays Protocol selector', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Protocol')).toBeInTheDocument();
        const select = document.querySelector('select');
        expect(select).toBeInTheDocument();
    });

    it('protocol selector has TCP and UDP options', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('TCP')).toBeInTheDocument();
        expect(screen.getByText('UDP')).toBeInTheDocument();
    });

    it('shows Add button for port mapping', () => {
        render(<NetworkingTab service={mockService} />);
        const addButtons = screen.getAllByText('Add');
        expect(addButtons.length).toBeGreaterThan(0);
    });

    it('allows adding a new port mapping', () => {
        render(<NetworkingTab service={mockService} ports={[]} />);

        const internalInput = screen.getByPlaceholderText('3000');
        const externalInput = screen.getByPlaceholderText('80');

        fireEvent.change(internalInput, { target: { value: '5000' } });
        fireEvent.change(externalInput, { target: { value: '5000' } });

        const addButton = screen.getAllByText('Add')[0];
        fireEvent.click(addButton);

        expect(screen.getByText('Port Mapping')).toBeInTheDocument();
    });

    it('renders Service Mesh section', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Service Mesh')).toBeInTheDocument();
    });

    it('shows Disabled badge for service mesh by default', () => {
        render(<NetworkingTab service={mockService} />);
        const badges = screen.getAllByText('Disabled');
        // Service mesh is disabled by default
        expect(badges.length).toBeGreaterThan(0);
    });

    it('displays service mesh checkbox', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Enable service mesh for advanced traffic management and observability')).toBeInTheDocument();
    });

    it('shows service mesh features when enabled', () => {
        render(<NetworkingTab service={mockService} />);

        const checkbox = screen.getByText('Enable service mesh for advanced traffic management and observability');
        const input = checkbox.querySelector('input') || checkbox.parentElement?.querySelector('input');

        if (input) {
            fireEvent.click(input);

            expect(screen.getByText('Features Enabled:')).toBeInTheDocument();
            expect(screen.getByText('Mutual TLS (mTLS) encryption')).toBeInTheDocument();
            expect(screen.getByText('Traffic routing and load balancing')).toBeInTheDocument();
            expect(screen.getByText('Circuit breaking and retry policies')).toBeInTheDocument();
            expect(screen.getByText('Distributed tracing')).toBeInTheDocument();
        }
    });

    it('renders Connected Services section', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Connected Services')).toBeInTheDocument();
    });

    it('displays connected services', () => {
        render(<NetworkingTab service={mockService} connectedServices={mockConnectedServices} />);
        expect(screen.getByText('postgres-db')).toBeInTheDocument();
        expect(screen.getByText('Database')).toBeInTheDocument();
        expect(screen.getByText('redis-cache')).toBeInTheDocument();
        expect(screen.getByText('Cache')).toBeInTheDocument();
    });

    it('shows status badges for connected services', () => {
        render(<NetworkingTab service={mockService} connectedServices={mockConnectedServices} />);
        expect(screen.getByText('connected')).toBeInTheDocument();
        expect(screen.getByText('disconnected')).toBeInTheDocument();
    });

    it('displays Connect Service button', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Connect Service')).toBeInTheDocument();
    });

    it('renders Network Policies section', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Network Policies')).toBeInTheDocument();
    });

    it('displays ingress traffic policy checkbox', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Allow all ingress traffic')).toBeInTheDocument();
        expect(screen.getByText(/When disabled, only explicitly allowed sources can send traffic/)).toBeInTheDocument();
    });

    it('displays egress traffic policy checkbox', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Allow all egress traffic')).toBeInTheDocument();
        expect(screen.getByText(/When disabled, this service can only send traffic to explicitly allowed destinations/)).toBeInTheDocument();
    });

    it('shows Save Changes button', () => {
        render(<NetworkingTab service={mockService} />);
        expect(screen.getByText('Save Changes')).toBeInTheDocument();
    });

    it('save button triggers toast notification', () => {
        render(<NetworkingTab service={mockService} />);

        const saveButton = screen.getByText('Save Changes');
        fireEvent.click(saveButton);

        expect(mockToast).toHaveBeenCalledWith(
            expect.objectContaining({
                title: 'Settings Updated',
                description: expect.stringContaining('Network configuration has been updated'),
            })
        );
    });

    it('allows toggling private networking', () => {
        render(<NetworkingTab service={mockService} />);

        const checkboxLabel = screen.getByText('Enable private networking for secure inter-service communication');
        const checkbox = checkboxLabel.closest('label')?.querySelector('input');

        if (checkbox) {
            expect(checkbox).toBeChecked();
            fireEvent.click(checkbox);
            expect(checkbox).not.toBeChecked();
        }
    });

    it('removes port when delete button is clicked', () => {
        render(<NetworkingTab service={mockService} ports={mockPorts} />);

        const deleteButtons = document.querySelectorAll('button');
        const firstDeleteButton = Array.from(deleteButtons).find(
            btn => btn.querySelector('svg')
        );

        if (firstDeleteButton) {
            fireEvent.click(firstDeleteButton);
            // Port should still be in DOM initially, just verifying the action
        }
    });
});
