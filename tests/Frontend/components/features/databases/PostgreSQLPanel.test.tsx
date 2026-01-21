import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../../utils/test-utils';
import { PostgreSQLPanel } from '@/components/features/databases/PostgreSQLPanel';
import type { StandaloneDatabase } from '@/types';

// Mock clipboard API
const writeTextMock = vi.fn().mockResolvedValue(undefined);

// Delete existing clipboard if it exists and create new one
delete (navigator as any).clipboard;
Object.defineProperty(navigator, 'clipboard', {
    value: {
        writeText: writeTextMock,
    },
    writable: true,
    configurable: true,
});

// Create a spy for the clipboard API (use the mock function directly)
const clipboardSpy = writeTextMock;

// Mock database data
const mockDatabase: StandaloneDatabase = {
    id: 1,
    uuid: 'db-1',
    name: 'test-postgres',
    type: 'postgresql',
    description: 'Test PostgreSQL database',
    environment_id: 1,
    destination_id: 1,
    status: 'running',
    created_at: '2024-01-01',
    updated_at: '2024-01-01',
};

describe('PostgreSQLPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render tabs', () => {
            render(<PostgreSQLPanel database={mockDatabase} />);

            expect(screen.getByText('Overview')).toBeInTheDocument();
            expect(screen.getByText('Extensions')).toBeInTheDocument();
            expect(screen.getByText('Users')).toBeInTheDocument();
            expect(screen.getByText('Settings')).toBeInTheDocument();
            expect(screen.getByText('Logs')).toBeInTheDocument();
        });

        it('should render Overview tab by default', () => {
            render(<PostgreSQLPanel database={mockDatabase} />);

            expect(screen.getByText('Connection String')).toBeInTheDocument();
            expect(screen.getByText('Database Statistics')).toBeInTheDocument();
            expect(screen.getByText('Connection Details')).toBeInTheDocument();
        });
    });

    describe('Overview tab', () => {
        it('should display database statistics', () => {
            render(<PostgreSQLPanel database={mockDatabase} />);

            expect(screen.getByText('Active Connections')).toBeInTheDocument();
            expect(screen.getByText('Database Size')).toBeInTheDocument();
            expect(screen.getByText('Queries/sec')).toBeInTheDocument();
            expect(screen.getByText('Cache Hit Ratio')).toBeInTheDocument();
        });

        it('should display connection details', () => {
            render(<PostgreSQLPanel database={mockDatabase} />);

            expect(screen.getByText('Host')).toBeInTheDocument();
            expect(screen.getByText('Port')).toBeInTheDocument();
            expect(screen.getByText('Database')).toBeInTheDocument();
            expect(screen.getByText('Username')).toBeInTheDocument();
            expect(screen.getByText('Password')).toBeInTheDocument();
        });

        it('should toggle password visibility', async () => {
            const { user, container } = render(<PostgreSQLPanel database={mockDatabase} />);

            // Password should be hidden initially
            expect(screen.getByText('••••••••••••••••••••')).toBeInTheDocument();

            // Find the password field container and get the eye button
            const passwordLabel = screen.getByText('Password');
            const passwordContainer = passwordLabel.parentElement;
            const buttons = passwordContainer?.querySelectorAll('button') || [];
            // First button should be the eye/eye-off toggle
            const eyeButton = buttons[0];

            if (eyeButton) {
                await user.click(eyeButton as HTMLElement);
            }

            // Password should now be visible
            await waitFor(() => {
                expect(screen.queryByText('••••••••••••••••••••')).not.toBeInTheDocument();
            });
        });

        it('should show copied confirmation message', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            const copyButtons = screen.getAllByRole('button');
            const copyButton = copyButtons[0];
            await user.click(copyButton);

            await waitFor(() => {
                expect(screen.getByText('Copied to clipboard!')).toBeInTheDocument();
            });
        });
    });

    describe('Extensions tab', () => {
        it('should display PostgreSQL extensions', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            // Click Extensions tab
            await user.click(screen.getByText('Extensions'));

            await waitFor(() => {
                expect(screen.getByText('PostgreSQL Extensions')).toBeInTheDocument();
                expect(screen.getByText('pg_stat_statements')).toBeInTheDocument();
                expect(screen.getByText('pgcrypto')).toBeInTheDocument();
                expect(screen.getByText('uuid-ossp')).toBeInTheDocument();
                expect(screen.getByText('hstore')).toBeInTheDocument();
                expect(screen.getByText('pg_trgm')).toBeInTheDocument();
                expect(screen.getByText('postgis')).toBeInTheDocument();
            });
        });

        it('should show extension status badges', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Extensions'));

            await waitFor(() => {
                const enabledBadges = screen.getAllByText('Enabled');
                const disabledBadges = screen.getAllByText('Disabled');

                expect(enabledBadges.length).toBeGreaterThan(0);
                expect(disabledBadges.length).toBeGreaterThan(0);
            });
        });

        it('should have refresh button', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Extensions'));

            await waitFor(() => {
                expect(screen.getByText('Refresh')).toBeInTheDocument();
            });
        });
    });

    describe('Users tab', () => {
        it('should display database users', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Users'));

            await waitFor(() => {
                expect(screen.getByText('Database Users')).toBeInTheDocument();
                expect(screen.getByText('postgres')).toBeInTheDocument();
                expect(screen.getByText('app_user')).toBeInTheDocument();
                expect(screen.getByText('readonly')).toBeInTheDocument();
            });
        });

        it('should show user roles', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Users'));

            await waitFor(() => {
                expect(screen.getByText('Superuser')).toBeInTheDocument();
                expect(screen.getByText('Standard')).toBeInTheDocument();
                expect(screen.getByText('Read-only')).toBeInTheDocument();
            });
        });

        it('should have create user button', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Users'));

            await waitFor(() => {
                expect(screen.getByText('Create User')).toBeInTheDocument();
            });
        });

        it('should not show delete button for postgres user', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Users'));

            await waitFor(() => {
                const postgresRow = screen.getByText('postgres').closest('div');
                expect(postgresRow?.querySelector('[data-icon="trash-2"]')).not.toBeInTheDocument();
            });
        });
    });

    describe('Settings tab', () => {
        it('should display maintenance options', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Maintenance')).toBeInTheDocument();
                expect(screen.getByText('VACUUM Database')).toBeInTheDocument();
                expect(screen.getByText('ANALYZE Database')).toBeInTheDocument();
            });
        });

        it('should display connection pooling settings', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Connection Pooling')).toBeInTheDocument();
                expect(screen.getByText('Max Connections')).toBeInTheDocument();
                expect(screen.getByText('Pool Size')).toBeInTheDocument();
                expect(screen.getByText('Idle Timeout')).toBeInTheDocument();
            });
        });

        it('should have run VACUUM button', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Run VACUUM')).toBeInTheDocument();
            });
        });

        it('should have run ANALYZE button', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Settings'));

            await waitFor(() => {
                expect(screen.getByText('Run ANALYZE')).toBeInTheDocument();
            });
        });
    });

    describe('Logs tab', () => {
        it('should display recent logs', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Logs'));

            await waitFor(() => {
                expect(screen.getByText('Recent Logs')).toBeInTheDocument();
            });
        });

        it('should show log levels', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Logs'));

            await waitFor(() => {
                expect(screen.getAllByText('INFO').length).toBeGreaterThan(0);
                expect(screen.getByText('WARNING')).toBeInTheDocument();
            });
        });

        it('should have refresh button in logs', async () => {
            const { user } = render(<PostgreSQLPanel database={mockDatabase} />);

            await user.click(screen.getByText('Logs'));

            await waitFor(() => {
                expect(screen.getByText('Refresh')).toBeInTheDocument();
            });
        });
    });
});
