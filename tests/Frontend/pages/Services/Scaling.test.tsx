import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import { router } from '@inertiajs/react';

// Mock Toast
const mockAddToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        addToast: mockAddToast,
    }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Import after mocks
import { ScalingTab } from '@/pages/Services/Scaling';
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
    limits_memory: '512m',
    limits_memory_swap: '1g',
    limits_memory_swappiness: 60,
    limits_memory_reservation: '256m',
    limits_cpus: '1.0',
    limits_cpu_shares: 1024,
    limits_cpuset: '0,1',
};

describe('Service Scaling Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders Resource Limits info alert', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText('Resource Limits')).toBeInTheDocument();
        expect(screen.getByText(/Configure CPU and memory limits for all containers/)).toBeInTheDocument();
    });

    it('renders Memory Limits section', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText('Memory Limits')).toBeInTheDocument();
    });

    it('shows Configured badge when memory limit is set', () => {
        render(<ScalingTab service={mockService} />);
        const badges = screen.getAllByText('Configured');
        expect(badges.length).toBeGreaterThan(0);
    });

    it('displays Memory Limit slider', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText(/Memory Limit:/)).toBeInTheDocument();
        const sliders = document.querySelectorAll('input[type="range"]');
        expect(sliders.length).toBeGreaterThan(0);
    });

    it('displays Memory Reservation slider', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText(/Memory Reservation \(soft limit\):/)).toBeInTheDocument();
    });

    it('displays Swap Limit slider', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText(/Swap Limit:/)).toBeInTheDocument();
    });

    it('displays Swappiness slider', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText(/Swappiness:/)).toBeInTheDocument();
    });

    it('renders CPU Limits section', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText('CPU Limits')).toBeInTheDocument();
    });

    it('displays CPU Limit slider', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText(/CPU Limit:/)).toBeInTheDocument();
    });

    it('displays CPU Shares slider', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText(/CPU Shares \(relative weight\):/)).toBeInTheDocument();
        expect(screen.getByText(/Default is 1024/)).toBeInTheDocument();
    });

    it('displays CPU Set input', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText('CPU Set (pin to specific CPUs)')).toBeInTheDocument();
        const cpuSetInput = screen.getByPlaceholderText('e.g., 0,1 or 0-3');
        expect(cpuSetInput).toBeInTheDocument();
    });

    it('shows Save Changes button', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText('Save Changes')).toBeInTheDocument();
    });

    it('save button is disabled when no changes', () => {
        render(<ScalingTab service={mockService} />);
        const saveButton = screen.getByText('Save Changes');
        expect(saveButton).toBeDisabled();
    });

    it('shows "All changes saved" when no changes', () => {
        render(<ScalingTab service={mockService} />);
        expect(screen.getByText('All changes saved')).toBeInTheDocument();
    });

    it('allows changing memory limit', () => {
        render(<ScalingTab service={mockService} />);
        const sliders = document.querySelectorAll('input[type="range"]');
        const memorySlider = sliders[0] as HTMLInputElement;

        fireEvent.change(memorySlider, { target: { value: '1024' } });

        expect(screen.getByText('You have unsaved changes')).toBeInTheDocument();
    });

    it('enables save button when changes are made', () => {
        render(<ScalingTab service={mockService} />);
        const sliders = document.querySelectorAll('input[type="range"]');
        const memorySlider = sliders[0] as HTMLInputElement;

        fireEvent.change(memorySlider, { target: { value: '1024' } });

        const saveButton = screen.getByText('Save Changes');
        expect(saveButton).not.toBeDisabled();
    });

    it('allows changing CPU limit', () => {
        render(<ScalingTab service={mockService} />);
        const sliders = document.querySelectorAll('input[type="range"]');
        // CPU limit slider is at a later index
        const cpuSlider = Array.from(sliders).find(slider =>
            (slider as HTMLInputElement).max === '4000'
        ) as HTMLInputElement;

        if (cpuSlider) {
            fireEvent.change(cpuSlider, { target: { value: '2000' } });
            expect(screen.getByText('You have unsaved changes')).toBeInTheDocument();
        }
    });

    it('allows editing CPU Set input', () => {
        render(<ScalingTab service={mockService} />);
        const cpuSetInput = screen.getByPlaceholderText('e.g., 0,1 or 0-3') as HTMLInputElement;

        fireEvent.change(cpuSetInput, { target: { value: '0-3' } });

        expect(cpuSetInput.value).toBe('0-3');
    });

    it('saves changes when button is clicked', async () => {
        render(<ScalingTab service={mockService} />);

        const sliders = document.querySelectorAll('input[type="range"]');
        const memorySlider = sliders[0] as HTMLInputElement;
        fireEvent.change(memorySlider, { target: { value: '1024' } });

        const saveButton = screen.getByText('Save Changes');
        fireEvent.click(saveButton);

        await waitFor(() => {
            expect(router.patch).toHaveBeenCalledWith(
                expect.stringContaining(`/api/v1/services/${mockService.uuid}`),
                expect.any(Object),
                expect.any(Object)
            );
        });
    });

    it('shows warning when no limits are set', () => {
        const serviceWithoutLimits = {
            ...mockService,
            limits_memory: '0',
            limits_cpus: '0',
            limits_memory_reservation: '0',
        };

        render(<ScalingTab service={serviceWithoutLimits} />);

        expect(screen.getByText('No resource limits configured')).toBeInTheDocument();
        expect(screen.getByText(/Without limits, containers can consume all available server resources/)).toBeInTheDocument();
    });

    it('displays helper text for sliders', () => {
        render(<ScalingTab service={mockService} />);

        expect(screen.getByText(/Soft limit. Docker will try to keep container memory below this value/)).toBeInTheDocument();
        expect(screen.getByText(/Higher values give more CPU time when system is under load/)).toBeInTheDocument();
    });

    it('displays hint text for CPU Set', () => {
        render(<ScalingTab service={mockService} />);

        expect(screen.getByText(/Leave empty to use all available CPUs/)).toBeInTheDocument();
    });

    it('formats memory values correctly in labels', () => {
        render(<ScalingTab service={mockService} />);

        // Check that memory is displayed in appropriate units (MB or GB)
        const bodyText = document.body.textContent || '';
        expect(bodyText).toMatch(/\d+\.?\d* (MB|GB)/);
    });

    it('formats CPU values correctly in labels', () => {
        render(<ScalingTab service={mockService} />);

        // Check that CPU is displayed correctly
        const bodyText = document.body.textContent || '';
        expect(bodyText).toMatch(/\d+\.?\d* CPU/);
    });
});
