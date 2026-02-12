import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';

// Import after mock setup
import EnvironmentSecrets from '@/pages/Environments/Secrets';

// Mock clipboard API
Object.assign(navigator, {
    clipboard: {
        writeText: vi.fn().mockResolvedValue(undefined),
    },
});

const mockEnvironment = {
    id: 1,
    uuid: 'env-123',
    name: 'production',
    project: {
        name: 'Test Project',
    },
};

const mockSecrets = [
    {
        id: '1',
        key: 'JWT_SECRET',
        value: 'super-secret-jwt-key-12345',
        rotationSchedule: 'monthly' as const,
        lastRotated: '2024-01-15T00:00:00Z',
        createdAt: '2024-01-01T00:00:00Z',
        createdBy: 'admin@example.com',
    },
    {
        id: '2',
        key: 'STRIPE_SECRET_KEY',
        value: 'sk_live_abcdef1234567890',
        rotationSchedule: 'quarterly' as const,
        externalReference: {
            provider: 'aws-secrets-manager' as const,
            path: '/production/stripe/secret',
        },
        lastRotated: '2024-02-01T00:00:00Z',
        createdAt: '2024-01-05T00:00:00Z',
        createdBy: 'developer@example.com',
    },
    {
        id: '3',
        key: 'DATABASE_PASSWORD',
        value: 'db-password-xyz-9876',
        rotationSchedule: 'weekly' as const,
        lastRotated: '2024-02-10T00:00:00Z',
        createdAt: '2024-01-10T00:00:00Z',
        createdBy: 'admin@example.com',
    },
];

const mockAuditLogs = [
    {
        id: '1',
        secretKey: 'JWT_SECRET',
        action: 'viewed' as const,
        user: 'admin@example.com',
        timestamp: '2024-02-12T10:00:00Z',
        ipAddress: '192.168.1.100',
    },
    {
        id: '2',
        secretKey: 'STRIPE_SECRET_KEY',
        action: 'rotated' as const,
        user: 'developer@example.com',
        timestamp: '2024-02-11T15:30:00Z',
        ipAddress: '192.168.1.101',
    },
    {
        id: '3',
        secretKey: 'DATABASE_PASSWORD',
        action: 'created' as const,
        user: 'admin@example.com',
        timestamp: '2024-01-10T09:00:00Z',
        ipAddress: '192.168.1.100',
    },
];

describe('Environment Secrets Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders the page title and description', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );
            expect(screen.getByText('Secrets Management')).toBeInTheDocument();
            expect(
                screen.getByText('Securely manage sensitive credentials for production')
            ).toBeInTheDocument();
        });

        it('renders action buttons', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );
            expect(screen.getByText('Audit Log')).toBeInTheDocument();
            expect(screen.getByText('Add Secret')).toBeInTheDocument();
        });

        it('displays security notice', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );
            expect(screen.getByText('Security Best Practices')).toBeInTheDocument();
            expect(
                screen.getByText(/Secrets are always encrypted at rest/)
            ).toBeInTheDocument();
        });
    });

    describe('Secrets List', () => {
        it('displays all secrets', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            expect(screen.getByText('JWT_SECRET')).toBeInTheDocument();
            expect(screen.getByText('STRIPE_SECRET_KEY')).toBeInTheDocument();
            expect(screen.getByText('DATABASE_PASSWORD')).toBeInTheDocument();
        });

        it('masks secret values by default', () => {
            const { container } = render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            // All secrets should be masked (shown as 32 dots)
            const maskedSecrets = container.querySelectorAll('code');
            const hasMaskedValue = Array.from(maskedSecrets).some(el =>
                el.textContent?.includes('••••••••••••••••••••••••••••••••')
            );
            expect(hasMaskedValue).toBe(true);
        });

        it('toggles secret visibility', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            // Click eye icon to reveal value
            const eyeButtons = screen.getAllByTitle(/show secret|hide secret/i);
            fireEvent.click(eyeButtons[0]);

            // Value should be visible
            expect(screen.getByText('super-secret-jwt-key-12345')).toBeInTheDocument();
        });

        it('displays rotation schedule badges', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            expect(screen.getByText('monthly')).toBeInTheDocument();
            expect(screen.getByText('quarterly')).toBeInTheDocument();
            expect(screen.getByText('weekly')).toBeInTheDocument();
        });

        it('displays external reference badges', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            expect(screen.getByText('aws-secrets-manager')).toBeInTheDocument();
            expect(screen.getByText('/production/stripe/secret')).toBeInTheDocument();
        });

        it('shows last rotated date', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            expect(screen.getAllByText(/Last rotated:/i).length).toBeGreaterThan(0);
        });

        it('shows created by information', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            expect(screen.getAllByText(/Created by:/i).length).toBeGreaterThan(0);
            // The email addresses appear in the metadata
            expect(screen.getAllByText(/admin@example.com/i).length).toBeGreaterThan(0);
            expect(screen.getAllByText(/developer@example.com/i).length).toBeGreaterThan(0);
        });
    });

    describe('Secret Actions', () => {
        it('copies secret value to clipboard', async () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const copyButtons = screen.getAllByTitle('Copy secret');
            fireEvent.click(copyButtons[0]);

            // Just check that the "Copied!" message appears
            await waitFor(() => {
                expect(screen.getByText('Copied!')).toBeInTheDocument();
            });
        });

        it('opens rotate modal', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const rotateButtons = screen.getAllByTitle('Rotate secret');
            fireEvent.click(rotateButtons[0]);

            // Modal title appears (multiple matches because of breadcrumbs)
            expect(screen.getAllByText('Rotate Secret').length).toBeGreaterThan(0);
            expect(screen.getByText(/Update the value for JWT_SECRET/)).toBeInTheDocument();
        });

        it('rotates a secret', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const rotateButtons = screen.getAllByTitle('Rotate secret');
            fireEvent.click(rotateButtons[0]);

            // Find the confirm button in the modal (not the action buttons)
            const allRotateButtons = screen.getAllByRole('button', { name: /rotate secret/i });
            const confirmButton = allRotateButtons.find(btn =>
                btn.textContent?.includes('Rotate Secret')
            );
            fireEvent.click(confirmButton!);

            // Modal should close
            expect(screen.queryByText('Update the value for JWT_SECRET')).not.toBeInTheDocument();
        });
    });

    describe('Add Secret Modal', () => {
        it('opens add secret modal', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const addButton = screen.getByText('Add Secret');
            fireEvent.click(addButton);

            expect(screen.getByText('Add New Secret')).toBeInTheDocument();
            expect(screen.getByText('Secrets are encrypted and access is logged')).toBeInTheDocument();
        });

        it('renders add secret form fields', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const addButton = screen.getByText('Add Secret');
            fireEvent.click(addButton);

            expect(screen.getByLabelText('Secret Key')).toBeInTheDocument();
            expect(screen.getByLabelText('Secret Value')).toBeInTheDocument();
            expect(screen.getByText('Rotation Schedule')).toBeInTheDocument();
        });

        it('has rotation schedule options', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const addButton = screen.getByText('Add Secret');
            fireEvent.click(addButton);

            expect(screen.getByText('Never')).toBeInTheDocument();
            expect(screen.getByText('Weekly')).toBeInTheDocument();
            expect(screen.getByText('Monthly')).toBeInTheDocument();
            expect(screen.getByText('Quarterly')).toBeInTheDocument();
        });

        it('adds a new secret', async () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={[]}
                    auditLogs={[]}
                />
            );

            // Open modal - get the header button
            const addButtons = screen.getAllByText('Add Secret');
            fireEvent.click(addButtons[0]);

            // Fill form
            const keyInput = screen.getByPlaceholderText('API_SECRET_KEY');
            const valueInput = screen.getByPlaceholderText('Enter the secret value...');

            fireEvent.change(keyInput, { target: { value: 'NEW_SECRET' } });
            fireEvent.change(valueInput, { target: { value: 'secret-value-xyz' } });

            // Submit - find button in modal dialog
            const submitButtons = screen.getAllByRole('button');
            const submitButton = submitButtons.find(btn =>
                btn.textContent?.includes('Add Secret') && btn.closest('[role="dialog"]')
            );
            fireEvent.click(submitButton!);

            // Secret should be added (wait for state update)
            await waitFor(() => {
                expect(screen.getByText('NEW_SECRET')).toBeInTheDocument();
            });
        });

        it('disables submit when fields are empty', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const addButtons = screen.getAllByText('Add Secret');
            fireEvent.click(addButtons[0]);

            // Find the modal submit button (should be disabled by default when fields are empty)
            const submitButtons = screen.getAllByRole('button');
            const submitButton = submitButtons.find(btn =>
                btn.textContent?.includes('Add Secret') && btn.closest('[role="dialog"]')
            );
            expect(submitButton).toBeDisabled();
        });
    });

    describe('Audit Log', () => {
        it('displays recent activity section', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            expect(screen.getByText('Recent Activity')).toBeInTheDocument();
        });

        it('expands recent activity', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const activityButton = screen.getByText('Recent Activity').closest('button');
            fireEvent.click(activityButton!);

            // Should show audit logs
            expect(screen.getAllByText(/viewed|rotated|created/).length).toBeGreaterThan(0);
        });

        it('opens full audit log modal', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const auditLogButton = screen.getByText('Audit Log');
            fireEvent.click(auditLogButton);

            expect(screen.getByText('Secret Access Audit Log')).toBeInTheDocument();
            expect(
                screen.getByText('Complete history of secret access and modifications')
            ).toBeInTheDocument();
        });

        it('displays audit log entries with details', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const activityButton = screen.getByText('Recent Activity').closest('button');
            fireEvent.click(activityButton!);

            // IP addresses appear in audit log
            expect(screen.getAllByText(/192.168.1.100/i).length).toBeGreaterThan(0);
            expect(screen.getAllByText(/192.168.1.101/i).length).toBeGreaterThan(0);
        });
    });

    describe('Empty State', () => {
        it('displays empty state when no secrets', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={[]}
                    auditLogs={[]}
                />
            );

            expect(screen.getByText('No secrets yet')).toBeInTheDocument();
            expect(screen.getByText('Add your first secret to get started.')).toBeInTheDocument();
        });

        it('shows add secret button in empty state', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={[]}
                    auditLogs={[]}
                />
            );

            const addButtons = screen.getAllByText('Add Secret');
            expect(addButtons.length).toBeGreaterThan(0);
        });
    });

    describe('Security Warning', () => {
        it('shows warning in rotate modal', () => {
            render(
                <EnvironmentSecrets
                    environment={mockEnvironment}
                    secrets={mockSecrets}
                    auditLogs={mockAuditLogs}
                />
            );

            const rotateButtons = screen.getAllByTitle('Rotate secret');
            fireEvent.click(rotateButtons[0]);

            expect(
                screen.getByText(
                    /Rotating this secret will update its value. Make sure to update all applications using this secret./
                )
            ).toBeInTheDocument();
        });
    });
});
