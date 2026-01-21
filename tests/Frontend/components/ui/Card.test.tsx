import { describe, it, expect } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/Card';

describe('Card Component', () => {
    it('renders card with content', () => {
        render(<Card>Card content</Card>);
        expect(screen.getByText('Card content')).toBeInTheDocument();
    });

    it('renders with all sub-components', () => {
        render(
            <Card>
                <CardHeader>
                    <CardTitle>Title</CardTitle>
                    <CardDescription>Description</CardDescription>
                </CardHeader>
                <CardContent>Content</CardContent>
                <CardFooter>Footer</CardFooter>
            </Card>
        );

        expect(screen.getByText('Title')).toBeInTheDocument();
        expect(screen.getByText('Description')).toBeInTheDocument();
        expect(screen.getByText('Content')).toBeInTheDocument();
        expect(screen.getByText('Footer')).toBeInTheDocument();
    });

    it('applies custom className', () => {
        render(<Card className="custom-class" data-testid="card">Content</Card>);
        expect(screen.getByTestId('card')).toHaveClass('custom-class');
    });

    it('CardTitle renders as h3', () => {
        render(<CardTitle>Test Title</CardTitle>);
        expect(screen.getByRole('heading', { level: 3 })).toBeInTheDocument();
    });
});
