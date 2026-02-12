import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';

// Mock Toast
const mockAddToast = vi.fn();
vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        addToast: mockAddToast,
    }),
    ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Mock useConfirm
const mockConfirm = vi.fn(() => Promise.resolve(true));
vi.mock('@/components/ui/ConfirmationModal', () => ({
    useConfirm: () => mockConfirm,
    ConfirmationProvider: ({ children }: { children: React.ReactNode }) => children,
}));

// Import after mocks
import { VariablesTab } from '@/pages/Services/Variables';
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

const mockVariables = [
    { id: 1, key: 'DATABASE_URL', value: 'postgresql://localhost/db', isSecret: true },
    { id: 2, key: 'API_KEY', value: 'secret-key-123', isSecret: true },
    { id: 3, key: 'NODE_ENV', value: 'production', isSecret: false },
];

describe('Service Variables Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockConfirm.mockResolvedValue(true);
    });

    it('renders Environment Variables heading', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        expect(screen.getByText('Environment Variables')).toBeInTheDocument();
    });

    it('shows description for environment variables', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        expect(screen.getByText('Manage environment variables for your service')).toBeInTheDocument();
    });

    it('displays Add Variable button', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        const addButtons = screen.getAllByText('Add Variable');
        expect(addButtons.length).toBeGreaterThan(0);
    });

    it('displays Bulk Edit button', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        expect(screen.getByText('Bulk Edit')).toBeInTheDocument();
    });

    it('displays all variable keys', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        expect(screen.getByText('DATABASE_URL')).toBeInTheDocument();
        expect(screen.getByText('API_KEY')).toBeInTheDocument();
        expect(screen.getByText('NODE_ENV')).toBeInTheDocument();
    });

    it('shows Secret badge for secret variables', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        const secretBadges = screen.getAllByText('Secret');
        // DATABASE_URL and API_KEY are secrets
        expect(secretBadges.length).toBe(2);
    });

    it('displays non-secret values in plain text', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        expect(screen.getByText('production')).toBeInTheDocument();
    });

    it('hides secret values by default', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        const hiddenValues = document.querySelectorAll('code');
        const hasHiddenSecret = Array.from(hiddenValues).some(
            code => code.textContent?.includes('••••••••••••••••')
        );
        expect(hasHiddenSecret).toBe(true);
    });

    it('shows eye icon button for secret variables', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        const eyeButtons = document.querySelectorAll('button[title*="value"]');
        // Should have show/hide buttons for secret variables
        expect(eyeButtons.length).toBeGreaterThan(0);
    });

    it('toggles secret value visibility when eye button is clicked', async () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        // Find show value button
        const showButton = document.querySelector('button[title="Show value"]');
        expect(showButton).toBeInTheDocument();

        // Click to show value
        fireEvent.click(showButton!);

        await waitFor(() => {
            const hideButton = document.querySelector('button[title="Hide value"]');
            expect(hideButton).toBeInTheDocument();
        });
    });

    it('shows copy button for each variable', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        const copyButtons = document.querySelectorAll('button[title="Copy value"]');
        expect(copyButtons.length).toBe(mockVariables.length);
    });

    it('copies value to clipboard when copy button is clicked', async () => {
        const writeTextSpy = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator.clipboard, 'writeText', {
            writable: true,
            configurable: true,
            value: writeTextSpy,
        });

        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const copyButtons = document.querySelectorAll('button[title="Copy value"]');
        fireEvent.click(copyButtons[0]);

        await waitFor(() => {
            expect(writeTextSpy).toHaveBeenCalledWith('postgresql://localhost/db');
        });
    });

    it('shows edit button for each variable', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        const editButtons = document.querySelectorAll('button[title="Edit variable"]');
        expect(editButtons.length).toBe(mockVariables.length);
    });

    it('shows delete button for each variable', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);
        const deleteButtons = document.querySelectorAll('button[title="Delete variable"]');
        expect(deleteButtons.length).toBe(mockVariables.length);
    });

    it('opens add modal when Add Variable button is clicked', async () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const addButton = screen.getAllByText('Add Variable')[0];
        fireEvent.click(addButton);

        await waitFor(() => {
            // Modal should show placeholder inputs
            expect(screen.getByPlaceholderText('DATABASE_URL')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('postgresql://...')).toBeInTheDocument();
        });
    });

    it('opens edit modal when edit button is clicked', async () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const editButton = document.querySelectorAll('button[title="Edit variable"]')[0];
        fireEvent.click(editButton);

        await waitFor(() => {
            expect(screen.getByText('Edit Variable')).toBeInTheDocument();
        });
    });

    it('shows confirmation dialog when delete button is clicked', async () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const deleteButton = document.querySelectorAll('button[title="Delete variable"]')[0];
        fireEvent.click(deleteButton);

        await waitFor(() => {
            expect(mockConfirm).toHaveBeenCalledWith(
                expect.objectContaining({
                    title: 'Delete Variable',
                    description: 'Are you sure you want to delete this variable? This action cannot be undone.',
                })
            );
        });
    });

    it('shows bulk edit mode when Bulk Edit is clicked', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const bulkEditButton = screen.getByText('Bulk Edit');
        fireEvent.click(bulkEditButton);

        expect(screen.getByText('Bulk Edit Variables')).toBeInTheDocument();
        expect(screen.getByText('Exit Bulk Edit')).toBeInTheDocument();
    });

    it('displays textarea with variables in bulk edit mode', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const bulkEditButton = screen.getByText('Bulk Edit');
        fireEvent.click(bulkEditButton);

        const textarea = document.querySelector('textarea');
        expect(textarea).toBeInTheDocument();
        expect(textarea?.value).toContain('DATABASE_URL=postgresql://localhost/db');
        expect(textarea?.value).toContain('API_KEY=secret-key-123');
        expect(textarea?.value).toContain('NODE_ENV=production');
    });

    it('shows Cancel and Save Changes buttons in bulk edit mode', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const bulkEditButton = screen.getByText('Bulk Edit');
        fireEvent.click(bulkEditButton);

        expect(screen.getByText('Cancel')).toBeInTheDocument();
        expect(screen.getByText('Save Changes')).toBeInTheDocument();
    });

    it('exits bulk edit mode when Cancel is clicked', () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const bulkEditButton = screen.getByText('Bulk Edit');
        fireEvent.click(bulkEditButton);

        const cancelButton = screen.getByText('Cancel');
        fireEvent.click(cancelButton);

        expect(screen.queryByText('Bulk Edit Variables')).not.toBeInTheDocument();
        expect(screen.getByText('Bulk Edit')).toBeInTheDocument();
    });

    it('shows empty state when no variables exist', () => {
        render(<VariablesTab service={mockService} variables={[]} />);

        expect(screen.getByText('No variables')).toBeInTheDocument();
        expect(screen.getByText('Add your first environment variable')).toBeInTheDocument();
    });

    it('shows Add Variable button in empty state', () => {
        render(<VariablesTab service={mockService} variables={[]} />);

        const addButtons = screen.getAllByText('Add Variable');
        // Should have button in header and in empty state
        expect(addButtons.length).toBeGreaterThan(1);
    });

    it('add modal has required form fields', async () => {
        render(<VariablesTab service={mockService} variables={[]} />);

        const addButton = screen.getAllByText('Add Variable')[0];
        fireEvent.click(addButton);

        await waitFor(() => {
            expect(screen.getByText('Key')).toBeInTheDocument();
            expect(screen.getByText('Value')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('DATABASE_URL')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('postgresql://...')).toBeInTheDocument();
        });
    });

    it('add modal has secret checkbox', async () => {
        render(<VariablesTab service={mockService} variables={[]} />);

        const addButton = screen.getAllByText('Add Variable')[0];
        fireEvent.click(addButton);

        await waitFor(() => {
            expect(screen.getByText('Mark as secret (hide value by default)')).toBeInTheDocument();
            const checkbox = document.getElementById('is-secret');
            expect(checkbox).toBeInTheDocument();
        });
    });

    it('add modal has Cancel and Add Variable buttons', async () => {
        render(<VariablesTab service={mockService} variables={[]} />);

        const addButton = screen.getAllByText('Add Variable')[0];
        fireEvent.click(addButton);

        await waitFor(() => {
            expect(screen.getByText('Cancel')).toBeInTheDocument();
            // Modal submit button shows "Add Variable"
            const submitButtons = screen.getAllByText('Add Variable');
            expect(submitButtons.length).toBeGreaterThan(1);
        });
    });

    it('closes add modal when Cancel is clicked', async () => {
        render(<VariablesTab service={mockService} variables={[]} />);

        const addButton = screen.getAllByText('Add Variable')[0];
        fireEvent.click(addButton);

        await waitFor(() => {
            expect(screen.getByPlaceholderText('DATABASE_URL')).toBeInTheDocument();
        });

        const cancelButton = screen.getByText('Cancel');
        fireEvent.click(cancelButton);

        await waitFor(() => {
            expect(screen.queryByPlaceholderText('DATABASE_URL')).not.toBeInTheDocument();
        });
    });

    it('allows filling out variable form', async () => {
        render(<VariablesTab service={mockService} variables={[]} />);

        const addButton = screen.getAllByText('Add Variable')[0];
        fireEvent.click(addButton);

        await waitFor(() => {
            const keyInput = screen.getByPlaceholderText('DATABASE_URL') as HTMLInputElement;
            const valueInput = screen.getByPlaceholderText('postgresql://...') as HTMLTextAreaElement;

            fireEvent.change(keyInput, { target: { value: 'NEW_VAR' } });
            fireEvent.change(valueInput, { target: { value: 'new-value' } });

            expect(keyInput.value).toBe('NEW_VAR');
            expect(valueInput.value).toBe('new-value');
        });
    });

    it('edit modal shows Save Changes button', async () => {
        render(<VariablesTab service={mockService} variables={mockVariables} />);

        const editButton = document.querySelectorAll('button[title="Edit variable"]')[0];
        fireEvent.click(editButton);

        await waitFor(() => {
            expect(screen.getByText('Save Changes')).toBeInTheDocument();
        });
    });
});
