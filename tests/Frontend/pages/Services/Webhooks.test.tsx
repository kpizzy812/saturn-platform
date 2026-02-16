import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';

// Mock Toast
const mockAddToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({ addToast: mockAddToast }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { WebhooksTab } from '@/pages/Services/Webhooks';
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

// Note: usePage mock from test-utils returns no webhooks/availableEvents,
// so the component shows its empty state. This is expected.

describe('Service Webhooks Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Webhooks heading', () => {
        render(<WebhooksTab service={mockService} />);
        expect(screen.getByText('Webhooks')).toBeInTheDocument();
    });

    it('renders description', () => {
        render(<WebhooksTab service={mockService} />);
        expect(screen.getByText('Configure webhooks to receive notifications about service events')).toBeInTheDocument();
    });

    it('renders Add Webhook button in header', () => {
        render(<WebhooksTab service={mockService} />);
        const buttons = screen.getAllByText('Add Webhook');
        expect(buttons.length).toBeGreaterThanOrEqual(1);
    });

    it('shows empty state with no webhooks configured', () => {
        render(<WebhooksTab service={mockService} />);
        expect(screen.getByText('No webhooks configured')).toBeInTheDocument();
    });

    it('shows empty state description', () => {
        render(<WebhooksTab service={mockService} />);
        expect(screen.getByText('Add a webhook to receive notifications')).toBeInTheDocument();
    });

    it('renders Add Webhook button in empty state', () => {
        render(<WebhooksTab service={mockService} />);
        // Both header and empty state have Add Webhook buttons
        const addButtons = screen.getAllByText('Add Webhook');
        expect(addButtons.length).toBe(2);
    });

    it('has correct structure with header card', () => {
        render(<WebhooksTab service={mockService} />);
        expect(screen.getByText('Webhooks')).toBeInTheDocument();
        expect(screen.getByText('No webhooks configured')).toBeInTheDocument();
    });
});
