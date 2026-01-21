import { describe, it, expect } from 'vitest';
import { render } from '../../utils/test-utils';
import { Spinner } from '@/components/ui/Spinner';

describe('Spinner Component', () => {
    it('renders with default size', () => {
        const { container } = render(<Spinner />);
        const svg = container.querySelector('svg');
        expect(svg).toBeInTheDocument();
        expect(svg).toHaveClass('h-6', 'w-6');
    });

    it('renders with small size', () => {
        const { container } = render(<Spinner size="sm" />);
        const svg = container.querySelector('svg');
        expect(svg).toHaveClass('h-4', 'w-4');
    });

    it('renders with large size', () => {
        const { container } = render(<Spinner size="lg" />);
        const svg = container.querySelector('svg');
        expect(svg).toHaveClass('h-8', 'w-8');
    });

    it('has animation class', () => {
        const { container } = render(<Spinner />);
        const svg = container.querySelector('svg');
        expect(svg).toHaveClass('animate-spin');
    });
});
