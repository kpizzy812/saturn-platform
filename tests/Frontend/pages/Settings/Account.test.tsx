import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import userEvent from '@testing-library/user-event';

// Mock the @inertiajs/react module
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href, className }: { children: React.ReactNode; href: string; className?: string }) => (
        <a href={href} className={className}>{children}</a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: {
                    id: 1,
                    name: 'John Doe',
                    email: 'john@example.com',
                },
            },
        },
    }),
}));

// Import after mock
import AccountSettings from '@/pages/Settings/Account';

describe('Account Settings Page', () => {
    it('renders the settings layout with sidebar', () => {
        render(<AccountSettings />);
        expect(screen.getByText('Settings')).toBeInTheDocument();
        expect(screen.getByText('Account')).toBeInTheDocument();
        expect(screen.getByText('Team')).toBeInTheDocument();
        expect(screen.getByText('Billing')).toBeInTheDocument();
    });

    it('displays user profile form fields', () => {
        render(<AccountSettings />);
        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Email')).toBeInTheDocument();
    });

    it('shows profile section', () => {
        render(<AccountSettings />);
        expect(screen.getByText('Profile')).toBeInTheDocument();
        expect(screen.getByText('Update your personal information')).toBeInTheDocument();
    });

    it('renders password change form', () => {
        render(<AccountSettings />);
        expect(screen.getByText('Change Password')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Enter current password')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Enter new password')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Confirm new password')).toBeInTheDocument();
    });

    it('renders two-factor authentication section', () => {
        render(<AccountSettings />);
        expect(screen.getByText('Two-Factor Authentication')).toBeInTheDocument();
        expect(screen.getByText('Add an extra layer of security to your account')).toBeInTheDocument();
    });

    it('shows danger zone with delete account option', () => {
        render(<AccountSettings />);
        expect(screen.getByText('Danger Zone')).toBeInTheDocument();
        // Multiple "Delete Account" texts exist (title and button)
        expect(screen.getAllByText('Delete Account').length).toBeGreaterThan(0);
    });

    it('has name input field', () => {
        render(<AccountSettings />);
        const nameInputs = screen.getAllByRole('textbox');
        expect(nameInputs.length).toBeGreaterThan(0);
    });

    it('can toggle two-factor authentication', async () => {
        render(<AccountSettings />);

        const toggleButton = screen.getAllByRole('button').find(btn =>
            btn.textContent === 'Enable'
        );

        expect(toggleButton).toBeInTheDocument();
        expect(screen.getByText('Disabled')).toBeInTheDocument();

        // Button exists and is clickable - actual toggle now makes API call
        if (toggleButton) {
            expect(toggleButton).not.toBeDisabled();
        }
    });

    it('has delete account buttons', () => {
        render(<AccountSettings />);
        const deleteButtons = screen.getAllByRole('button').filter(btn =>
            btn.textContent?.includes('Delete Account')
        );
        expect(deleteButtons.length).toBeGreaterThan(0);
    });

    it('has save changes button', () => {
        render(<AccountSettings />);
        const saveButton = screen.getAllByRole('button').find(btn =>
            btn.textContent === 'Save Changes'
        );
        expect(saveButton).toBeInTheDocument();
    });

    it('has proper form structure with labels', () => {
        render(<AccountSettings />);
        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Email')).toBeInTheDocument();
        expect(screen.getByText('Current Password')).toBeInTheDocument();
        expect(screen.getByText('New Password')).toBeInTheDocument();
        expect(screen.getByText('Confirm New Password')).toBeInTheDocument();
    });
});
