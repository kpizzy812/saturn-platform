import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';

// Mock useConfirm
const mockConfirm = vi.fn(() => Promise.resolve(true));
vi.mock('@/components/ui', async (importOriginal) => {
    const mod = await importOriginal<typeof import('@/components/ui')>();
    return {
        ...mod,
        useConfirm: () => mockConfirm,
    };
});

import { DomainsTab } from '@/pages/Services/Domains';
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

const mockDomains = [
    {
        id: 1,
        domain: 'api.example.com',
        isPrimary: true,
        sslStatus: 'active' as const,
        sslProvider: 'letsencrypt' as const,
        createdAt: '2024-01-10',
    },
    {
        id: 2,
        domain: 'staging.example.com',
        isPrimary: false,
        sslStatus: 'pending' as const,
        sslProvider: 'letsencrypt' as const,
        createdAt: '2024-01-12',
    },
];

describe('Service Domains Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockConfirm.mockResolvedValue(true);
    });

    it('renders Domain Management heading', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText('Domain Management')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText('Manage custom domains and SSL certificates for your service')).toBeInTheDocument();
    });

    it('renders Add Domain button', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        const addButtons = screen.getAllByText('Add Domain');
        expect(addButtons.length).toBeGreaterThanOrEqual(1);
    });

    it('displays domain names', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText('api.example.com')).toBeInTheDocument();
        expect(screen.getByText('staging.example.com')).toBeInTheDocument();
    });

    it('shows Primary badge for primary domain', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText('Primary')).toBeInTheDocument();
    });

    it('shows SSL Active badge', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText('SSL Active')).toBeInTheDocument();
    });

    it('shows SSL Pending badge', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText('SSL Pending')).toBeInTheDocument();
    });

    it('shows empty state when no domains', () => {
        render(<DomainsTab service={mockService} domains={[]} />);
        expect(screen.getByText('No domains configured')).toBeInTheDocument();
        expect(screen.getByText('Add a custom domain to get started')).toBeInTheDocument();
    });

    it('renders SSL Certificate Information card', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText('SSL Certificate Information')).toBeInTheDocument();
    });

    it('shows SSL info text', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText(/All domains are automatically secured with free SSL certificates/)).toBeInTheDocument();
    });

    it('opens Add Domain modal on button click', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        const addButtons = screen.getAllByText('Add Domain');
        fireEvent.click(addButtons[0]);
        expect(screen.getByPlaceholderText('api.your-domain.com')).toBeInTheDocument();
    });

    it('shows DNS button for domains', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        const dnsButtons = screen.getAllByText('DNS');
        expect(dnsButtons.length).toBeGreaterThanOrEqual(1);
    });

    it('shows DNS instructions when DNS button clicked', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        const dnsButtons = screen.getAllByText('DNS');
        fireEvent.click(dnsButtons[0]);
        expect(screen.getByText('DNS Configuration')).toBeInTheDocument();
    });

    it('renders domain creation date', () => {
        render(<DomainsTab service={mockService} domains={mockDomains} />);
        expect(screen.getByText(/Added 2024-01-10/)).toBeInTheDocument();
    });
});
