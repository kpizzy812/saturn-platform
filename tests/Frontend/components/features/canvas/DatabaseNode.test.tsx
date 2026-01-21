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
import { DatabaseNode } from '@/components/features/canvas/nodes/DatabaseNode';

const mockNodeProps = {
    id: 'db-1',
    type: 'database',
    position: { x: 0, y: 0 },
    selected: false,
    isConnectable: true,
    xPos: 0,
    yPos: 0,
    dragging: false,
    zIndex: 0,
    data: {
        label: 'postgres',
        status: 'running',
        type: 'database',
        databaseType: 'postgresql',
        volume: 'postgres-data',
    },
};

describe('DatabaseNode', () => {
    it('renders database name', () => {
        render(<DatabaseNode {...mockNodeProps} />);
        expect(screen.getByText('postgres')).toBeInTheDocument();
    });

    it('displays online status for running databases', () => {
        render(<DatabaseNode {...mockNodeProps} />);
        expect(screen.getByText('Online')).toBeInTheDocument();
    });

    it('displays volume information when provided', () => {
        render(<DatabaseNode {...mockNodeProps} />);
        expect(screen.getByText('postgres-data')).toBeInTheDocument();
    });

    it('applies selected styling when selected', () => {
        const selectedProps = {
            ...mockNodeProps,
            selected: true,
        };
        const { container } = render(<DatabaseNode {...selectedProps} />);
        const node = container.querySelector('.border-cyan-500');
        expect(node).toBeTruthy();
    });

    it('renders database logo container', () => {
        const { container } = render(<DatabaseNode {...mockNodeProps} />);
        // PostgreSQL has bg-[#336791] background
        const iconContainer = container.querySelector('[class*="bg-"]');
        expect(iconContainer).toBeTruthy();
    });

    it('renders handles for connections', () => {
        render(<DatabaseNode {...mockNodeProps} />);
        expect(screen.getByTestId('handle-left')).toBeInTheDocument();
        expect(screen.getByTestId('handle-right')).toBeInTheDocument();
    });

    describe('Database Type Logos', () => {
        it('renders PostgreSQL logo with correct background', () => {
            const { container } = render(<DatabaseNode {...mockNodeProps} />);
            // Check for PostgreSQL brand color
            const logo = container.querySelector('[class*="bg-[#336791]"]');
            expect(logo).toBeTruthy();
        });

        it('renders MySQL logo with correct background', () => {
            const mysqlProps = {
                ...mockNodeProps,
                data: { ...mockNodeProps.data, databaseType: 'mysql', label: 'mysql-db' },
            };
            const { container } = render(<DatabaseNode {...mysqlProps} />);
            const logo = container.querySelector('[class*="bg-[#00758F]"]');
            expect(logo).toBeTruthy();
        });

        it('renders Redis logo with correct background', () => {
            const redisProps = {
                ...mockNodeProps,
                data: { ...mockNodeProps.data, databaseType: 'redis', label: 'redis-cache' },
            };
            const { container } = render(<DatabaseNode {...redisProps} />);
            const logo = container.querySelector('[class*="bg-[#D82C20]"]');
            expect(logo).toBeTruthy();
        });

        it('renders MongoDB logo with correct background', () => {
            const mongoProps = {
                ...mockNodeProps,
                data: { ...mockNodeProps.data, databaseType: 'mongodb', label: 'mongo-db' },
            };
            const { container } = render(<DatabaseNode {...mongoProps} />);
            const logo = container.querySelector('[class*="bg-[#47A248]"]');
            expect(logo).toBeTruthy();
        });

        it('renders generic icon for unknown database type', () => {
            const unknownProps = {
                ...mockNodeProps,
                data: { ...mockNodeProps.data, databaseType: 'unknown', label: 'custom-db' },
            };
            const { container } = render(<DatabaseNode {...unknownProps} />);
            const logo = container.querySelector('.bg-gray-600');
            expect(logo).toBeTruthy();
        });
    });

    describe('Status Colors', () => {
        it('uses emerald color for running status', () => {
            const { container } = render(<DatabaseNode {...mockNodeProps} />);
            const statusDot = container.querySelector('.bg-emerald-500');
            expect(statusDot).toBeTruthy();
        });

        it('uses gray color for non-running status', () => {
            const stoppedProps = {
                ...mockNodeProps,
                data: { ...mockNodeProps.data, status: 'stopped' },
            };
            const { container } = render(<DatabaseNode {...stoppedProps} />);
            const statusDot = container.querySelector('.bg-gray-500');
            expect(statusDot).toBeTruthy();
        });
    });

    describe('Database Types', () => {
        const databaseTypes = [
            'postgresql',
            'mysql',
            'mongodb',
            'redis',
        ];

        databaseTypes.forEach((dbType) => {
            it(`renders ${dbType} correctly`, () => {
                const dbProps = {
                    ...mockNodeProps,
                    data: {
                        ...mockNodeProps.data,
                        databaseType: dbType,
                        label: dbType,
                    },
                };
                render(<DatabaseNode {...dbProps} />);
                expect(screen.getByText(dbType)).toBeInTheDocument();
            });
        });
    });

    describe('Layout and Structure', () => {
        it('has proper card structure', () => {
            const { container } = render(<DatabaseNode {...mockNodeProps} />);
            const card = container.querySelector('.rounded-lg');
            expect(card).toBeTruthy();
        });

        it('has correct width', () => {
            const { container } = render(<DatabaseNode {...mockNodeProps} />);
            const card = container.querySelector('.w-\\[220px\\]');
            expect(card).toBeTruthy();
        });

        it('has dark theme background', () => {
            const { container } = render(<DatabaseNode {...mockNodeProps} />);
            const card = container.querySelector('[class*="bg-[#1a1a2e]"]');
            expect(card).toBeTruthy();
        });

        it('has volume section when volume is provided', () => {
            const { container } = render(<DatabaseNode {...mockNodeProps} />);
            const volumeSection = container.querySelector('.border-t');
            expect(volumeSection).toBeTruthy();
        });

        it('does not show volume section when volume is not provided', () => {
            const noVolumeProps = {
                ...mockNodeProps,
                data: { ...mockNodeProps.data, volume: undefined },
            };
            const { container } = render(<DatabaseNode {...noVolumeProps} />);
            const volumeSection = container.querySelector('.border-t');
            expect(volumeSection).toBeFalsy();
        });
    });
});
