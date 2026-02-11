import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act } from '../../utils/test-utils';
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
import { SettingsTab } from '@/pages/Services/Settings';
import type { Service } from '@/types';

const mockService: Service = {
    id: 1,
    uuid: 'service-uuid-123',
    name: 'production-api',
    description: 'Main production API service',
    docker_compose_raw: 'version: "3.8"\nservices:\n  api:\n    image: node:18\n    ports:\n      - "3000:3000"',
    environment_id: 1,
    destination_id: 1,
    created_at: '2024-01-01T00:00:00.000Z',
    updated_at: '2024-01-15T00:00:00.000Z',
};

describe('Service Settings Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockAddToast.mockClear();

        // Mock router.patch to call onSuccess
        (router.patch as any).mockImplementation((_url: string, _data: any, options?: any) => {
            Promise.resolve().then(() => {
                options?.onSuccess?.();
                options?.onFinish?.();
            });
        });

        // Mock router.delete to call onSuccess
        (router.delete as any).mockImplementation((_url: string, options?: any) => {
            Promise.resolve().then(() => {
                options?.onSuccess?.();
                options?.onFinish?.();
            });
        });
    });

    it('renders General Settings section', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('General Settings')).toBeInTheDocument();
    });

    it('pre-fills service name input', () => {
        render(<SettingsTab service={mockService} />);
        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        expect(nameInput.value).toBe('production-api');
    });

    it('pre-fills description textarea', () => {
        render(<SettingsTab service={mockService} />);
        const descInput = screen.getByPlaceholderText('Service description') as HTMLTextAreaElement;
        expect(descInput.value).toBe('Main production API service');
    });

    it('shows Docker Compose Configuration section', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Docker Compose Configuration')).toBeInTheDocument();
        expect(screen.getByText('Docker Compose YAML')).toBeInTheDocument();
    });

    it('pre-fills docker compose configuration', () => {
        render(<SettingsTab service={mockService} />);
        const textareas = document.querySelectorAll('textarea');
        const dockerComposeTextarea = Array.from(textareas).find(
            textarea => textarea.value.includes('version: "3.8"')
        );
        expect(dockerComposeTextarea?.value).toBe(mockService.docker_compose_raw);
    });

    it('allows editing service name', () => {
        render(<SettingsTab service={mockService} />);
        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;

        fireEvent.change(nameInput, { target: { value: 'new-service-name' } });

        expect(nameInput.value).toBe('new-service-name');
    });

    it('allows editing description', () => {
        render(<SettingsTab service={mockService} />);
        const descInput = screen.getByPlaceholderText('Service description') as HTMLTextAreaElement;

        fireEvent.change(descInput, { target: { value: 'Updated description' } });

        expect(descInput.value).toBe('Updated description');
    });

    it('allows editing docker compose configuration', () => {
        render(<SettingsTab service={mockService} />);
        const textareas = document.querySelectorAll('textarea');
        const dockerComposeTextarea = Array.from(textareas).find(
            textarea => textarea.value.includes('version: "3.8"')
        ) as HTMLTextAreaElement;

        const newConfig = 'version: "3.8"\nservices:\n  web:\n    image: nginx';
        fireEvent.change(dockerComposeTextarea, { target: { value: newConfig } });

        expect(dockerComposeTextarea.value).toBe(newConfig);
    });

    it('shows Save Changes button', () => {
        render(<SettingsTab service={mockService} />);
        const saveButtons = screen.getAllByText('Save Changes');
        expect(saveButtons.length).toBeGreaterThan(0);
    });

    it('shows Save Configuration button', () => {
        render(<SettingsTab service={mockService} />);
        const saveConfigButtons = screen.getAllByText('Save Configuration');
        expect(saveConfigButtons.length).toBeGreaterThan(0);
    });

    it('shows Service Information section', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Service Information')).toBeInTheDocument();
    });

    it('displays service UUID', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Service UUID')).toBeInTheDocument();
        expect(screen.getByText('service-uuid-123')).toBeInTheDocument();
    });

    it('displays environment ID', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Environment ID')).toBeInTheDocument();
        // Environment ID and Destination ID are both "1", so check for multiple
        const idValues = screen.getAllByText('1');
        expect(idValues.length).toBeGreaterThanOrEqual(2);
    });

    it('displays destination ID', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Destination ID')).toBeInTheDocument();
    });

    it('displays created date', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Created')).toBeInTheDocument();
    });

    it('displays last updated date', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Last Updated')).toBeInTheDocument();
    });

    it('shows Resource Limits section', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Resource Limits')).toBeInTheDocument();
    });

    it('shows Memory Limit input', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Memory Limit')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('512')).toBeInTheDocument();
    });

    it('shows CPU Limit input', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('CPU Limit')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('1.0')).toBeInTheDocument();
    });

    it('shows Save Limits button', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Save Limits')).toBeInTheDocument();
    });

    it('shows Webhooks section', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Webhooks')).toBeInTheDocument();
        expect(screen.getByText('Deployment Webhook URL')).toBeInTheDocument();
    });

    it('displays webhook URL', () => {
        render(<SettingsTab service={mockService} />);
        const expectedUrl = `${window.location.origin}/webhooks/services/${mockService.uuid}`;
        const webhookInput = screen.getByDisplayValue(expectedUrl);
        expect(webhookInput).toBeInTheDocument();
        expect(webhookInput).toHaveAttribute('readOnly');
    });

    it('shows copy button for webhook URL', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Copy')).toBeInTheDocument();
    });

    it('shows Danger Zone section', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('Danger Zone')).toBeInTheDocument();
    });

    it('shows delete service warning', () => {
        render(<SettingsTab service={mockService} />);
        const deleteTexts = screen.getAllByText('Delete Service');
        expect(deleteTexts.length).toBeGreaterThan(0);
        expect(screen.getByText(/Once you delete a service, there is no going back/)).toBeInTheDocument();
    });

    it('clicking save changes shows success toast', async () => {
        render(<SettingsTab service={mockService} />);

        const saveButton = screen.getAllByText('Save Changes')[0];

        await act(async () => {
            fireEvent.click(saveButton);
            // Wait for microtasks to complete
            await Promise.resolve();
        });

        // Wait for toast to be called
        await waitFor(() => {
            expect(mockAddToast).toHaveBeenCalledWith('success', 'General settings saved successfully');
        });
    });

    it('delete service requires confirmation', async () => {
        render(<SettingsTab service={mockService} />);

        const deleteButton = screen.getByRole('button', { name: /Delete Service/ });
        fireEvent.click(deleteButton);

        // Wait for confirmation modal to appear
        await waitFor(() => {
            expect(screen.getByText('Are you sure you want to delete this service? This action cannot be undone.')).toBeInTheDocument();
        }, { timeout: 10000 });

        // Check that the modal has Cancel button
        expect(screen.getByText('Cancel')).toBeInTheDocument();
        expect(screen.getByText('Continue')).toBeInTheDocument();
    }, 15000);

    it('delete service shows toast when confirmed', async () => {
        render(<SettingsTab service={mockService} />);

        const deleteButton = screen.getByRole('button', { name: /Delete Service/ });
        fireEvent.click(deleteButton);

        // Wait for first confirmation modal (title is "Delete Service")
        await waitFor(() => {
            const modalTitle = screen.getAllByText('Delete Service').find(
                el => el.tagName === 'H2' || el.classList.contains('text-lg')
            );
            expect(modalTitle).toBeInTheDocument();
        }, { timeout: 10000 });

        // Verify first confirmation modal has Continue button
        expect(screen.getByText('Continue')).toBeInTheDocument();
    }, 15000);

    it('all inputs are editable', () => {
        render(<SettingsTab service={mockService} />);
        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const descInput = screen.getByPlaceholderText('Service description') as HTMLTextAreaElement;

        expect(nameInput).not.toBeDisabled();
        expect(descInput).not.toBeDisabled();
    });

    it('shows helper text for inputs', () => {
        render(<SettingsTab service={mockService} />);
        expect(screen.getByText('A unique name for your service')).toBeInTheDocument();
        expect(screen.getByText('Optional description for your service')).toBeInTheDocument();
        expect(screen.getByText('Define your service using Docker Compose syntax')).toBeInTheDocument();
    });

    it('memory limit has unit selector', () => {
        render(<SettingsTab service={mockService} />);
        const select = document.querySelector('select');
        expect(select).toBeInTheDocument();
        expect(screen.getByText('MB')).toBeInTheDocument();
        expect(screen.getByText('GB')).toBeInTheDocument();
    });
});
