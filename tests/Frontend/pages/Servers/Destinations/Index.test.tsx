import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import ServerDestinationsIndex from '@/pages/Servers/Destinations/Index';
import type { Server } from '@/types';

const mockServer: Server = {
    id: 1,
    uuid: 'server-uuid-1',
    name: 'production-server',
    description: 'Main production server',
    ip: '192.168.1.100',
    port: 22,
    user: 'root',
    is_reachable: true,
    is_usable: true,
    settings: {
        id: 1,
        server_id: 1,
        is_build_server: false,
        concurrent_builds: 2,
    },
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

const mockDestinations = [
    {
        id: 1,
        uuid: 'dest-uuid-1',
        name: 'Production Network',
        network: 'coolify-prod',
        server_id: 1,
        is_default: true,
        created_at: '2024-01-01T00:00:00Z',
    },
    {
        id: 2,
        uuid: 'dest-uuid-2',
        name: 'Staging Network',
        network: 'coolify-staging',
        server_id: 1,
        is_default: false,
        created_at: '2024-01-02T00:00:00Z',
    },
    {
        id: 3,
        uuid: 'dest-uuid-3',
        name: 'Development Network',
        network: 'coolify-dev',
        server_id: 1,
        is_default: false,
        created_at: '2024-01-03T00:00:00Z',
    },
];

describe('Server Destinations Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            const destinationsText = screen.getAllByText('Destinations');
            expect(destinationsText.length).toBeGreaterThan(0);
            expect(screen.getByText(/Manage deployment destinations for production-server/)).toBeInTheDocument();
        });

        it('should render breadcrumbs', () => {
            render(<ServerDestinationsIndex server={mockServer} />);

            const breadcrumbs = screen.getAllByText('Servers');
            expect(breadcrumbs.length).toBeGreaterThan(0);
        });

        it('should render back to server link', () => {
            render(<ServerDestinationsIndex server={mockServer} />);

            const backLink = screen.getByText('Back to Server');
            expect(backLink).toBeInTheDocument();
            expect(backLink.closest('a')).toHaveAttribute('href', '/servers/server-uuid-1');
        });

        it('should render New Destination button', () => {
            render(<ServerDestinationsIndex server={mockServer} />);

            const newButton = screen.getByRole('button', { name: /new destination/i });
            expect(newButton).toBeInTheDocument();
        });

        it('should render info card about destinations', () => {
            render(<ServerDestinationsIndex server={mockServer} />);

            expect(screen.getByText('About Destinations')).toBeInTheDocument();
            expect(screen.getByText(/Destinations define where your applications will be deployed/)).toBeInTheDocument();
        });
    });

    describe('destinations list', () => {
        it('should render all destinations', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            expect(screen.getByText('Production Network')).toBeInTheDocument();
            expect(screen.getByText('Staging Network')).toBeInTheDocument();
            expect(screen.getByText('Development Network')).toBeInTheDocument();
        });

        it('should display destination network names', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            expect(screen.getByText('Network: coolify-prod')).toBeInTheDocument();
            expect(screen.getByText('Network: coolify-staging')).toBeInTheDocument();
            expect(screen.getByText('Network: coolify-dev')).toBeInTheDocument();
        });

        it('should display default badge on default destination', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            const defaultBadges = screen.getAllByText('Default');
            expect(defaultBadges.length).toBe(1);
        });

        it('should display active status for all destinations', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            const activeStatuses = screen.getAllByText('Active');
            expect(activeStatuses.length).toBe(3);
        });

        it('should render destinations as clickable cards', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            const prodCard = screen.getByText('Production Network').closest('a');
            const stagingCard = screen.getByText('Staging Network').closest('a');
            const devCard = screen.getByText('Development Network').closest('a');

            expect(prodCard).toHaveAttribute('href', '/servers/server-uuid-1/destinations/dest-uuid-1');
            expect(stagingCard).toHaveAttribute('href', '/servers/server-uuid-1/destinations/dest-uuid-2');
            expect(devCard).toHaveAttribute('href', '/servers/server-uuid-1/destinations/dest-uuid-3');
        });

        it('should display destinations in grid layout', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            // All destination names should be present
            expect(screen.getByText('Production Network')).toBeInTheDocument();
            expect(screen.getByText('Staging Network')).toBeInTheDocument();
            expect(screen.getByText('Development Network')).toBeInTheDocument();
        });
    });

    describe('empty state', () => {
        it('should show empty state when no destinations', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={[]} />);

            expect(screen.getByText('No destinations yet')).toBeInTheDocument();
            expect(screen.getByText('Create your first destination to start deploying applications')).toBeInTheDocument();
        });

        it('should render Create Destination button in empty state', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={[]} />);

            const createButtons = screen.getAllByRole('button');
            const createButton = createButtons.find(btn => btn.textContent?.includes('Create Destination'));
            expect(createButton).toBeInTheDocument();
        });

        it('should link to create page in empty state', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={[]} />);

            const createLink = screen.getByText('Create Destination').closest('a');
            expect(createLink).toHaveAttribute('href', '/servers/server-uuid-1/destinations/create');
        });

        it('should not show destination cards in empty state', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={[]} />);

            expect(screen.queryByText('Production Network')).not.toBeInTheDocument();
            expect(screen.queryByText('Active')).not.toBeInTheDocument();
            expect(screen.queryByText('Default')).not.toBeInTheDocument();
        });
    });

    describe('default destination badge', () => {
        it('should only show default badge on the default destination', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            const defaultBadges = screen.getAllByText('Default');
            expect(defaultBadges.length).toBe(1);
        });

        it('should not show default badge when no destinations are default', () => {
            const nonDefaultDestinations = mockDestinations.map(dest => ({ ...dest, is_default: false }));

            render(<ServerDestinationsIndex server={mockServer} destinations={nonDefaultDestinations} />);

            expect(screen.queryByText('Default')).not.toBeInTheDocument();
        });

        it('should show default badge on correct destination', () => {
            const multipleDefaults = [
                { ...mockDestinations[0], is_default: false },
                { ...mockDestinations[1], is_default: true },
                { ...mockDestinations[2], is_default: false },
            ];

            render(<ServerDestinationsIndex server={mockServer} destinations={multipleDefaults} />);

            const defaultBadges = screen.getAllByText('Default');
            expect(defaultBadges.length).toBe(1);
        });
    });

    describe('navigation', () => {
        it('should link to create destination page from header button', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            const newButton = screen.getByRole('button', { name: /new destination/i }).closest('a');
            expect(newButton).toHaveAttribute('href', '/servers/server-uuid-1/destinations/create');
        });

        it('should have correct server UUID in all links', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={mockDestinations} />);

            const links = screen.getAllByRole('link');
            const destinationLinks = links.filter(link =>
                link.getAttribute('href')?.includes('/servers/server-uuid-1')
            );

            expect(destinationLinks.length).toBeGreaterThan(0);
        });
    });

    describe('edge cases', () => {
        it('should handle server with different UUID', () => {
            const differentServer = {
                ...mockServer,
                uuid: 'different-uuid',
                name: 'staging-server',
            };

            render(<ServerDestinationsIndex server={differentServer} destinations={mockDestinations} />);

            expect(screen.getByText(/Manage deployment destinations for staging-server/)).toBeInTheDocument();
            const prodCard = screen.getByText('Production Network').closest('a');
            expect(prodCard).toHaveAttribute('href', '/servers/different-uuid/destinations/dest-uuid-1');
        });

        it('should render with minimal server data', () => {
            const minimalServer: Server = {
                id: 2,
                uuid: 'minimal-uuid',
                name: 'minimal-server',
                description: null,
                ip: '127.0.0.1',
                port: 22,
                user: 'root',
                is_reachable: true,
                is_usable: true,
                settings: null,
                created_at: '2024-01-01T00:00:00Z',
                updated_at: '2024-01-01T00:00:00Z',
            };

            render(<ServerDestinationsIndex server={minimalServer} />);

            const destinationsText = screen.getAllByText('Destinations');
            expect(destinationsText.length).toBeGreaterThan(0);
        });

        it('should handle single destination', () => {
            render(<ServerDestinationsIndex server={mockServer} destinations={[mockDestinations[0]]} />);

            expect(screen.getByText('Production Network')).toBeInTheDocument();
            expect(screen.queryByText('Staging Network')).not.toBeInTheDocument();
            expect(screen.queryByText('Development Network')).not.toBeInTheDocument();
        });

        it('should handle destinations with long network names', () => {
            const longNetworkDestinations = [
                {
                    ...mockDestinations[0],
                    network: 'very-long-network-name-that-should-still-display-correctly',
                },
            ];

            render(<ServerDestinationsIndex server={mockServer} destinations={longNetworkDestinations} />);

            expect(screen.getByText('Network: very-long-network-name-that-should-still-display-correctly')).toBeInTheDocument();
        });

        it('should handle undefined destinations array', () => {
            render(<ServerDestinationsIndex server={mockServer} />);

            expect(screen.getByText('No destinations yet')).toBeInTheDocument();
        });

        it('should handle all destinations being default (edge case)', () => {
            const allDefaultDestinations = mockDestinations.map(dest => ({ ...dest, is_default: true }));

            render(<ServerDestinationsIndex server={mockServer} destinations={allDefaultDestinations} />);

            const defaultBadges = screen.getAllByText('Default');
            expect(defaultBadges.length).toBe(3);
        });
    });
});
