import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { Input } from '@/components/ui/Input';

describe('Input Component', () => {
    it('renders with label', () => {
        render(<Input label="Email" />);
        expect(screen.getByLabelText('Email')).toBeInTheDocument();
    });

    it('renders without label', () => {
        render(<Input placeholder="Enter text" />);
        expect(screen.getByPlaceholderText('Enter text')).toBeInTheDocument();
    });

    it('shows error message', () => {
        render(<Input error="This field is required" />);
        expect(screen.getByText('This field is required')).toBeInTheDocument();
    });

    it('shows hint when no error', () => {
        render(<Input hint="Enter your email address" />);
        expect(screen.getByText('Enter your email address')).toBeInTheDocument();
    });

    it('hides hint when error is present', () => {
        render(<Input hint="Enter email" error="Invalid email" />);
        expect(screen.queryByText('Enter email')).not.toBeInTheDocument();
        expect(screen.getByText('Invalid email')).toBeInTheDocument();
    });

    it('handles user input', async () => {
        const handleChange = vi.fn();
        const { user } = render(<Input onChange={handleChange} />);

        await user.type(screen.getByRole('textbox'), 'hello');
        expect(handleChange).toHaveBeenCalled();
    });

    it('can be disabled', () => {
        render(<Input disabled />);
        expect(screen.getByRole('textbox')).toBeDisabled();
    });

    it('applies error styling when error is present', () => {
        render(<Input error="Error" />);
        expect(screen.getByRole('textbox')).toHaveClass('border-danger');
    });
});
