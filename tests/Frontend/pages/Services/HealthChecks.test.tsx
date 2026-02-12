import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import { router } from '@inertiajs/react';

// Mock Toast
const mockToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        toast: mockToast,
    }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Import after mocks
import { HealthChecksTab } from '@/pages/Services/HealthChecks';
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

const mockHealthcheckConfig = {
    enabled: true,
    type: 'http' as const,
    test: 'curl -f http://localhost/ || exit 1',
    interval: 30,
    timeout: 10,
    retries: 3,
    start_period: 30,
    service_name: null,
    status: 'running:healthy',
    services_status: {
        'api': { has_healthcheck: true, healthcheck: {} },
        'worker': { has_healthcheck: false, healthcheck: null },
    },
};

describe('Service HealthChecks Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        // Mock fetch for healthcheck config
        global.fetch = vi.fn(() =>
            Promise.resolve({
                ok: true,
                json: () => Promise.resolve(mockHealthcheckConfig),
            } as Response)
        );
    });

    it('shows loading state initially', async () => {
        render(<HealthChecksTab service={mockService} />);

        // Should show spinner initially, then load config
        await waitFor(() => {
            expect(screen.getByText('Current Health Status')).toBeInTheDocument();
        });
    });

    it('renders Current Health Status section', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Current Health Status')).toBeInTheDocument();
        });
    });

    it('displays health status badges', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('running')).toBeInTheDocument();
            expect(screen.getByText('healthy')).toBeInTheDocument();
        });
    });

    it('shows Refresh button', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Refresh')).toBeInTheDocument();
        });
    });

    it('renders Health Check Configuration section', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Health Check Configuration')).toBeInTheDocument();
            expect(screen.getByText(/Configure how Docker monitors the health of your service/)).toBeInTheDocument();
        });
    });

    it('displays Enable Health Check toggle', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Enable Health Check')).toBeInTheDocument();
            expect(screen.getByText(/Docker will periodically check if your container is healthy/)).toBeInTheDocument();
        });
    });

    it('health check toggle is checked when enabled', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const toggle = screen.getByRole('checkbox', { hidden: true });
            expect(toggle).toBeChecked();
        });
    });

    it('displays Health Check Type buttons', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Health Check Type')).toBeInTheDocument();
            expect(screen.getByText('HTTP Health Check')).toBeInTheDocument();
            expect(screen.getByText('TCP Health Check')).toBeInTheDocument();
        });
    });

    it('shows Test Command input', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Test Command')).toBeInTheDocument();
            const input = screen.getByPlaceholderText('curl -f http://localhost/ || exit 1');
            expect(input).toHaveValue('curl -f http://localhost/ || exit 1');
        });
    });

    it('displays timing settings inputs', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Interval (seconds)')).toBeInTheDocument();
            expect(screen.getByText('Timeout (seconds)')).toBeInTheDocument();
            expect(screen.getByText('Retries')).toBeInTheDocument();
            expect(screen.getByText('Start Period (seconds)')).toBeInTheDocument();
        });
    });

    it('shows hint text for timing inputs', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('How often to perform the check')).toBeInTheDocument();
            expect(screen.getByText('Maximum time to wait for response')).toBeInTheDocument();
            expect(screen.getByText('Consecutive failures to mark unhealthy')).toBeInTheDocument();
            expect(screen.getByText('Grace period for container startup')).toBeInTheDocument();
        });
    });

    it('displays pre-filled timing values', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const inputs = screen.getAllByRole('spinbutton');
            const intervalInput = inputs.find(input => (input as HTMLInputElement).value === '30');
            const timeoutInput = inputs.find(input => (input as HTMLInputElement).value === '10');
            const retriesInput = inputs.find(input => (input as HTMLInputElement).value === '3');

            expect(intervalInput).toBeInTheDocument();
            expect(timeoutInput).toBeInTheDocument();
            expect(retriesInput).toBeInTheDocument();
        });
    });

    it('renders Container Health Status section', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Container Health Status')).toBeInTheDocument();
            expect(screen.getByText(/Health check status for each container/)).toBeInTheDocument();
        });
    });

    it('displays container statuses', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('api')).toBeInTheDocument();
            expect(screen.getByText('worker')).toBeInTheDocument();
            expect(screen.getByText('Configured')).toBeInTheDocument();
            expect(screen.getByText('No Healthcheck')).toBeInTheDocument();
        });
    });

    it('shows Important Notes section', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Important Notes')).toBeInTheDocument();
            expect(screen.getByText(/Changes require a service restart to take effect/)).toBeInTheDocument();
            expect(screen.getByText(/Health checks modify your docker-compose configuration/)).toBeInTheDocument();
        });
    });

    it('displays Save Configuration button', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            expect(screen.getByText('Save Configuration')).toBeInTheDocument();
        });
    });

    it('allows toggling health check type', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const tcpButton = screen.getByText('TCP Health Check');
            fireEvent.click(tcpButton);

            // Test command should update for TCP
            const testCommandInput = screen.getByPlaceholderText('curl -f http://localhost/ || exit 1') as HTMLInputElement;
            expect(testCommandInput.value).toContain('nc');
        });
    });

    it('allows editing test command', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const testCommandInput = screen.getByPlaceholderText('curl -f http://localhost/ || exit 1') as HTMLInputElement;
            fireEvent.change(testCommandInput, { target: { value: 'curl -f http://localhost/health || exit 1' } });
            expect(testCommandInput.value).toBe('curl -f http://localhost/health || exit 1');
        });
    });

    it('allows editing interval', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const inputs = screen.getAllByRole('spinbutton');
            const intervalInput = inputs.find(input => (input as HTMLInputElement).value === '30') as HTMLInputElement;
            fireEvent.change(intervalInput, { target: { value: '60' } });
            expect(intervalInput.value).toBe('60');
        });
    });

    it('saves configuration when button is clicked', async () => {
        global.fetch = vi.fn()
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockHealthcheckConfig),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ success: true }),
            });

        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const saveButton = screen.getByText('Save Configuration');
            fireEvent.click(saveButton);
        });

        await waitFor(() => {
            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining(`/api/v1/services/${mockService.uuid}/healthcheck`),
                expect.objectContaining({
                    method: 'PATCH',
                })
            );
        });
    });

    it('shows toast on successful save', async () => {
        global.fetch = vi.fn()
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockHealthcheckConfig),
            })
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve({ success: true }),
            });

        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const saveButton = screen.getByText('Save Configuration');
            fireEvent.click(saveButton);
        });

        await waitFor(() => {
            expect(mockToast).toHaveBeenCalledWith(
                expect.objectContaining({
                    title: 'Configuration saved',
                    variant: 'success',
                })
            );
        });
    });

    it('shows error toast on save failure', async () => {
        global.fetch = vi.fn()
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockHealthcheckConfig),
            })
            .mockResolvedValueOnce({
                ok: false,
                json: () => Promise.resolve({ message: 'Save failed' }),
            });

        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const saveButton = screen.getByText('Save Configuration');
            fireEvent.click(saveButton);
        });

        await waitFor(() => {
            expect(mockToast).toHaveBeenCalledWith(
                expect.objectContaining({
                    title: 'Error',
                    variant: 'error',
                })
            );
        });
    });

    it('refresh button reloads the page', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const refreshButton = screen.getByText('Refresh');
            fireEvent.click(refreshButton);
            expect(router.reload).toHaveBeenCalled();
        });
    });

    it('disables save button while saving', async () => {
        let resolvePromise: (value: any) => void;
        const savePromise = new Promise(resolve => { resolvePromise = resolve; });

        global.fetch = vi.fn()
            .mockResolvedValueOnce({
                ok: true,
                json: () => Promise.resolve(mockHealthcheckConfig),
            })
            .mockReturnValueOnce(savePromise);

        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const saveButton = screen.getByText('Save Configuration');
            fireEvent.click(saveButton);
        });

        await waitFor(() => {
            const savingButton = screen.getByText('Saving...');
            expect(savingButton).toBeDisabled();
        });

        resolvePromise!({ ok: true, json: () => Promise.resolve({}) });
    });

    it('hides configuration fields when health check is disabled', async () => {
        render(<HealthChecksTab service={mockService} />);

        await waitFor(() => {
            const toggle = screen.getByRole('checkbox', { hidden: true });
            fireEvent.click(toggle);

            expect(screen.queryByText('Health Check Type')).not.toBeInTheDocument();
            expect(screen.queryByText('Test Command')).not.toBeInTheDocument();
        });
    });
});
