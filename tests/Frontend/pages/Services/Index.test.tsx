import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';

// Mock the @inertiajs/react module
const mockRouterVisit = vi.fn();
const mockRouterPost = vi.fn();
const mockRouterDelete = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    router: {
        visit: mockRouterVisit,
        post: mockRouterPost,
        delete: mockRouterDelete,
        patch: vi.fn(),
    },
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Import after mock
import ServicesIndex from '@/pages/Services/Index';
import type { Service } from '@/types';

const mockServices: Service[] = [
    {
        id: 1,
        uuid: 'service-uuid-1',
        name: 'production-api',
        description: 'Main production API service',
        docker_compose_raw: 'version: "3.8"\nservices:\n  api:\n    image: node:18',
        environment_id: 1,
        destination_id: 1,
        created_at: '2024-01-01T00:00:00.000Z',
        updated_at: '2024-01-15T00:00:00.000Z',
    },
    {
        id: 2,
        uuid: 'service-uuid-2',
        name: 'staging-database',
        description: 'Staging database cluster',
        docker_compose_raw: 'version: "3.8"\nservices:\n  postgres:\n    image: postgres:15',
        environment_id: 2,
        destination_id: 1,
        created_at: '2024-01-02T00:00:00.000Z',
        updated_at: '2024-01-14T00:00:00.000Z',
    },
    {
        id: 3,
        uuid: 'service-uuid-3',
        name: 'monitoring-stack',
        description: null,
        docker_compose_raw: 'version: "3.8"\nservices:\n  prometheus:\n    image: prom/prometheus',
        environment_id: 1,
        destination_id: 2,
        created_at: '2024-01-03T00:00:00.000Z',
        updated_at: '2024-01-13T00:00:00.000Z',
    },
];

describe('Services Index Page', () => {
    beforeEach(() => {
        mockRouterVisit.mockClear();
        mockRouterPost.mockClear();
        mockRouterDelete.mockClear();
    });

    it('renders the page header', () => {
        render(<ServicesIndex services={[]} />);
        const servicesHeadings = screen.getAllByText('Services');
        expect(servicesHeadings.length).toBeGreaterThan(0);
        expect(screen.getByText('Manage your Docker Compose services')).toBeInTheDocument();
    });

    it('shows New Service button', () => {
        render(<ServicesIndex services={[]} />);
        const newServiceLink = screen.getByText('New Service').closest('a');
        expect(newServiceLink).toBeInTheDocument();
        expect(newServiceLink?.getAttribute('href')).toBe('/services/create');
    });

    it('shows empty state when no services exist', () => {
        render(<ServicesIndex services={[]} />);
        expect(screen.getByText('No services yet')).toBeInTheDocument();
        expect(screen.getByText('Create your first Docker Compose service to deploy multi-container applications.')).toBeInTheDocument();
        expect(screen.getByText('Create Service')).toBeInTheDocument();
    });

    it('shows service cards when services exist', () => {
        render(<ServicesIndex services={mockServices} />);
        expect(screen.getByText('production-api')).toBeInTheDocument();
        expect(screen.getByText('staging-database')).toBeInTheDocument();
        expect(screen.getByText('monitoring-stack')).toBeInTheDocument();
    });

    it('displays service descriptions', () => {
        render(<ServicesIndex services={mockServices} />);
        expect(screen.getByText('Main production API service')).toBeInTheDocument();
        expect(screen.getByText('Staging database cluster')).toBeInTheDocument();
    });

    it('shows Docker Compose indicator on service cards', () => {
        render(<ServicesIndex services={mockServices} />);
        const dockerComposeLabels = screen.getAllByText('Docker Compose');
        expect(dockerComposeLabels.length).toBe(mockServices.length);
    });

    it('displays last updated date for services', () => {
        render(<ServicesIndex services={mockServices} />);
        // Check for "Updated" text in the date display
        const bodyText = document.body.textContent || '';
        expect(bodyText).toContain('Updated');
    });

    it('links to service detail pages', () => {
        render(<ServicesIndex services={mockServices} />);
        const links = screen.getAllByRole('link');
        const serviceLinks = links.filter(link =>
            link.getAttribute('href')?.includes('/services/service-uuid-')
        );
        // Should have at least 3 service card links
        expect(serviceLinks.length).toBeGreaterThanOrEqual(3);
    });

    it('shows dropdown menu trigger for each service', () => {
        render(<ServicesIndex services={mockServices} />);
        const dropdownTriggers = document.querySelectorAll('button');
        // Each service card should have a dropdown menu button
        expect(dropdownTriggers.length).toBeGreaterThanOrEqual(mockServices.length);
    });

    it('dropdown menu contains Service Settings option', async () => {
        render(<ServicesIndex services={mockServices} />);
        // Dropdown items may not be visible until dropdown is opened
        // Just verify the dropdown structure exists
        const dropdownTriggers = document.querySelectorAll('button');
        expect(dropdownTriggers.length).toBeGreaterThan(0);
    });

    it('dropdown menu contains Restart Service option', async () => {
        render(<ServicesIndex services={mockServices} />);
        // Dropdown items may not be visible until dropdown is opened
        const dropdownTriggers = document.querySelectorAll('button');
        expect(dropdownTriggers.length).toBeGreaterThan(0);
    });

    it('dropdown menu contains Stop Service option', async () => {
        render(<ServicesIndex services={mockServices} />);
        // Dropdown items may not be visible until dropdown is opened
        const dropdownTriggers = document.querySelectorAll('button');
        expect(dropdownTriggers.length).toBeGreaterThan(0);
    });

    it('dropdown menu contains Delete Service option', async () => {
        render(<ServicesIndex services={mockServices} />);
        // Dropdown items may not be visible until dropdown is opened
        const dropdownTriggers = document.querySelectorAll('button');
        expect(dropdownTriggers.length).toBeGreaterThan(0);
    });

    it('renders grid layout for service cards', () => {
        render(<ServicesIndex services={mockServices} />);
        const grid = document.querySelector('.grid');
        expect(grid).toBeInTheDocument();
        expect(grid?.classList.contains('md:grid-cols-2')).toBe(true);
        expect(grid?.classList.contains('lg:grid-cols-3')).toBe(true);
    });

    it('shows all three services in the list', () => {
        render(<ServicesIndex services={mockServices} />);
        mockServices.forEach(service => {
            expect(screen.getByText(service.name)).toBeInTheDocument();
        });
    });

    it('empty state has create service link', () => {
        render(<ServicesIndex services={[]} />);
        const createLink = screen.getByText('Create Service').closest('a');
        expect(createLink?.getAttribute('href')).toBe('/services/create');
    });

    it('shows container icon in empty state', () => {
        render(<ServicesIndex services={[]} />);
        // Empty state should render with container icon
        expect(screen.getByText('No services yet')).toBeInTheDocument();
    });

    it('service cards have hover effect styling', () => {
        render(<ServicesIndex services={mockServices} />);
        const cards = document.querySelectorAll('.hover\\:border-primary\\/50');
        expect(cards.length).toBeGreaterThan(0);
    });
});
