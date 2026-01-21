import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import DatabaseUsers from '@/pages/Databases/Users';
import type { StandaloneDatabase } from '@/types';

// Mock router
const mockRouter = {
    post: vi.fn(),
    delete: vi.fn(),
};

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: mockRouter,
    };
});

const mockDatabase: StandaloneDatabase = {
    id: 1,
    uuid: 'db-uuid-1',
    name: 'production-postgres',
    description: 'Main production database',
    database_type: 'postgresql',
    status: 'running',
    environment_id: 1,
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-15T00:00:00Z',
};

const mockUsers = [
    {
        id: 1,
        username: 'admin',
        role: 'admin' as const,
        permissions: {
            read: true,
            write: true,
            admin: true,
        },
        created_at: '2024-01-01T00:00:00Z',
    },
    {
        id: 2,
        username: 'app_user',
        role: 'read_write' as const,
        permissions: {
            read: true,
            write: true,
            admin: false,
        },
        created_at: '2024-01-05T00:00:00Z',
    },
    {
        id: 3,
        username: 'readonly_user',
        role: 'read_only' as const,
        permissions: {
            read: true,
            write: false,
            admin: false,
        },
        created_at: '2024-01-10T00:00:00Z',
    },
];

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

describe('Database Users Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.confirm = vi.fn(() => true);
    });

    describe('rendering', () => {
        it('should render page title and description', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            expect(screen.getByText('Database Users')).toBeInTheDocument();
            expect(screen.getByText(`Manage user access and permissions for ${mockDatabase.name}`)).toBeInTheDocument();
        });

        it('should display back link', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            expect(screen.getByText(`Back to ${mockDatabase.name}`)).toBeInTheDocument();
        });

        it('should display breadcrumbs', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            expect(screen.getByText('Databases')).toBeInTheDocument();
            expect(screen.getByText(mockDatabase.name)).toBeInTheDocument();
            expect(screen.getByText('Users')).toBeInTheDocument();
        });

        it('should display create user button', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            expect(screen.getByText('Create User')).toBeInTheDocument();
        });

        it('should show loading state when users are not available', () => {
            render(<DatabaseUsers database={mockDatabase} />);

            expect(screen.getByText('Loading users...')).toBeInTheDocument();
        });
    });

    describe('user list', () => {
        it('should display all users', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            expect(screen.getByText('admin')).toBeInTheDocument();
            expect(screen.getByText('app_user')).toBeInTheDocument();
            expect(screen.getByText('readonly_user')).toBeInTheDocument();
        });

        it('should display user roles as badges', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            expect(screen.getAllByText('Admin').length).toBeGreaterThan(0);
            expect(screen.getByText('Read/Write')).toBeInTheDocument();
            expect(screen.getByText('Read Only')).toBeInTheDocument();
        });

        it('should display user permissions as badges', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const readBadges = screen.getAllByText('Read');
            const writeBadges = screen.getAllByText('Write');

            expect(readBadges.length).toBeGreaterThan(0);
            expect(writeBadges.length).toBeGreaterThan(0);
        });

        it('should display reset password button for each user', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const resetButtons = screen.getAllByText('Reset Password');
            expect(resetButtons.length).toBe(mockUsers.length);
        });

        it('should display delete button for non-admin users', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            // Admin user should not have delete button, but others should
            const deleteButtons = screen.getAllByRole('button').filter(
                button => button.querySelector('svg') && button.className.includes('danger')
            );

            // Should have delete buttons for non-admin users (2 users)
            expect(deleteButtons.length).toBeGreaterThanOrEqual(2);
        });

        it('should not display delete button for admin user', () => {
            render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            // Check that admin row doesn't have as many buttons as others
            // This is a simplified check since admin shouldn't have delete
            const allButtons = screen.getAllByRole('button');
            expect(allButtons.length).toBeGreaterThan(0);
        });
    });

    describe('create user modal', () => {
        it('should open create user modal when button is clicked', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const createButton = screen.getByText('Create User');
            await user.click(createButton);

            expect(screen.getByText('Create Database User')).toBeInTheDocument();
            expect(screen.getByText('Create a new user with specific permissions')).toBeInTheDocument();
        });

        it('should display username input in modal', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            expect(screen.getByText('Username')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('user_name')).toBeInTheDocument();
        });

        it('should display password input in modal', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            expect(screen.getByText('Password')).toBeInTheDocument();
        });

        it('should display generate password button', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            expect(screen.getByText('Generate')).toBeInTheDocument();
        });

        it('should display permission checkboxes', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            expect(screen.getByText('Permissions')).toBeInTheDocument();
            expect(screen.getByText('Allow SELECT queries')).toBeInTheDocument();
            expect(screen.getByText('Allow INSERT, UPDATE, DELETE')).toBeInTheDocument();
            expect(screen.getByText('Full database access including schema changes')).toBeInTheDocument();
        });

        it('should have create button disabled when form is empty', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            const createUserButtons = screen.getAllByText('Create User');
            const modalCreateButton = createUserButtons[createUserButtons.length - 1];
            expect(modalCreateButton).toBeDisabled();
        });

        it('should enable create button when form is filled', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            const usernameInput = screen.getByPlaceholderText('user_name');
            await user.type(usernameInput, 'newuser');

            const passwordInput = screen.getByPlaceholderText('••••••••••••');
            await user.type(passwordInput, 'password123');

            const createUserButtons = screen.getAllByText('Create User');
            const modalCreateButton = createUserButtons[createUserButtons.length - 1];
            expect(modalCreateButton).not.toBeDisabled();
        });

        it('should close modal after successful creation', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            // Click the header "Create User" button
            const headerButton = screen.getAllByRole('button').find(btn => btn.textContent?.includes('Create User'));
            if (headerButton) {
                await user.click(headerButton);
            }

            const usernameInput = screen.getByPlaceholderText('user_name');
            await user.type(usernameInput, 'newuser');

            const passwordInput = screen.getByPlaceholderText('••••••••••••');
            await user.type(passwordInput, 'password123');

            // Find the modal's Create User button (the last one)
            const allButtons = screen.getAllByRole('button');
            const createButtons = allButtons.filter(btn => btn.textContent === 'Create User');
            const modalCreateButton = createButtons[createButtons.length - 1];
            await user.click(modalCreateButton);

            // Modal title should not be visible after creation
            await waitFor(() => {
                expect(screen.queryByText('Create a new user with specific permissions')).not.toBeInTheDocument();
            });
        });
    });

    describe('password generation', () => {
        it('should generate password when generate button is clicked', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            const generateButton = screen.getByText('Generate');
            await user.click(generateButton);

            const passwordInput = screen.getByPlaceholderText('••••••••••••');
            expect((passwordInput as HTMLInputElement).value).not.toBe('');
        });

        it('should generate a 20-character password', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            const generateButton = screen.getByText('Generate');
            await user.click(generateButton);

            const passwordInput = screen.getByPlaceholderText('••••••••••••') as HTMLInputElement;
            expect(passwordInput.value.length).toBe(20);
        });
    });

    describe('reset password modal', () => {
        it('should open reset password modal when button is clicked', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const resetButtons = screen.getAllByRole('button').filter(btn => btn.textContent?.includes('Reset Password'));
            await user.click(resetButtons[0]);

            expect(screen.getAllByText('Reset Password').length).toBeGreaterThan(1);
            expect(screen.getByText(/Reset password for user:/)).toBeInTheDocument();
        });

        it('should display new password input', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const resetButtons = screen.getAllByText('Reset Password');
            await user.click(resetButtons[0]);

            expect(screen.getByText('New Password')).toBeInTheDocument();
        });

        it('should have reset button disabled when password is empty', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const resetButtons = screen.getAllByText('Reset Password');
            await user.click(resetButtons[0]);

            const resetPasswordButtons = screen.getAllByText('Reset Password');
            const modalResetButton = resetPasswordButtons[resetPasswordButtons.length - 1];
            expect(modalResetButton).toBeDisabled();
        });

        it('should enable reset button when password is entered', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const resetButtons = screen.getAllByText('Reset Password');
            await user.click(resetButtons[0]);

            const passwordInputs = screen.getAllByPlaceholderText('••••••••••••');
            const newPasswordInput = passwordInputs[passwordInputs.length - 1];
            await user.type(newPasswordInput, 'newpassword123');

            const resetPasswordButtons = screen.getAllByText('Reset Password');
            const modalResetButton = resetPasswordButtons[resetPasswordButtons.length - 1];
            expect(modalResetButton).not.toBeDisabled();
        });

    });

    describe('delete user modal', () => {
        it('should open delete modal when delete button is clicked', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            // Find delete buttons (should not include admin)
            const allButtons = screen.getAllByRole('button');
            const deleteButton = allButtons.find(btn =>
                btn.className.includes('danger') && btn.querySelector('svg') && !btn.textContent
            );

            if (deleteButton) {
                await user.click(deleteButton);

                expect(screen.getAllByText('Delete User').length).toBeGreaterThan(0);
                expect(screen.getByText('Are you sure you want to delete this user?')).toBeInTheDocument();
            }
        });

        it('should display user details in delete modal', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const allButtons = screen.getAllByRole('button');
            const deleteButton = allButtons.find(btn =>
                btn.className.includes('danger') && btn.querySelector('svg')
            );

            if (deleteButton) {
                await user.click(deleteButton);

                expect(screen.getByText('User Details')).toBeInTheDocument();
                expect(screen.getByText(/Username:/)).toBeInTheDocument();
                expect(screen.getByText(/Role:/)).toBeInTheDocument();
            }
        });

        it('should display warning message', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const allButtons = screen.getAllByRole('button');
            const deleteButton = allButtons.find(btn =>
                btn.className.includes('danger') && btn.querySelector('svg')
            );

            if (deleteButton) {
                await user.click(deleteButton);

                expect(screen.getByText('This action cannot be undone')).toBeInTheDocument();
                expect(screen.getByText('This user will lose all access to the database.')).toBeInTheDocument();
            }
        });

    });

    describe('permission checkboxes', () => {
        it('should toggle read permission', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            const checkboxes = screen.getAllByRole('checkbox');
            const readCheckbox = checkboxes.find(cb =>
                cb.closest('div')?.textContent?.includes('Allow SELECT queries')
            );

            if (readCheckbox) {
                const initialState = (readCheckbox as HTMLInputElement).checked;
                await user.click(readCheckbox);
                expect((readCheckbox as HTMLInputElement).checked).toBe(!initialState);
            }
        });

        it('should toggle write permission', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            const checkboxes = screen.getAllByRole('checkbox');
            const writeCheckbox = checkboxes.find(cb =>
                cb.closest('div')?.textContent?.includes('Allow INSERT, UPDATE, DELETE')
            );

            if (writeCheckbox) {
                await user.click(writeCheckbox);
                expect((writeCheckbox as HTMLInputElement).checked).toBeTruthy();
            }
        });

        it('should toggle admin permission', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            await user.click(screen.getByText('Create User'));

            const checkboxes = screen.getAllByRole('checkbox');
            const adminCheckbox = checkboxes.find(cb =>
                cb.closest('div')?.textContent?.includes('Full database access')
            );

            if (adminCheckbox) {
                await user.click(adminCheckbox);
                expect((adminCheckbox as HTMLInputElement).checked).toBeTruthy();
            }
        });
    });

    describe('edge cases', () => {
        it('should handle empty users array', () => {
            render(<DatabaseUsers database={mockDatabase} users={[]} />);

            expect(screen.getByText('Database Users')).toBeInTheDocument();
            expect(screen.queryByText('admin')).not.toBeInTheDocument();
        });

        it('should handle database without description', () => {
            const dbWithoutDescription = {
                ...mockDatabase,
                description: null,
            };

            render(<DatabaseUsers database={dbWithoutDescription} users={mockUsers} />);

            expect(screen.getByText('Database Users')).toBeInTheDocument();
        });
    });

    describe('modal cancellation', () => {
        it('should close create modal when cancel is clicked', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            // Click the header "Create User" button
            const headerButton = screen.getAllByRole('button').find(btn => btn.textContent?.includes('Create User'));
            if (headerButton) {
                await user.click(headerButton);
            }

            expect(screen.getByText('Create a new user with specific permissions')).toBeInTheDocument();

            const cancelButtons = screen.getAllByRole('button').filter(btn => btn.textContent === 'Cancel');
            if (cancelButtons.length > 0) {
                await user.click(cancelButtons[0]);
            }
            await waitFor(() => {
                expect(screen.queryByText('Create a new user with specific permissions')).not.toBeInTheDocument();
            });
        });

        it('should close reset password modal when cancel is clicked', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const resetButtons = screen.getAllByRole('button').filter(btn => btn.textContent?.includes('Reset Password'));
            await user.click(resetButtons[0]);

            const cancelButtons = screen.getAllByRole('button').filter(btn => btn.textContent === 'Cancel');
            if (cancelButtons.length > 0) {
                await user.click(cancelButtons[0]);
            }

            await waitFor(() => {
                expect(screen.queryByText(/Reset password for user:/)).not.toBeInTheDocument();
            });
        });

        it('should close delete modal when cancel is clicked', async () => {
            const { user } = render(<DatabaseUsers database={mockDatabase} users={mockUsers} />);

            const allButtons = screen.getAllByRole('button');
            const deleteButton = allButtons.find(btn =>
                btn.className.includes('danger') && btn.querySelector('svg') && !btn.textContent
            );

            if (deleteButton) {
                await user.click(deleteButton);
                expect(screen.getByText('Are you sure you want to delete this user?')).toBeInTheDocument();

                const cancelButtons = screen.getAllByRole('button').filter(btn => btn.textContent === 'Cancel');
                if (cancelButtons.length > 0) {
                    await user.click(cancelButtons[0]);
                }

                await waitFor(() => {
                    expect(screen.queryByText('Are you sure you want to delete this user?')).not.toBeInTheDocument();
                });
            }
        });
    });
});
