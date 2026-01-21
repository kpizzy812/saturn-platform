import { describe, it, expect } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { Badge } from '@/components/ui/Badge';

describe('Badge Component', () => {
    it('renders with default variant', () => {
        render(<Badge>Default</Badge>);
        expect(screen.getByText('Default')).toBeInTheDocument();
    });

    it('renders with success variant', () => {
        render(<Badge variant="success">Success</Badge>);
        const badge = screen.getByText('Success');
        expect(badge).toHaveClass('text-success');
    });

    it('renders with danger variant', () => {
        render(<Badge variant="danger">Error</Badge>);
        const badge = screen.getByText('Error');
        expect(badge).toHaveClass('text-danger');
    });

    it('renders with warning variant', () => {
        render(<Badge variant="warning">Warning</Badge>);
        const badge = screen.getByText('Warning');
        expect(badge).toHaveClass('text-warning');
    });

    it('renders with info variant', () => {
        render(<Badge variant="info">Info</Badge>);
        const badge = screen.getByText('Info');
        expect(badge).toHaveClass('text-info');
    });
});
