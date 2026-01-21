import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { Button } from '@/components/ui/Button';

describe('Button Component', () => {
    it('renders with default variant', () => {
        render(<Button>Click me</Button>);
        const button = screen.getByRole('button', { name: /click me/i });
        expect(button).toBeInTheDocument();
        expect(button).toHaveClass('bg-primary');
    });

    it('renders with secondary variant', () => {
        render(<Button variant="secondary">Secondary</Button>);
        const button = screen.getByRole('button');
        // Secondary variant uses semi-transparent background for glassmorphism
        expect(button).toHaveClass('bg-background-secondary/80');
    });

    it('renders with danger variant', () => {
        render(<Button variant="danger">Delete</Button>);
        const button = screen.getByRole('button');
        expect(button).toHaveClass('bg-danger');
    });

    it('renders with different sizes', () => {
        const { rerender } = render(<Button size="sm">Small</Button>);
        expect(screen.getByRole('button')).toHaveClass('h-8');

        rerender(<Button size="lg">Large</Button>);
        expect(screen.getByRole('button')).toHaveClass('h-12');
    });

    it('handles click events', async () => {
        const handleClick = vi.fn();
        const { user } = render(<Button onClick={handleClick}>Click</Button>);

        await user.click(screen.getByRole('button'));
        expect(handleClick).toHaveBeenCalledTimes(1);
    });

    it('can be disabled', () => {
        render(<Button disabled>Disabled</Button>);
        const button = screen.getByRole('button');
        expect(button).toBeDisabled();
    });

    it('shows loading state', () => {
        render(<Button loading>Loading</Button>);
        const button = screen.getByRole('button');
        expect(button).toBeDisabled();
        expect(button.querySelector('svg')).toBeInTheDocument();
    });

    it('does not trigger click when loading', async () => {
        const handleClick = vi.fn();
        const { user } = render(<Button loading onClick={handleClick}>Loading</Button>);

        await user.click(screen.getByRole('button'));
        expect(handleClick).not.toHaveBeenCalled();
    });
});
