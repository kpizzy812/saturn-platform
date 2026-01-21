import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../../utils/test-utils';

// Mock @xyflow/react
vi.mock('@xyflow/react', () => ({
    Handle: ({ position }: { position: string }) => <div data-testid={`handle-${position}`} />,
    Position: {
        Left: 'left',
        Right: 'right',
    },
}));

// Import after mocks
import { ServiceNode } from '@/components/features/canvas/nodes/ServiceNode';

const mockNodeProps = {
    id: 'app-1',
    type: 'service',
    position: { x: 0, y: 0 },
    selected: false,
    isConnectable: true,
    xPos: 0,
    yPos: 0,
    dragging: false,
    zIndex: 0,
    data: {
        label: 'api-server',
        status: 'running',
        type: 'application',
        fqdn: 'api.example.com',
        buildPack: 'dockerfile',
    },
};

describe('ServiceNode', () => {
    it('renders service name', () => {
        render(<ServiceNode {...mockNodeProps} />);
        expect(screen.getByText('api-server')).toBeInTheDocument();
    });

    it('renders FQDN when provided', () => {
        render(<ServiceNode {...mockNodeProps} />);
        expect(screen.getByText(/api.example.com/)).toBeInTheDocument();
    });

    it('displays online status for running services', () => {
        render(<ServiceNode {...mockNodeProps} />);
        expect(screen.getByText('Online')).toBeInTheDocument();
    });

    it('displays actual status for non-running services', () => {
        const stoppedProps = {
            ...mockNodeProps,
            data: {
                ...mockNodeProps.data,
                status: 'stopped',
            },
        };
        render(<ServiceNode {...stoppedProps} />);
        expect(screen.getByText('stopped')).toBeInTheDocument();
    });

    it('applies selected styling when selected', () => {
        const selectedProps = {
            ...mockNodeProps,
            selected: true,
        };
        const { container } = render(<ServiceNode {...selectedProps} />);
        const node = container.querySelector('.border-primary');
        expect(node).toBeTruthy();
    });

    it('renders GitHub icon container', () => {
        const { container } = render(<ServiceNode {...mockNodeProps} />);
        // GitHub icon has bg-[#24292e] background
        const iconContainer = container.querySelector('[class*="bg-"]');
        expect(iconContainer).toBeTruthy();
    });

    it('renders handles for connections', () => {
        render(<ServiceNode {...mockNodeProps} />);
        expect(screen.getByTestId('handle-left')).toBeInTheDocument();
        expect(screen.getByTestId('handle-right')).toBeInTheDocument();
    });

    it('handles missing FQDN gracefully', () => {
        const noFqdnProps = {
            ...mockNodeProps,
            data: {
                ...mockNodeProps.data,
                fqdn: null,
            },
        };
        render(<ServiceNode {...noFqdnProps} />);
        expect(screen.getByText('api-server')).toBeInTheDocument();
    });

    describe('Status Colors', () => {
        it('uses status-online class for running status', () => {
            const { container } = render(<ServiceNode {...mockNodeProps} />);
            // Running services use status-online animation class
            const statusDot = container.querySelector('.status-online');
            expect(statusDot).toBeTruthy();
        });

        it('uses muted color for non-running status', () => {
            const stoppedProps = {
                ...mockNodeProps,
                data: { ...mockNodeProps.data, status: 'stopped' },
            };
            const { container } = render(<ServiceNode {...stoppedProps} />);
            // Stopped services use foreground-subtle background
            const statusDot = container.querySelector('.bg-foreground-subtle');
            expect(statusDot).toBeTruthy();
        });
    });

    describe('Layout and Structure', () => {
        it('has proper card structure', () => {
            const { container } = render(<ServiceNode {...mockNodeProps} />);
            const card = container.querySelector('.rounded-lg');
            expect(card).toBeTruthy();
        });

        it('has correct width', () => {
            const { container } = render(<ServiceNode {...mockNodeProps} />);
            const card = container.querySelector('.w-\\[220px\\]');
            expect(card).toBeTruthy();
        });

        it('has Saturn design token background', () => {
            const { container } = render(<ServiceNode {...mockNodeProps} />);
            // Uses Saturn design tokens for background
            const card = container.querySelector('.bg-background-secondary\\/80');
            expect(card).toBeTruthy();
        });
    });
});
