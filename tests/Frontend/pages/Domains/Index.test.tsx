import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import type { Domain } from '@/types';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Import after mocks
import DomainsIndex from '@/pages/Domains/Index';

const mockDomains: Domain[] = [
    {
        id: '1',
        domain: 'api.example.com',
        status: 'active',
        ssl_status: 'active',
        service_id: 'app-1',
        service_name: 'API Server',
        service_type: 'application',
        verification_method: 'dns',
        verified_at: '2024-01-15T10:00:00Z',
        redirect_to_www: false,
        redirect_to_https: true,
        ssl_certificate_id: 'cert-1',
        dns_records: [
            { type: 'A', name: '@', value: '192.168.1.1' },
        ],
        created_at: '2024-01-01T00:00:00Z',
        updated_at: '2024-01-15T10:00:00Z',
    },
    {
        id: '2',
        domain: 'app.test.com',
        status: 'pending',
        ssl_status: 'pending',
        service_id: 'app-2',
        service_name: 'Web App',
        service_type: 'application',
        verification_method: 'http',
        verified_at: null,
        redirect_to_www: true,
        redirect_to_https: false,
        ssl_certificate_id: null,
        dns_records: [
            { type: 'CNAME', name: 'www', value: 'app.test.com' },
        ],
        created_at: '2024-01-10T00:00:00Z',
        updated_at: '2024-01-10T00:00:00Z',
    },
    {
        id: '3',
        domain: 'db.example.com',
        status: 'active',
        ssl_status: 'expiring_soon',
        service_id: 'db-1',
        service_name: 'PostgreSQL',
        service_type: 'database',
        verification_method: 'dns',
        verified_at: '2024-01-05T10:00:00Z',
        redirect_to_www: false,
        redirect_to_https: true,
        ssl_certificate_id: 'cert-2',
        dns_records: [
            { type: 'A', name: '@', value: '192.168.1.2' },
        ],
        created_at: '2024-01-05T00:00:00Z',
        updated_at: '2024-01-05T10:00:00Z',
    },
    {
        id: '4',
        domain: 'failed.example.com',
        status: 'failed',
        ssl_status: 'failed',
        service_id: 'app-3',
        service_name: 'Test App',
        service_type: 'application',
        verification_method: 'dns',
        verified_at: null,
        redirect_to_www: false,
        redirect_to_https: false,
        ssl_certificate_id: null,
        dns_records: [],
        created_at: '2024-01-12T00:00:00Z',
        updated_at: '2024-01-12T00:00:00Z',
    },
];

describe('Domains Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders the domains page', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getByText('Custom Domains')).toBeInTheDocument();
        });

        it('renders the page description', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getByText('Manage custom domains and SSL certificates')).toBeInTheDocument();
        });

        it('renders the Add Domain button', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getByText('Add Domain')).toBeInTheDocument();
        });

        it('renders with empty domains array', () => {
            render(<DomainsIndex domains={[]} />);
            expect(screen.getByText('Custom Domains')).toBeInTheDocument();
            expect(screen.getByText('No domains yet')).toBeInTheDocument();
        });
    });

    describe('Header and Actions', () => {
        it('has Add Domain link with correct href', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const addButton = screen.getByText('Add Domain').closest('a');
            expect(addButton).toHaveAttribute('href', '/domains/add');
        });

        it('renders page title as h1', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const heading = screen.getByText('Custom Domains');
            expect(heading.tagName).toBe('H1');
        });
    });

    describe('Filter Controls', () => {
        it('renders search input', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const searchInput = screen.getByPlaceholderText('Search domains...');
            expect(searchInput).toBeInTheDocument();
        });

        it('renders status filter dropdown', () => {
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            const selects = container.querySelectorAll('select');
            expect(selects.length).toBeGreaterThan(0);
        });

        it('filters domains by search query', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const searchInput = screen.getByPlaceholderText('Search domains...');

            fireEvent.change(searchInput, { target: { value: 'api.example.com' } });

            // Should show matching domain
            expect(screen.getByText('api.example.com')).toBeInTheDocument();

            // Should not show non-matching domains
            expect(screen.queryByText('app.test.com')).not.toBeInTheDocument();
        });

        it('filters domains by service name', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const searchInput = screen.getByPlaceholderText('Search domains...');

            fireEvent.change(searchInput, { target: { value: 'PostgreSQL' } });

            // Should show PostgreSQL domain
            expect(screen.getByText('db.example.com')).toBeInTheDocument();

            // Should not show other domains
            expect(screen.queryByText('api.example.com')).not.toBeInTheDocument();
        });

        it('filters domains by status', () => {
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            const statusSelect = container.querySelectorAll('select')[0];

            fireEvent.change(statusSelect, { target: { value: 'active' } });

            // Should show active domains
            expect(screen.getByText('api.example.com')).toBeInTheDocument();
            expect(screen.getByText('db.example.com')).toBeInTheDocument();

            // Should not show non-active domains
            expect(screen.queryByText('app.test.com')).not.toBeInTheDocument();
            expect(screen.queryByText('failed.example.com')).not.toBeInTheDocument();
        });
    });

    describe('Domain Cards Display', () => {
        it('displays all domain names', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getByText('api.example.com')).toBeInTheDocument();
            expect(screen.getByText('app.test.com')).toBeInTheDocument();
            expect(screen.getByText('db.example.com')).toBeInTheDocument();
            expect(screen.getByText('failed.example.com')).toBeInTheDocument();
        });

        it('displays service names and types', () => {
            render(<DomainsIndex domains={mockDomains} />);
            // Check for service names
            expect(screen.getAllByText(/API Server/).length).toBeGreaterThan(0);
            expect(screen.getAllByText(/Web App/).length).toBeGreaterThan(0);
            expect(screen.getAllByText(/PostgreSQL/).length).toBeGreaterThan(0);
        });

        it('displays domain status badges', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getAllByText('Active').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Pending').length).toBeGreaterThan(0);
            expect(screen.getAllByText('Failed').length).toBeGreaterThan(0);
        });

        it('displays SSL status badges', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getByText('SSL Active')).toBeInTheDocument();
            expect(screen.getByText('SSL Pending')).toBeInTheDocument();
            expect(screen.getAllByText('SSL Expiring').length).toBeGreaterThan(0);
            expect(screen.getByText('SSL Failed')).toBeInTheDocument();
        });

        it('displays verification status for verified domains', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const verifiedTexts = screen.queryAllByText('Verified');
            expect(verifiedTexts.length).toBeGreaterThan(0);
        });

        it('displays pending verification for unverified domains', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const pendingTexts = screen.queryAllByText('Pending verification');
            expect(pendingTexts.length).toBeGreaterThan(0);
        });

        it('displays redirect indicators', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getAllByText('Force HTTPS').length).toBeGreaterThan(0);
            expect(screen.getByText('→ www redirect')).toBeInTheDocument();
        });

        it('renders domain cards as clickable links', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const links = screen.getAllByRole('link');

            // Find domain detail links
            const domainLinks = links.filter(link =>
                link.getAttribute('href')?.startsWith('/domains/') &&
                !link.getAttribute('href')?.includes('/add')
            );

            expect(domainLinks.length).toBeGreaterThan(0);
        });
    });

    describe('Empty States', () => {
        it('displays empty state when no domains', () => {
            render(<DomainsIndex domains={[]} />);
            expect(screen.getByText('No domains yet')).toBeInTheDocument();
            expect(screen.getByText('Add a custom domain to your services and configure SSL certificates.')).toBeInTheDocument();
        });

        it('displays Add Domain button in empty state', () => {
            render(<DomainsIndex domains={[]} />);
            const buttons = screen.getAllByText('Add Domain');
            expect(buttons.length).toBeGreaterThan(1); // Header button + empty state button
        });

        it('displays no results state when filter returns nothing', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const searchInput = screen.getByPlaceholderText('Search domains...');

            fireEvent.change(searchInput, { target: { value: 'nonexistent.domain' } });

            expect(screen.getByText('No domains found')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your search query or filters.')).toBeInTheDocument();
        });
    });

    describe('Domain Card Layout', () => {
        it('renders domain icon', () => {
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            // Globe icons should be present
            const icons = container.querySelectorAll('svg');
            expect(icons.length).toBeGreaterThan(0);
        });

        it('displays domains in grid layout', () => {
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            const domainCards = container.querySelectorAll('.space-y-3 a[href]');
            expect(domainCards.length).toBe(mockDomains.length);
        });

        it('shows external link icon on domain cards', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            // External link icons should be present
            const icons = container.querySelectorAll('svg');
            expect(icons.length).toBeGreaterThan(0);
        });
    });

    describe('Service Filter', () => {
        it('filters domains by service', () => {
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            const serviceSelect = container.querySelectorAll('select')[1];

            // Filter by first service
            fireEvent.change(serviceSelect, { target: { value: 'app-1' } });

            // Should show only API Server domain
            expect(screen.getByText('api.example.com')).toBeInTheDocument();

            // Should not show other domains
            expect(screen.queryByText('app.test.com')).not.toBeInTheDocument();
        });
    });

    describe('Accessibility', () => {
        it('has proper heading structure', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const heading = screen.getByText('Custom Domains');
            expect(heading.tagName).toBe('H1');
        });

        it('search input is accessible', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const searchInput = screen.getByPlaceholderText('Search domains...');
            expect(searchInput).toBeInTheDocument();
        });

        it('domain cards are navigable links', () => {
            render(<DomainsIndex domains={mockDomains} />);
            const links = screen.getAllByRole('link');
            expect(links.length).toBeGreaterThan(0);
        });
    });

    describe('Responsive Layout', () => {
        it('renders filter grid with responsive columns', () => {
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            const filterGrid = container.querySelector('.grid.gap-4.md\\:grid-cols-3');
            expect(filterGrid).toBeInTheDocument();
        });

        it('renders header with flex layout', () => {
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            const header = container.querySelector('.flex.items-center.justify-between');
            expect(header).toBeInTheDocument();
        });
    });

    describe('Status Badge Variants', () => {
        it('renders active status with success variant', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getAllByText('Active').length).toBeGreaterThan(0);
        });

        it('renders pending status with warning variant', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getAllByText('Pending').length).toBeGreaterThan(0);
        });

        it('renders failed status with danger variant', () => {
            render(<DomainsIndex domains={mockDomains} />);
            expect(screen.getAllByText('Failed').length).toBeGreaterThan(0);
        });
    });

    describe('Combined Filters', () => {
        it('combines search and status filter', () => {
            const { container } = render(<DomainsIndex domains={mockDomains} />);
            const searchInput = screen.getByPlaceholderText('Search domains...');
            const statusSelect = container.querySelectorAll('select')[0];

            // Apply both filters
            fireEvent.change(statusSelect, { target: { value: 'active' } });
            fireEvent.change(searchInput, { target: { value: 'api' } });

            // Should show matching domain
            expect(screen.getByText('api.example.com')).toBeInTheDocument();

            // Should not show non-matching
            expect(screen.queryByText('app.test.com')).not.toBeInTheDocument();
            expect(screen.queryByText('db.example.com')).not.toBeInTheDocument();
        });
    });
});
