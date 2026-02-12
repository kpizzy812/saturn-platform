import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import ServerResourcesIndex from '@/pages/Servers/Resources/Index';
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

describe('Server Resources Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<ServerResourcesIndex server={mockServer} applications={5} databases={3} services={2} />);

            expect(screen.getByText('Server Resources')).toBeInTheDocument();
            expect(screen.getByText('10 resources running on production-server')).toBeInTheDocument();
        });

        it('should render singular resource text when count is 1', () => {
            render(<ServerResourcesIndex server={mockServer} applications={1} databases={0} services={0} />);

            expect(screen.getByText('1 resource running on production-server')).toBeInTheDocument();
        });

        it('should render breadcrumbs', () => {
            render(<ServerResourcesIndex server={mockServer} />);

            const breadcrumbs = screen.getAllByText('Servers');
            expect(breadcrumbs.length).toBeGreaterThan(0);
        });

        it('should render back to server link', () => {
            render(<ServerResourcesIndex server={mockServer} />);

            const backLink = screen.getByText('Back to Server');
            expect(backLink).toBeInTheDocument();
            expect(backLink.closest('a')).toHaveAttribute('href', '/servers/server-uuid-1');
        });
    });

    describe('resource stats cards', () => {
        it('should render all three resource type cards', () => {
            render(<ServerResourcesIndex server={mockServer} applications={5} databases={3} services={2} />);

            const applicationsText = screen.getAllByText('Applications');
            const databasesText = screen.getAllByText('Databases');
            const servicesText = screen.getAllByText('Services');

            expect(applicationsText.length).toBeGreaterThan(0);
            expect(databasesText.length).toBeGreaterThan(0);
            expect(servicesText.length).toBeGreaterThan(0);
        });

        it('should display resource counts', () => {
            render(<ServerResourcesIndex server={mockServer} applications={5} databases={3} services={2} />);

            const fiveText = screen.getAllByText('5');
            const threeText = screen.getAllByText('3');
            const twoText = screen.getAllByText('2');

            expect(fiveText.length).toBeGreaterThan(0);
            expect(threeText.length).toBeGreaterThan(0);
            expect(twoText.length).toBeGreaterThan(0);
        });

        it('should display resource descriptions', () => {
            render(<ServerResourcesIndex server={mockServer} />);

            expect(screen.getByText('Deployed applications')).toBeInTheDocument();
            expect(screen.getByText('Database instances')).toBeInTheDocument();
            const servicesDesc = screen.getByText('Docker Compose services');
            expect(servicesDesc).toBeInTheDocument();
        });

        it('should link to resource pages', () => {
            render(<ServerResourcesIndex server={mockServer} />);

            const applicationsCards = screen.getAllByText('Applications');
            const applicationsCard = applicationsCards[0].closest('a');

            expect(applicationsCard).toHaveAttribute('href', '/servers/server-uuid-1/resources/applications');
        });

        it('should use default counts of 0 when not provided', () => {
            render(<ServerResourcesIndex server={mockServer} />);

            const counts = screen.getAllByText('0');
            expect(counts.length).toBeGreaterThanOrEqual(3); // Applications, Databases, Services all show 0
        });
    });

    describe('empty state', () => {
        it('should show empty state when no resources', () => {
            render(<ServerResourcesIndex server={mockServer} applications={0} databases={0} services={0} />);

            expect(screen.getByText('No resources yet')).toBeInTheDocument();
            expect(screen.getByText('Deploy applications, databases, or services to see them here')).toBeInTheDocument();
        });

        it('should display correct total count in empty state', () => {
            render(<ServerResourcesIndex server={mockServer} />);

            expect(screen.getByText('0 resources running on production-server')).toBeInTheDocument();
        });
    });

    describe('resource details sections', () => {
        it('should show applications detail section when applications exist', () => {
            render(<ServerResourcesIndex server={mockServer} applications={5} databases={0} services={0} />);

            const applicationsText = screen.getAllByText('Applications');
            expect(applicationsText.length).toBeGreaterThan(1);
            expect(screen.getByText('View all applications running on this server')).toBeInTheDocument();
            expect(screen.getByText('View Applications →')).toBeInTheDocument();
        });

        it('should show databases detail section when databases exist', () => {
            render(<ServerResourcesIndex server={mockServer} applications={0} databases={3} services={0} />);

            const databasesText = screen.getAllByText('Databases');
            expect(databasesText.length).toBeGreaterThan(1);
            expect(screen.getByText('Manage database instances on this server')).toBeInTheDocument();
            expect(screen.getByText('View Databases →')).toBeInTheDocument();
        });

        it('should show services detail section when services exist', () => {
            render(<ServerResourcesIndex server={mockServer} applications={0} databases={0} services={2} />);

            const servicesText = screen.getAllByText('Services');
            expect(servicesText.length).toBeGreaterThan(1);
            expect(screen.getByText('Docker Compose services deployed on this server')).toBeInTheDocument();
            expect(screen.getByText('View Services →')).toBeInTheDocument();
        });

        it('should show all detail sections when all resources exist', () => {
            render(<ServerResourcesIndex server={mockServer} applications={5} databases={3} services={2} />);

            expect(screen.getByText('View all applications running on this server')).toBeInTheDocument();
            expect(screen.getByText('Manage database instances on this server')).toBeInTheDocument();
            expect(screen.getByText('Docker Compose services deployed on this server')).toBeInTheDocument();
        });

        it('should not show detail sections when resources are zero', () => {
            render(<ServerResourcesIndex server={mockServer} applications={0} databases={0} services={0} />);

            expect(screen.queryByText('View all applications running on this server')).not.toBeInTheDocument();
            expect(screen.queryByText('Manage database instances on this server')).not.toBeInTheDocument();
            expect(screen.queryByText('Docker Compose services deployed on this server')).not.toBeInTheDocument();
        });
    });

    describe('resource badges', () => {
        it('should display count badges in detail sections', () => {
            render(<ServerResourcesIndex server={mockServer} applications={5} databases={3} services={2} />);

            const badges = screen.getAllByText(/^[0-9]+$/);
            expect(badges.length).toBeGreaterThan(0);
        });
    });

    describe('navigation links', () => {
        it('should have correct links in detail sections', () => {
            render(<ServerResourcesIndex server={mockServer} applications={5} databases={3} services={2} />);

            const viewAppsLink = screen.getByText('View Applications →').closest('a');
            const viewDbsLink = screen.getByText('View Databases →').closest('a');
            const viewServicesLink = screen.getByText('View Services →').closest('a');

            expect(viewAppsLink).toHaveAttribute('href', '/servers/server-uuid-1/resources/applications');
            expect(viewDbsLink).toHaveAttribute('href', '/servers/server-uuid-1/resources/databases');
            expect(viewServicesLink).toHaveAttribute('href', '/servers/server-uuid-1/resources/services');
        });
    });

    describe('edge cases', () => {
        it('should handle server with different UUID', () => {
            const differentServer = {
                ...mockServer,
                uuid: 'different-uuid',
                name: 'staging-server',
            };

            render(<ServerResourcesIndex server={differentServer} applications={2} />);

            expect(screen.getByText('2 resources running on staging-server')).toBeInTheDocument();
            const appsCards = screen.getAllByText('Applications');
            const appsCard = appsCards[0].closest('a');
            expect(appsCard).toHaveAttribute('href', '/servers/different-uuid/resources/applications');
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

            render(<ServerResourcesIndex server={minimalServer} />);

            expect(screen.getByText('Server Resources')).toBeInTheDocument();
        });

        it('should handle large resource counts', () => {
            render(<ServerResourcesIndex server={mockServer} applications={100} databases={50} services={25} />);

            expect(screen.getByText('175 resources running on production-server')).toBeInTheDocument();
            const allCounts = screen.getAllByText('100');
            expect(allCounts.length).toBeGreaterThan(0);
        });

        it('should handle only one resource type having count', () => {
            render(<ServerResourcesIndex server={mockServer} applications={0} databases={5} services={0} />);

            expect(screen.getByText('5 resources running on production-server')).toBeInTheDocument();
            expect(screen.getByText('View Databases →')).toBeInTheDocument();
            expect(screen.queryByText('View Applications →')).not.toBeInTheDocument();
            expect(screen.queryByText('View Services →')).not.toBeInTheDocument();
        });
    });
});
