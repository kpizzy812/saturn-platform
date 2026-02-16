import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import SSLIndex from '../../../../resources/js/pages/SSL/Index';
import type { SSLCertificate } from '../../../../resources/js/types';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
            <a href={href}>{children}</a>
        ),
    };
});

describe('SSL/Index', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const mockCertificates: SSLCertificate[] = [
        {
            id: 1,
            domain: 'example.com',
            domains: ['example.com', 'www.example.com'],
            issuer: "Let's Encrypt",
            type: 'letsencrypt',
            status: 'active',
            auto_renew: true,
            expires_at: '2024-12-31T23:59:59Z',
            days_until_expiry: 120,
        },
        {
            id: 2,
            domain: 'staging.example.com',
            domains: ['staging.example.com'],
            issuer: 'Custom CA',
            type: 'custom',
            status: 'expiring_soon',
            auto_renew: false,
            expires_at: '2024-02-28T23:59:59Z',
            days_until_expiry: 15,
        },
        {
            id: 3,
            domain: 'old.example.com',
            domains: ['old.example.com'],
            issuer: "Let's Encrypt",
            type: 'letsencrypt',
            status: 'expired',
            auto_renew: false,
            expires_at: '2024-01-01T00:00:00Z',
            days_until_expiry: -30,
        },
    ];

    it('renders SSL certificates page with heading', () => {
        render(<SSLIndex certificates={[]} />);

        expect(screen.getByRole('heading', { level: 1, name: /ssl certificates/i })).toBeInTheDocument();
        expect(screen.getByText(/manage ssl certificates and auto-renewal/i)).toBeInTheDocument();
    });

    it('renders upload certificate button', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        // With certificates present, only the header Upload Certificate link renders (no empty state)
        const uploadButton = screen.getByRole('link', { name: /upload certificate/i });
        expect(uploadButton).toBeInTheDocument();
        expect(uploadButton).toHaveAttribute('href', '/ssl/upload');
    });

    it('displays statistics cards', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getByText('Total Certificates')).toBeInTheDocument();
        expect(screen.getByText('3')).toBeInTheDocument();

        // "Active", "Expiring Soon", "Expired" appear in both stats labels and status badges
        expect(screen.getAllByText('Active').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('Expiring Soon').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('Expired').length).toBeGreaterThanOrEqual(1);
    });

    it('renders search input', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getByPlaceholderText(/search certificates by domain or issuer/i)).toBeInTheDocument();
    });

    it('shows status filter buttons', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getByRole('button', { name: /^all$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^active$/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /expiring soon/i })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^expired$/i })).toBeInTheDocument();
    });

    it('shows empty state when no certificates exist', () => {
        render(<SSLIndex certificates={[]} />);

        expect(screen.getByText(/no ssl certificates yet/i)).toBeInTheDocument();
        expect(screen.getByText(/ssl certificates are automatically generated when you add a domain/i)).toBeInTheDocument();
    });

    it('displays empty state action buttons', () => {
        render(<SSLIndex certificates={[]} />);

        const addDomainLink = screen.getByRole('link', { name: /add domain/i });
        expect(addDomainLink).toHaveAttribute('href', '/domains/add');

        // Header + empty state both show "Upload Certificate" links
        const uploadLinks = screen.getAllByRole('link', { name: /upload certificate/i });
        expect(uploadLinks.length).toBeGreaterThanOrEqual(2);
        uploadLinks.forEach(link => expect(link).toHaveAttribute('href', '/ssl/upload'));
    });

    it('displays certificate cards when certificates exist', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        // "example.com" appears as heading AND in the domains list
        expect(screen.getAllByText('example.com').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText('staging.example.com')).toBeInTheDocument();
        expect(screen.getByText('old.example.com')).toBeInTheDocument();
    });

    it('shows certificate issuer information', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getAllByText(/issued by let's encrypt/i)).toHaveLength(2);
        expect(screen.getByText(/issued by custom ca/i)).toBeInTheDocument();
    });

    it('displays certificate type badges', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getAllByText("Let's Encrypt")).toHaveLength(2);
        expect(screen.getByText('Custom')).toBeInTheDocument();
    });

    it('shows certificate status badges', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        // Status text appears in stats cards AND status badges
        expect(screen.getAllByText('Active').length).toBeGreaterThanOrEqual(2);
        expect(screen.getAllByText('Expiring Soon').length).toBeGreaterThanOrEqual(2);
        expect(screen.getAllByText('Expired').length).toBeGreaterThanOrEqual(2);
    });

    it('displays expiry dates', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getAllByText(/expires/i)).toHaveLength(mockCertificates.length);
    });

    it('shows days until expiry', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getByText(/120 days remaining/i)).toBeInTheDocument();
        expect(screen.getByText(/15 days remaining/i)).toBeInTheDocument();
        expect(screen.getByText(/expired 30 days ago/i)).toBeInTheDocument();
    });

    it('displays additional domains for multi-domain certificates', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getByText('www.example.com')).toBeInTheDocument();
    });

    it('shows auto-renewal status', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getByText(/auto-renewal enabled/i)).toBeInTheDocument();
        expect(screen.getAllByText(/auto-renewal disabled/i)).toHaveLength(2);
    });

    it('displays will auto-renew badge for active auto-renew certificates', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        expect(screen.getByText('Will auto-renew')).toBeInTheDocument();
    });

    it('shows action required badge for expiring certificates without auto-renewal', () => {
        render(<SSLIndex certificates={mockCertificates} />);

        // Both cert id=2 (15 days, no auto-renew) and id=3 (-30 days, no auto-renew) match the condition
        expect(screen.getAllByText('Action required').length).toBeGreaterThanOrEqual(1);
    });

    it('filters certificates by search query on domain', async () => {
        const { user } = render(<SSLIndex certificates={mockCertificates} />);

        const searchInput = screen.getByPlaceholderText(/search certificates/i);
        await user.type(searchInput, 'staging');

        expect(screen.getByText('staging.example.com')).toBeInTheDocument();
        expect(screen.queryByText('example.com')).not.toBeInTheDocument();
        expect(screen.queryByText('old.example.com')).not.toBeInTheDocument();
    });

    it('filters certificates by search query on issuer', async () => {
        const { user } = render(<SSLIndex certificates={mockCertificates} />);

        const searchInput = screen.getByPlaceholderText(/search certificates/i);
        await user.type(searchInput, 'custom ca');

        expect(screen.getByText('staging.example.com')).toBeInTheDocument();
        expect(screen.queryByText('example.com')).not.toBeInTheDocument();
    });

    it('filters certificates by status', async () => {
        const { user } = render(<SSLIndex certificates={mockCertificates} />);

        const activeButton = screen.getByRole('button', { name: /^active$/i });
        await user.click(activeButton);

        // "example.com" appears in heading + domains list for the active cert
        expect(screen.getAllByText('example.com').length).toBeGreaterThanOrEqual(1);
        expect(screen.queryByText('staging.example.com')).not.toBeInTheDocument();
        expect(screen.queryByText('old.example.com')).not.toBeInTheDocument();
    });

    it('filters certificates by expiring soon status', async () => {
        const { user } = render(<SSLIndex certificates={mockCertificates} />);

        const expiringSoonButton = screen.getByRole('button', { name: /expiring soon/i });
        await user.click(expiringSoonButton);

        expect(screen.queryByText('example.com')).not.toBeInTheDocument();
        expect(screen.getByText('staging.example.com')).toBeInTheDocument();
        expect(screen.queryByText('old.example.com')).not.toBeInTheDocument();
    });

    it('shows no results state when search has no matches', async () => {
        const { user } = render(<SSLIndex certificates={mockCertificates} />);

        const searchInput = screen.getByPlaceholderText(/search certificates/i);
        await user.type(searchInput, 'nonexistent');

        expect(screen.getByText(/no certificates found/i)).toBeInTheDocument();
        expect(screen.getByText(/try adjusting your search query or filters/i)).toBeInTheDocument();
    });

    it('displays more domains indicator when certificate has many domains', () => {
        const multiDomainCert: SSLCertificate = {
            id: 4,
            domain: 'multi.example.com',
            domains: ['multi.example.com', 'sub1.example.com', 'sub2.example.com', 'sub3.example.com', 'sub4.example.com', 'sub5.example.com', 'sub6.example.com'],
            issuer: "Let's Encrypt",
            type: 'letsencrypt',
            status: 'active',
            auto_renew: true,
            expires_at: '2024-12-31T23:59:59Z',
            days_until_expiry: 120,
        };

        render(<SSLIndex certificates={[multiDomainCert]} />);

        expect(screen.getByText('+2 more')).toBeInTheDocument();
    });
});
