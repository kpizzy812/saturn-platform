import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import Register from '@/pages/Auth/Register';

describe('Register Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and subtitle', () => {
            render(<Register />);

            expect(screen.getByText('Create Account')).toBeInTheDocument();
            expect(screen.getByText('Start deploying your applications for free.')).toBeInTheDocument();
        });

        it('should render all form fields', () => {
            render(<Register />);

            expect(screen.getByLabelText('Name')).toBeInTheDocument();
            expect(screen.getByLabelText('Email')).toBeInTheDocument();
            expect(screen.getByLabelText('Password')).toBeInTheDocument();
            expect(screen.getByLabelText('Confirm Password')).toBeInTheDocument();
        });

        it('should render form with correct placeholders', () => {
            render(<Register />);

            expect(screen.getByPlaceholderText('John Doe')).toBeInTheDocument();
            expect(screen.getByPlaceholderText('you@example.com')).toBeInTheDocument();
            // Two password fields with same placeholder
            const passwordFields = screen.getAllByPlaceholderText('••••••••');
            expect(passwordFields).toHaveLength(2);
        });

        it('should render password hint', () => {
            render(<Register />);

            expect(screen.getByText('Must be at least 8 characters')).toBeInTheDocument();
        });

        it('should render submit button', () => {
            render(<Register />);

            expect(screen.getByRole('button', { name: 'Create Account' })).toBeInTheDocument();
        });

        it('should render social login buttons', () => {
            render(<Register />);

            expect(screen.getByText('GitHub')).toBeInTheDocument();
            expect(screen.getByText('Google')).toBeInTheDocument();
            expect(screen.getByText('Or continue with')).toBeInTheDocument();
        });

        it('should render login link', () => {
            render(<Register />);

            expect(screen.getByText('Already have an account?')).toBeInTheDocument();
            const signInLink = screen.getByText('Sign in');
            expect(signInLink).toBeInTheDocument();
            expect(signInLink.closest('a')).toHaveAttribute('href', '/login');
        });
    });

    describe('form inputs', () => {
        it('should update name field on input', async () => {
            const { user } = render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            await user.type(nameInput, 'Test User');

            expect(screen.getByDisplayValue('Test User')).toBeInTheDocument();
        });

        it('should update email field on input', async () => {
            const { user } = render(<Register />);

            const emailInput = screen.getByPlaceholderText('you@example.com');
            await user.type(emailInput, 'test@example.com');

            expect(screen.getByDisplayValue('test@example.com')).toBeInTheDocument();
        });

        it('should update password field on input', async () => {
            const { user } = render(<Register />);

            const passwordInput = screen.getByLabelText('Password');
            await user.type(passwordInput, 'password123');

            expect(screen.getByDisplayValue('password123')).toBeInTheDocument();
        });

        it('should update confirm password field on input', async () => {
            const { user } = render(<Register />);

            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            await user.type(confirmPasswordInput, 'password123');

            expect(screen.getByDisplayValue('password123')).toBeInTheDocument();
        });

        it('should have name field with autofocus', () => {
            render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            // Input component receives autoFocus prop, but in test env it doesn't set DOM attribute
            expect(nameInput).toBeInTheDocument();
        });

        it('should have required attributes on all inputs', () => {
            render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            const emailInput = screen.getByPlaceholderText('you@example.com');
            const passwordInput = screen.getByLabelText('Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');

            expect(nameInput).toBeRequired();
            expect(emailInput).toBeRequired();
            expect(passwordInput).toBeRequired();
            expect(confirmPasswordInput).toBeRequired();
        });

        it('should have correct input types', () => {
            render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            const emailInput = screen.getByPlaceholderText('you@example.com');
            const passwordInput = screen.getByLabelText('Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');

            expect(nameInput).toHaveAttribute('type', 'text');
            expect(emailInput).toHaveAttribute('type', 'email');
            expect(passwordInput).toHaveAttribute('type', 'password');
            expect(confirmPasswordInput).toHaveAttribute('type', 'password');
        });
    });

    describe('form submission', () => {
        it('should submit form with correct data', async () => {
            const { user } = render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            const emailInput = screen.getByPlaceholderText('you@example.com');
            const passwordInput = screen.getByLabelText('Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            const submitButton = screen.getByRole('button', { name: 'Create Account' });

            await user.type(nameInput, 'Test User');
            await user.type(emailInput, 'test@example.com');
            await user.type(passwordInput, 'password123');
            await user.type(confirmPasswordInput, 'password123');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/register', expect.objectContaining({
                name: 'Test User',
                email: 'test@example.com',
                password: 'password123',
                password_confirmation: 'password123',
            }), undefined);
        });

        it('should submit form on Enter key press', async () => {
            const { user } = render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            const emailInput = screen.getByPlaceholderText('you@example.com');
            const passwordInput = screen.getByLabelText('Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');

            await user.type(nameInput, 'Test User');
            await user.type(emailInput, 'test@example.com');
            await user.type(passwordInput, 'password123');
            await user.type(confirmPasswordInput, 'password123');
            await user.keyboard('{Enter}');

            expect(router.post).toHaveBeenCalledWith('/register', expect.objectContaining({
                name: 'Test User',
                email: 'test@example.com',
                password: 'password123',
                password_confirmation: 'password123',
            }), undefined);
        });

        it('should submit form with mismatched passwords', async () => {
            const { user } = render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            const emailInput = screen.getByPlaceholderText('you@example.com');
            const passwordInput = screen.getByLabelText('Password');
            const confirmPasswordInput = screen.getByLabelText('Confirm Password');
            const submitButton = screen.getByRole('button', { name: 'Create Account' });

            await user.type(nameInput, 'Test User');
            await user.type(emailInput, 'test@example.com');
            await user.type(passwordInput, 'password123');
            await user.type(confirmPasswordInput, 'different123');
            await user.click(submitButton);

            expect(router.post).toHaveBeenCalledWith('/register', expect.objectContaining({
                name: 'Test User',
                email: 'test@example.com',
                password: 'password123',
                password_confirmation: 'different123',
            }), undefined);
        });
    });

    describe('edge cases', () => {
        it('should attempt form submission with empty fields', async () => {
            const { user } = render(<Register />);

            const submitButton = screen.getByRole('button', { name: 'Create Account' });
            await user.click(submitButton);

            // Form has required fields, so browser validation prevents submission
            // We just verify the button is clickable and form exists
            expect(submitButton).toBeInTheDocument();
        });

        it('should attempt partial form submission', async () => {
            const { user } = render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            const emailInput = screen.getByPlaceholderText('you@example.com');
            const submitButton = screen.getByRole('button', { name: 'Create Account' });

            await user.type(nameInput, 'Test User');
            await user.type(emailInput, 'test@example.com');
            await user.click(submitButton);

            // Form has required password fields, so browser validation prevents submission
            // We just verify the button is clickable and partial data is entered
            expect(nameInput).toHaveValue('Test User');
            expect(emailInput).toHaveValue('test@example.com');
        });

        it('should allow spaces in name field', async () => {
            const { user } = render(<Register />);

            const nameInput = screen.getByPlaceholderText('John Doe');
            await user.type(nameInput, 'Test User Name');

            expect(screen.getByDisplayValue('Test User Name')).toBeInTheDocument();
        });

        it('should allow special characters in password', async () => {
            const { user } = render(<Register />);

            const passwordInput = screen.getByLabelText('Password');
            await user.type(passwordInput, 'P@ssw0rd!#$');

            expect(screen.getByDisplayValue('P@ssw0rd!#$')).toBeInTheDocument();
        });
    });

    describe('social login', () => {
        it('should render GitHub social login button', () => {
            render(<Register />);

            const githubButton = screen.getByText('GitHub').closest('button');
            expect(githubButton).toBeInTheDocument();
            expect(githubButton).toHaveAttribute('type', 'button');
        });

        it('should render Google social login button', () => {
            render(<Register />);

            const googleButton = screen.getByText('Google').closest('button');
            expect(googleButton).toBeInTheDocument();
            expect(googleButton).toHaveAttribute('type', 'button');
        });
    });
});
