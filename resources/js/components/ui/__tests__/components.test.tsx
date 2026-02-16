import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Button } from '../Button';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../Card';
import { Badge, StatusBadge } from '../Badge';
import { Input, Textarea } from '../Input';
import { SaturnLogo, SaturnBrand, SaturnIcon } from '../SaturnLogo';

describe('Saturn UI Components', () => {
    describe('Button Component', () => {
        describe('Variants', () => {
            it('renders with default variant', () => {
                render(<Button>Click me</Button>);
                const button = screen.getByRole('button', { name: /click me/i });
                expect(button).toBeInTheDocument();
                expect(button).toHaveClass('bg-primary');
                expect(button).toHaveClass('text-white');
            });

            it('renders with secondary variant', () => {
                render(<Button variant="secondary">Secondary</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('bg-background-secondary/80');
                expect(button).toHaveClass('text-foreground');
                expect(button).toHaveClass('backdrop-blur-sm');
            });

            it('renders with danger variant', () => {
                render(<Button variant="danger">Delete</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('bg-danger');
                expect(button).toHaveClass('text-white');
            });

            it('renders with success variant', () => {
                render(<Button variant="success">Confirm</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('bg-success');
                expect(button).toHaveClass('text-white');
            });

            it('renders with warning variant', () => {
                render(<Button variant="warning">Warning</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('bg-warning');
                expect(button).toHaveClass('text-white');
            });

            it('renders with ghost variant', () => {
                render(<Button variant="ghost">Ghost</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('text-foreground-muted');
            });

            it('renders with link variant', () => {
                render(<Button variant="link">Link</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('text-primary');
                expect(button).toHaveClass('underline-offset-4');
            });

            it('renders with outline variant', () => {
                render(<Button variant="outline">Outline</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('bg-transparent');
                expect(button).toHaveClass('text-foreground');
                expect(button).toHaveClass('border');
            });

            it('renders with premium variant', () => {
                render(<Button variant="premium">Premium</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('bg-gradient-to-r');
                expect(button).toHaveClass('from-primary');
                expect(button).toHaveClass('to-purple-500');
            });
        });

        describe('Sizes', () => {
            it('renders with default size', () => {
                render(<Button>Default</Button>);
                expect(screen.getByRole('button')).toHaveClass('h-10');
            });

            it('renders with sm size', () => {
                render(<Button size="sm">Small</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('h-8');
                expect(button).toHaveClass('px-3');
                expect(button).toHaveClass('text-sm');
            });

            it('renders with lg size', () => {
                render(<Button size="lg">Large</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('h-12');
                expect(button).toHaveClass('px-6');
                expect(button).toHaveClass('text-lg');
            });

            it('renders with xl size', () => {
                render(<Button size="xl">Extra Large</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('h-14');
                expect(button).toHaveClass('px-8');
            });

            it('renders with icon size', () => {
                render(<Button size="icon" aria-label="Icon button">X</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('h-10');
                expect(button).toHaveClass('w-10');
            });

            it('renders with icon-sm size', () => {
                render(<Button size="icon-sm" aria-label="Small icon">X</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('h-8');
                expect(button).toHaveClass('w-8');
            });

            it('renders with icon-lg size', () => {
                render(<Button size="icon-lg" aria-label="Large icon">X</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('h-12');
                expect(button).toHaveClass('w-12');
            });
        });

        describe('States and Props', () => {
            it('shows loading spinner when loading is true', () => {
                render(<Button loading>Loading</Button>);
                const button = screen.getByRole('button');
                expect(button).toBeDisabled();
                const spinner = button.querySelector('svg');
                expect(spinner).toBeInTheDocument();
                expect(spinner).toHaveClass('animate-spin');
            });

            it('disables button when loading', () => {
                render(<Button loading>Loading</Button>);
                expect(screen.getByRole('button')).toBeDisabled();
            });

            it('applies glow effect when glow prop is true', () => {
                render(<Button glow>Glowing</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('shadow-glow-primary');
                expect(button).toHaveClass('animate-glow-pulse');
            });

            it('can be disabled', () => {
                render(<Button disabled>Disabled</Button>);
                expect(screen.getByRole('button')).toBeDisabled();
            });

            it('applies custom className', () => {
                render(<Button className="custom-class">Custom</Button>);
                expect(screen.getByRole('button')).toHaveClass('custom-class');
            });
        });

        describe('Interactions', () => {
            it('handles click events', async () => {
                const user = userEvent.setup();
                const handleClick = vi.fn();
                render(<Button onClick={handleClick}>Click</Button>);

                await user.click(screen.getByRole('button'));
                expect(handleClick).toHaveBeenCalledTimes(1);
            });

            it('does not trigger click when disabled', async () => {
                const user = userEvent.setup();
                const handleClick = vi.fn();
                render(<Button disabled onClick={handleClick}>Disabled</Button>);

                await user.click(screen.getByRole('button'));
                expect(handleClick).not.toHaveBeenCalled();
            });

            it('does not trigger click when loading', async () => {
                const user = userEvent.setup();
                const handleClick = vi.fn();
                render(<Button loading onClick={handleClick}>Loading</Button>);

                await user.click(screen.getByRole('button'));
                expect(handleClick).not.toHaveBeenCalled();
            });
        });

        describe('Accessibility', () => {
            it('has proper button role', () => {
                render(<Button>Accessible</Button>);
                expect(screen.getByRole('button')).toBeInTheDocument();
            });

            it('supports aria-label', () => {
                render(<Button aria-label="Close dialog">X</Button>);
                expect(screen.getByRole('button', { name: 'Close dialog' })).toBeInTheDocument();
            });

            it('has focus-visible styles', () => {
                render(<Button>Focus me</Button>);
                const button = screen.getByRole('button');
                expect(button).toHaveClass('focus-visible:outline-none');
                expect(button).toHaveClass('focus-visible:ring-2');
            });
        });
    });

    describe('Card Component', () => {
        describe('Variants', () => {
            it('renders with default variant', () => {
                render(<Card data-testid="card">Content</Card>);
                const card = screen.getByTestId('card');
                expect(card).toHaveClass('bg-background-secondary');
                expect(card).toHaveClass('border-white/[0.06]');
            });

            it('renders with glass variant', () => {
                render(<Card variant="glass" data-testid="card">Glass</Card>);
                const card = screen.getByTestId('card');
                expect(card).toHaveClass('bg-white/[0.03]');
                expect(card).toHaveClass('backdrop-blur-xl');
            });

            it('renders with elevated variant', () => {
                render(<Card variant="elevated" data-testid="card">Elevated</Card>);
                const card = screen.getByTestId('card');
                expect(card).toHaveClass('bg-background-tertiary');
                expect(card).toHaveClass('shadow-lg');
            });

            it('renders with outline variant', () => {
                render(<Card variant="outline" data-testid="card">Outline</Card>);
                const card = screen.getByTestId('card');
                expect(card).toHaveClass('bg-transparent');
                expect(card).toHaveClass('border-white/[0.12]');
            });
        });

        describe('Props', () => {
            it('applies hover effect when hover is true', () => {
                render(<Card hover data-testid="card">Hoverable</Card>);
                const card = screen.getByTestId('card');
                expect(card).toHaveClass('cursor-pointer');
            });

            it('applies glow effect with primary glow', () => {
                render(<Card glow="primary" data-testid="card">Glow</Card>);
                expect(screen.getByTestId('card')).toHaveClass('shadow-glow-primary');
            });

            it('applies glow effect with success glow', () => {
                render(<Card glow="success" data-testid="card">Glow</Card>);
                expect(screen.getByTestId('card')).toHaveClass('shadow-glow-success');
            });

            it('applies glow effect with warning glow', () => {
                render(<Card glow="warning" data-testid="card">Glow</Card>);
                expect(screen.getByTestId('card')).toHaveClass('shadow-glow-warning');
            });

            it('applies glow effect with danger glow', () => {
                render(<Card glow="danger" data-testid="card">Glow</Card>);
                expect(screen.getByTestId('card')).toHaveClass('shadow-glow-danger');
            });

            it('does not apply glow when glow is none', () => {
                render(<Card glow="none" data-testid="card">No Glow</Card>);
                const card = screen.getByTestId('card');
                expect(card).not.toHaveClass('shadow-glow-primary');
                expect(card).not.toHaveClass('shadow-glow-success');
            });

            it('applies custom className', () => {
                render(<Card className="custom-card" data-testid="card">Custom</Card>);
                expect(screen.getByTestId('card')).toHaveClass('custom-card');
            });
        });

        describe('Sub-components', () => {
            it('renders CardHeader with compact spacing', () => {
                render(<CardHeader compact data-testid="header">Header</CardHeader>);
                expect(screen.getByTestId('header')).toHaveClass('mb-3');
            });

            it('renders CardHeader with default spacing', () => {
                render(<CardHeader data-testid="header">Header</CardHeader>);
                expect(screen.getByTestId('header')).toHaveClass('mb-4');
            });

            it('renders CardTitle as h3 by default', () => {
                render(<CardTitle>Title</CardTitle>);
                expect(screen.getByRole('heading', { level: 3, name: 'Title' })).toBeInTheDocument();
            });

            it('renders CardTitle with custom heading level', () => {
                render(<CardTitle as="h2">Title</CardTitle>);
                expect(screen.getByRole('heading', { level: 2, name: 'Title' })).toBeInTheDocument();
            });

            it('renders CardDescription with correct styling', () => {
                render(<CardDescription>Description text</CardDescription>);
                const description = screen.getByText('Description text');
                expect(description).toHaveClass('text-foreground-muted');
            });

            it('renders CardContent', () => {
                render(<CardContent>Content</CardContent>);
                expect(screen.getByText('Content')).toBeInTheDocument();
            });

            it('renders CardFooter without border by default', () => {
                render(<CardFooter data-testid="footer">Footer</CardFooter>);
                const footer = screen.getByTestId('footer');
                expect(footer).not.toHaveClass('border-t');
            });

            it('renders CardFooter with border when border is true', () => {
                render(<CardFooter border data-testid="footer">Footer</CardFooter>);
                const footer = screen.getByTestId('footer');
                expect(footer).toHaveClass('border-t');
                expect(footer).toHaveClass('pt-4');
            });

            it('renders complete card structure', () => {
                render(
                    <Card data-testid="card">
                        <CardHeader>
                            <CardTitle>Card Title</CardTitle>
                            <CardDescription>Card Description</CardDescription>
                        </CardHeader>
                        <CardContent>Card Content</CardContent>
                        <CardFooter border>Card Footer</CardFooter>
                    </Card>
                );

                expect(screen.getByText('Card Title')).toBeInTheDocument();
                expect(screen.getByText('Card Description')).toBeInTheDocument();
                expect(screen.getByText('Card Content')).toBeInTheDocument();
                expect(screen.getByText('Card Footer')).toBeInTheDocument();
            });
        });
    });

    describe('Badge Component', () => {
        describe('Variants', () => {
            it('renders with default variant', () => {
                render(<Badge>Default</Badge>);
                const badge = screen.getByText('Default');
                expect(badge).toHaveClass('bg-white/[0.08]');
                expect(badge).toHaveClass('text-foreground-muted');
            });

            it('renders with primary variant', () => {
                render(<Badge variant="primary">Primary</Badge>);
                const badge = screen.getByText('Primary');
                expect(badge).toHaveClass('bg-primary/15');
                expect(badge).toHaveClass('text-primary');
            });

            it('renders with success variant', () => {
                render(<Badge variant="success">Success</Badge>);
                const badge = screen.getByText('Success');
                expect(badge).toHaveClass('bg-success/15');
                expect(badge).toHaveClass('text-success');
            });

            it('renders with danger variant', () => {
                render(<Badge variant="danger">Danger</Badge>);
                const badge = screen.getByText('Danger');
                expect(badge).toHaveClass('bg-danger/15');
                expect(badge).toHaveClass('text-danger');
            });

            it('renders with warning variant', () => {
                render(<Badge variant="warning">Warning</Badge>);
                const badge = screen.getByText('Warning');
                expect(badge).toHaveClass('bg-warning/15');
                expect(badge).toHaveClass('text-warning');
            });

            it('renders with info variant', () => {
                render(<Badge variant="info">Info</Badge>);
                const badge = screen.getByText('Info');
                expect(badge).toHaveClass('bg-info/15');
                expect(badge).toHaveClass('text-info');
            });

            it('renders with primary-solid variant', () => {
                render(<Badge variant="primary-solid">Primary Solid</Badge>);
                const badge = screen.getByText('Primary Solid');
                expect(badge).toHaveClass('bg-primary');
                expect(badge).toHaveClass('text-white');
            });

            it('renders with success-solid variant', () => {
                render(<Badge variant="success-solid">Success Solid</Badge>);
                const badge = screen.getByText('Success Solid');
                expect(badge).toHaveClass('bg-success');
                expect(badge).toHaveClass('text-white');
            });

            it('renders with danger-solid variant', () => {
                render(<Badge variant="danger-solid">Danger Solid</Badge>);
                const badge = screen.getByText('Danger Solid');
                expect(badge).toHaveClass('bg-danger');
                expect(badge).toHaveClass('text-white');
            });

            it('renders with warning-solid variant', () => {
                render(<Badge variant="warning-solid">Warning Solid</Badge>);
                const badge = screen.getByText('Warning Solid');
                expect(badge).toHaveClass('bg-warning');
                expect(badge).toHaveClass('text-white');
            });

            it('renders with info-solid variant', () => {
                render(<Badge variant="info-solid">Info Solid</Badge>);
                const badge = screen.getByText('Info Solid');
                expect(badge).toHaveClass('bg-info');
                expect(badge).toHaveClass('text-white');
            });
        });

        describe('Sizes', () => {
            it('renders with default size', () => {
                render(<Badge>Default Size</Badge>);
                const badge = screen.getByText('Default Size');
                expect(badge).toHaveClass('px-2.5');
                expect(badge).toHaveClass('text-xs');
            });

            it('renders with sm size', () => {
                render(<Badge size="sm">Small</Badge>);
                const badge = screen.getByText('Small');
                expect(badge).toHaveClass('px-1.5');
                expect(badge).toHaveClass('text-[10px]');
            });

            it('renders with lg size', () => {
                render(<Badge size="lg">Large</Badge>);
                const badge = screen.getByText('Large');
                expect(badge).toHaveClass('px-3');
                expect(badge).toHaveClass('text-sm');
            });
        });

        describe('Props', () => {
            it('renders with dot indicator', () => {
                render(<Badge dot>With Dot</Badge>);
                const badge = screen.getByText('With Dot');
                const dot = badge.querySelector('.h-1\\.5');
                expect(dot).toBeInTheDocument();
            });

            it('renders with pulsing dot', () => {
                render(<Badge dot pulse>Pulsing</Badge>);
                const badge = screen.getByText('Pulsing');
                const dot = badge.querySelector('.animate-pulse-soft');
                expect(dot).toBeInTheDocument();
            });

            it('renders with icon', () => {
                const icon = <span data-testid="badge-icon">★</span>;
                render(<Badge icon={icon}>With Icon</Badge>);
                expect(screen.getByTestId('badge-icon')).toBeInTheDocument();
                expect(screen.getByText('With Icon')).toBeInTheDocument();
            });

            it('renders with both dot and icon', () => {
                const icon = <span data-testid="badge-icon">★</span>;
                render(<Badge dot icon={icon}>Complete</Badge>);
                expect(screen.getByTestId('badge-icon')).toBeInTheDocument();
                expect(screen.getByText('Complete')).toBeInTheDocument();
            });
        });
    });

    describe('StatusBadge Component', () => {
        it('renders online status', () => {
            render(<StatusBadge status="online" />);
            const badge = screen.getByText('Online');
            expect(badge).toBeInTheDocument();
            expect(badge).toHaveClass('bg-success/15');
            expect(badge.querySelector('.status-online')).toBeInTheDocument();
        });

        it('renders offline status', () => {
            render(<StatusBadge status="offline" />);
            const badge = screen.getByText('Offline');
            expect(badge).toBeInTheDocument();
            expect(badge.querySelector('.status-stopped')).toBeInTheDocument();
        });

        it('renders deploying status', () => {
            render(<StatusBadge status="deploying" />);
            const badge = screen.getByText('Deploying');
            expect(badge).toBeInTheDocument();
            expect(badge).toHaveClass('bg-warning/15');
            expect(badge.querySelector('.status-deploying')).toBeInTheDocument();
        });

        it('renders error status', () => {
            render(<StatusBadge status="error" />);
            const badge = screen.getByText('Error');
            expect(badge).toBeInTheDocument();
            expect(badge).toHaveClass('bg-danger/15');
            expect(badge.querySelector('.status-error')).toBeInTheDocument();
        });

        it('renders stopped status', () => {
            render(<StatusBadge status="stopped" />);
            const badge = screen.getByText('Stopped');
            expect(badge).toBeInTheDocument();
            expect(badge.querySelector('.status-stopped')).toBeInTheDocument();
        });

        it('renders initializing status', () => {
            render(<StatusBadge status="initializing" />);
            const badge = screen.getByText('Initializing');
            expect(badge).toBeInTheDocument();
            expect(badge).toHaveClass('bg-info/15');
            expect(badge.querySelector('.status-initializing')).toBeInTheDocument();
        });

        it('respects size prop', () => {
            render(<StatusBadge status="online" size="lg" />);
            const badge = screen.getByText('Online');
            expect(badge).toHaveClass('px-3');
            expect(badge).toHaveClass('text-sm');
        });
    });

    describe('Input Component', () => {
        describe('Rendering', () => {
            it('renders with label', () => {
                render(<Input label="Email" />);
                expect(screen.getByLabelText('Email')).toBeInTheDocument();
            });

            it('renders without label', () => {
                render(<Input placeholder="Enter text" />);
                expect(screen.getByPlaceholderText('Enter text')).toBeInTheDocument();
            });

            it('applies custom id', () => {
                render(<Input id="custom-input" label="Custom" />);
                const input = screen.getByLabelText('Custom');
                expect(input).toHaveAttribute('id', 'custom-input');
            });

            it('generates unique id when not provided', () => {
                render(
                    <>
                        <Input label="Input 1" />
                        <Input label="Input 2" />
                    </>
                );
                const input1 = screen.getByLabelText('Input 1');
                const input2 = screen.getByLabelText('Input 2');
                expect(input1.id).not.toBe(input2.id);
            });
        });

        describe('Validation States', () => {
            it('shows error message', () => {
                render(<Input error="This field is required" />);
                expect(screen.getByText('This field is required')).toBeInTheDocument();
            });

            it('applies error styling when error is present', () => {
                render(<Input error="Error message" />);
                const input = screen.getByRole('textbox');
                expect(input).toHaveClass('border-danger');
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

            it('shows error icon', () => {
                render(<Input error="Error" />);
                const errorText = screen.getByText('Error');
                const svg = errorText.querySelector('svg');
                expect(svg).toBeInTheDocument();
            });
        });

        describe('Icon Support', () => {
            it('renders with left icon', () => {
                const icon = <span data-testid="input-icon">@</span>;
                render(<Input icon={icon} iconPosition="left" />);
                expect(screen.getByTestId('input-icon')).toBeInTheDocument();
            });

            it('renders with right icon', () => {
                const icon = <span data-testid="input-icon">✓</span>;
                render(<Input icon={icon} iconPosition="right" />);
                expect(screen.getByTestId('input-icon')).toBeInTheDocument();
            });

            it('applies left padding when icon is on left', () => {
                const icon = <span>@</span>;
                render(<Input icon={icon} iconPosition="left" />);
                expect(screen.getByRole('textbox')).toHaveClass('pl-10');
            });

            it('applies right padding when icon is on right', () => {
                const icon = <span>✓</span>;
                render(<Input icon={icon} iconPosition="right" />);
                expect(screen.getByRole('textbox')).toHaveClass('pr-10');
            });

            it('defaults to left icon position', () => {
                const icon = <span data-testid="input-icon">@</span>;
                render(<Input icon={icon} />);
                expect(screen.getByTestId('input-icon')).toBeInTheDocument();
                expect(screen.getByRole('textbox')).toHaveClass('pl-10');
            });
        });

        describe('Interactions', () => {
            it('handles user input', async () => {
                const user = userEvent.setup();
                const handleChange = vi.fn();
                render(<Input onChange={handleChange} />);

                await user.type(screen.getByRole('textbox'), 'hello');
                expect(handleChange).toHaveBeenCalled();
            });

            it('can be disabled', () => {
                render(<Input disabled />);
                expect(screen.getByRole('textbox')).toBeDisabled();
            });

            it('applies disabled styling', () => {
                render(<Input disabled />);
                const input = screen.getByRole('textbox');
                expect(input).toHaveClass('disabled:cursor-not-allowed');
                expect(input).toHaveClass('disabled:opacity-50');
            });
        });

        describe('Accessibility', () => {
            it('associates label with input', () => {
                render(<Input id="email-input" label="Email Address" />);
                const _input = screen.getByRole('textbox');
                const label = screen.getByText('Email Address');
                expect(label).toHaveAttribute('for', 'email-input');
            });

            it('has proper textbox role', () => {
                render(<Input />);
                expect(screen.getByRole('textbox')).toBeInTheDocument();
            });
        });
    });

    describe('Textarea Component', () => {
        describe('Rendering', () => {
            it('renders with label', () => {
                render(<Textarea label="Description" />);
                expect(screen.getByLabelText('Description')).toBeInTheDocument();
            });

            it('renders without label', () => {
                render(<Textarea placeholder="Enter description" />);
                expect(screen.getByPlaceholderText('Enter description')).toBeInTheDocument();
            });

            it('applies custom id', () => {
                render(<Textarea id="custom-textarea" label="Custom" />);
                const textarea = screen.getByLabelText('Custom');
                expect(textarea).toHaveAttribute('id', 'custom-textarea');
            });

            it('has minimum height', () => {
                render(<Textarea />);
                const textarea = screen.getByRole('textbox');
                expect(textarea).toHaveClass('min-h-[100px]');
            });
        });

        describe('Validation States', () => {
            it('shows error message', () => {
                render(<Textarea error="This field is required" />);
                expect(screen.getByText('This field is required')).toBeInTheDocument();
            });

            it('applies error styling', () => {
                render(<Textarea error="Error" />);
                const textarea = screen.getByRole('textbox');
                expect(textarea).toHaveClass('border-danger');
            });

            it('shows hint when no error', () => {
                render(<Textarea hint="Enter a detailed description" />);
                expect(screen.getByText('Enter a detailed description')).toBeInTheDocument();
            });

            it('hides hint when error is present', () => {
                render(<Textarea hint="Enter description" error="Required" />);
                expect(screen.queryByText('Enter description')).not.toBeInTheDocument();
                expect(screen.getByText('Required')).toBeInTheDocument();
            });
        });

        describe('Interactions', () => {
            it('handles user input', async () => {
                const user = userEvent.setup();
                const handleChange = vi.fn();
                render(<Textarea onChange={handleChange} />);

                await user.type(screen.getByRole('textbox'), 'test content');
                expect(handleChange).toHaveBeenCalled();
            });

            it('can be disabled', () => {
                render(<Textarea disabled />);
                expect(screen.getByRole('textbox')).toBeDisabled();
            });

            it('has resize-none class', () => {
                render(<Textarea />);
                expect(screen.getByRole('textbox')).toHaveClass('resize-none');
            });
        });

        describe('Accessibility', () => {
            it('associates label with textarea', () => {
                render(<Textarea id="desc" label="Description" />);
                const _textarea = screen.getByRole('textbox');
                const label = screen.getByText('Description');
                expect(label).toHaveAttribute('for', 'desc');
            });
        });
    });

    describe('SaturnLogo Component', () => {
        describe('Sizes', () => {
            it('renders with default md size', () => {
                const { container } = render(<SaturnLogo />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-8');
                expect(svg).toHaveClass('w-8');
            });

            it('renders with xs size', () => {
                const { container } = render(<SaturnLogo size="xs" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-4');
                expect(svg).toHaveClass('w-4');
            });

            it('renders with sm size', () => {
                const { container } = render(<SaturnLogo size="sm" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-6');
                expect(svg).toHaveClass('w-6');
            });

            it('renders with lg size', () => {
                const { container } = render(<SaturnLogo size="lg" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-10');
                expect(svg).toHaveClass('w-10');
            });

            it('renders with xl size', () => {
                const { container } = render(<SaturnLogo size="xl" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-12');
                expect(svg).toHaveClass('w-12');
            });
        });

        describe('Animation', () => {
            it('does not animate by default', () => {
                const { container } = render(<SaturnLogo />);
                const svg = container.querySelector('svg');
                expect(svg).not.toHaveClass('animate-spin-slow');
            });

            it('animates when animate prop is true', () => {
                const { container } = render(<SaturnLogo animate />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('animate-spin-slow');
            });
        });

        describe('SVG Structure', () => {
            it('renders with correct viewBox', () => {
                const { container } = render(<SaturnLogo />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveAttribute('viewBox', '0 0 32 32');
            });

            it('has gradients defined', () => {
                const { container } = render(<SaturnLogo />);
                expect(container.querySelector('#saturn-glow')).toBeInTheDocument();
                expect(container.querySelector('#saturn-gradient')).toBeInTheDocument();
            });

            it('has planet body circle', () => {
                const { container } = render(<SaturnLogo />);
                const circles = container.querySelectorAll('circle');
                expect(circles.length).toBeGreaterThan(0);
            });

            it('has ellipse for rings', () => {
                const { container } = render(<SaturnLogo />);
                const ellipses = container.querySelectorAll('ellipse');
                expect(ellipses.length).toBeGreaterThan(0);
            });
        });

        describe('Custom Styling', () => {
            it('applies custom className', () => {
                const { container } = render(<SaturnLogo className="custom-logo" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('custom-logo');
            });
        });
    });

    describe('SaturnBrand Component', () => {
        describe('Sizes', () => {
            it('renders with default md size', () => {
                render(<SaturnBrand />);
                const text = screen.getByText('Saturn');
                expect(text).toHaveClass('text-xl');
            });

            it('renders with sm size', () => {
                render(<SaturnBrand size="sm" />);
                const text = screen.getByText('Saturn');
                expect(text).toHaveClass('text-lg');
            });

            it('renders with lg size', () => {
                render(<SaturnBrand size="lg" />);
                const text = screen.getByText('Saturn');
                expect(text).toHaveClass('text-2xl');
            });
        });

        describe('Logo Visibility', () => {
            it('shows logo by default', () => {
                const { container } = render(<SaturnBrand />);
                const svg = container.querySelector('svg');
                expect(svg).toBeInTheDocument();
            });

            it('hides logo when showLogo is false', () => {
                const { container } = render(<SaturnBrand showLogo={false} />);
                const svg = container.querySelector('svg');
                expect(svg).not.toBeInTheDocument();
                expect(screen.getByText('Saturn')).toBeInTheDocument();
            });
        });

        describe('Text Styling', () => {
            it('has gradient class', () => {
                render(<SaturnBrand />);
                const text = screen.getByText('Saturn');
                expect(text).toHaveClass('saturn-gradient');
                expect(text).toHaveClass('font-bold');
                expect(text).toHaveClass('tracking-tight');
            });
        });

        describe('Custom Styling', () => {
            it('applies custom className', () => {
                const { container } = render(<SaturnBrand className="custom-brand" />);
                const wrapper = container.querySelector('.custom-brand');
                expect(wrapper).toBeInTheDocument();
            });
        });
    });

    describe('SaturnIcon Component', () => {
        describe('Sizes', () => {
            it('renders with default md size', () => {
                const { container } = render(<SaturnIcon />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-6');
                expect(svg).toHaveClass('w-6');
            });

            it('renders with xs size', () => {
                const { container } = render(<SaturnIcon size="xs" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-4');
                expect(svg).toHaveClass('w-4');
            });

            it('renders with sm size', () => {
                const { container } = render(<SaturnIcon size="sm" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-5');
                expect(svg).toHaveClass('w-5');
            });

            it('renders with lg size', () => {
                const { container } = render(<SaturnIcon size="lg" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('h-8');
                expect(svg).toHaveClass('w-8');
            });
        });

        describe('SVG Structure', () => {
            it('renders with correct viewBox', () => {
                const { container } = render(<SaturnIcon />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveAttribute('viewBox', '0 0 24 24');
            });

            it('has gradient defined', () => {
                const { container } = render(<SaturnIcon />);
                expect(container.querySelector('#saturn-icon-grad')).toBeInTheDocument();
            });

            it('has planet circle', () => {
                const { container } = render(<SaturnIcon />);
                const circle = container.querySelector('circle');
                expect(circle).toBeInTheDocument();
                expect(circle).toHaveAttribute('r', '5');
            });

            it('has ring ellipse', () => {
                const { container } = render(<SaturnIcon />);
                const ellipse = container.querySelector('ellipse');
                expect(ellipse).toBeInTheDocument();
            });
        });

        describe('Custom Styling', () => {
            it('applies custom className', () => {
                const { container } = render(<SaturnIcon className="custom-icon" />);
                const svg = container.querySelector('svg');
                expect(svg).toHaveClass('custom-icon');
            });
        });
    });
});
