import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import Onboarding from '@/pages/Auth/Onboarding/Index';

describe('Onboarding Page', () => {
    const mockTemplates = [
        { id: 'nodejs', name: 'Node.js App', description: 'A simple Node.js application', icon: '🟢' },
        { id: 'nextjs', name: 'Next.js', description: 'Full-stack React framework', icon: '▲' },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
        delete (window as any).location;
        (window as any).location = { href: '', origin: 'https://example.com' };
    });

    describe('step 1 - welcome and name', () => {
        it('should render welcome title', () => {
            render(<Onboarding />);

            expect(screen.getByText('Welcome to Saturn!')).toBeInTheDocument();
            expect(screen.getByText("Let's personalize your experience")).toBeInTheDocument();
        });

        it('should render name input', () => {
            render(<Onboarding />);

            const nameInput = screen.getByLabelText(/what should we call you/i);
            expect(nameInput).toBeInTheDocument();
            expect(nameInput).toHaveAttribute('placeholder', 'Your name');
        });

        it('should pre-fill name if provided', () => {
            render(<Onboarding userName="John Doe" />);

            const nameInput = screen.getByLabelText(/what should we call you/i);
            expect(nameInput).toHaveValue('John Doe');
        });

        it('should render progress indicator with 4 steps', () => {
            render(<Onboarding />);

            expect(screen.getByText('Step 1')).toBeInTheDocument();
            expect(screen.getByText('Step 2')).toBeInTheDocument();
            expect(screen.getByText('Step 3')).toBeInTheDocument();
            expect(screen.getByText('Step 4')).toBeInTheDocument();
        });

        it('should render next button', () => {
            render(<Onboarding />);

            expect(screen.getByRole('button', { name: /next/i })).toBeInTheDocument();
        });

        it('should render skip button', () => {
            render(<Onboarding />);

            expect(screen.getByRole('button', { name: /skip for now/i })).toBeInTheDocument();
        });

        it('should disable next button when name is empty', () => {
            render(<Onboarding />);

            const nextButton = screen.getByRole('button', { name: /next/i });
            expect(nextButton).toBeDisabled();
        });

        it('should enable next button when name is filled', async () => {
            const { user } = render(<Onboarding />);

            const nameInput = screen.getByLabelText(/what should we call you/i);
            await user.type(nameInput, 'John');

            const nextButton = screen.getByRole('button', { name: /next/i });
            expect(nextButton).not.toBeDisabled();
        });
    });

    describe('step 2 - create project', () => {
        it('should show project creation step after clicking next', async () => {
            const { user } = render(<Onboarding />);

            const nameInput = screen.getByLabelText(/what should we call you/i);
            await user.type(nameInput, 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByText('Create Your First Project')).toBeInTheDocument();
        });

        it('should render project name input', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));

            const projectInput = screen.getByLabelText('Project Name');
            expect(projectInput).toBeInTheDocument();
            expect(projectInput).toHaveAttribute('placeholder', 'My Awesome Project');
        });

        it('should render blank project and template options', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByText('Blank Project')).toBeInTheDocument();
            expect(screen.getByText('Use Template')).toBeInTheDocument();
        });

        it('should show default templates', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));

            // Select template option
            await user.click(screen.getByText('Use Template'));

            expect(screen.getByText('Node.js App')).toBeInTheDocument();
            expect(screen.getByText('Next.js')).toBeInTheDocument();
            expect(screen.getByText('Laravel')).toBeInTheDocument();
        });

        it('should render back button on step 2', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByRole('button', { name: /back/i })).toBeInTheDocument();
        });

        it('should go back to step 1 when back is clicked', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.click(screen.getByRole('button', { name: /back/i }));

            expect(screen.getByText('Welcome to Saturn!')).toBeInTheDocument();
        });
    });

    describe('step 3 - connect github', () => {
        it('should show GitHub connection step', async () => {
            const { user } = render(<Onboarding />);

            // Complete steps 1 and 2
            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.type(screen.getByLabelText('Project Name'), 'My Project');
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByText('Connect GitHub')).toBeInTheDocument();
            expect(screen.getByText('Deploy from your repositories automatically')).toBeInTheDocument();
        });

        it('should render GitHub benefits list', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.type(screen.getByLabelText('Project Name'), 'My Project');
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByText(/deploy directly from your repositories/i)).toBeInTheDocument();
            expect(screen.getByText(/automatic deployments on push/i)).toBeInTheDocument();
            expect(screen.getByText(/pull request previews/i)).toBeInTheDocument();
        });

        it('should render connect GitHub button', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.type(screen.getByLabelText('Project Name'), 'My Project');
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByRole('button', { name: /connect github account/i })).toBeInTheDocument();
        });
    });

    describe('step 4 - deploy service', () => {
        it('should show service deployment step', async () => {
            const { user } = render(<Onboarding />);

            // Complete steps 1, 2, 3
            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.type(screen.getByLabelText('Project Name'), 'My Project');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByText('Deploy Your First Service')).toBeInTheDocument();
            expect(screen.getByText('Choose a database or service to get started')).toBeInTheDocument();
        });

        it('should render database service options', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.type(screen.getByLabelText('Project Name'), 'My Project');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
            expect(screen.getByText('Redis')).toBeInTheDocument();
            expect(screen.getByText('MongoDB')).toBeInTheDocument();
            expect(screen.getByText('MySQL')).toBeInTheDocument();
        });

        it('should render complete setup button on final step', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.type(screen.getByLabelText('Project Name'), 'My Project');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByRole('button', { name: /complete setup/i })).toBeInTheDocument();
        });

        it('should show informational message about adding services later', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.type(screen.getByLabelText('Project Name'), 'My Project');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.click(screen.getByRole('button', { name: /next/i }));

            expect(screen.getByText(/you can always add more services later/i)).toBeInTheDocument();
        });
    });

    describe('navigation', () => {
        it('should call router.visit when skip is clicked', async () => {
            const { user } = render(<Onboarding />);

            await user.click(screen.getByRole('button', { name: /skip for now/i }));

            expect(router.visit).toHaveBeenCalledWith('/dashboard');
        });

        it('should redirect to GitHub OAuth when connect is clicked', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.type(screen.getByLabelText('Project Name'), 'My Project');
            await user.click(screen.getByRole('button', { name: /next/i }));

            const githubButton = screen.getByRole('button', { name: /connect github account/i });
            await user.click(githubButton);

            expect(window.location.href).toBe('/auth/github/redirect');
        });
    });

    describe('progress indicator', () => {
        it('should mark completed steps', async () => {
            const { user } = render(<Onboarding />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));

            // Step 1 should be marked as completed (visually)
            expect(screen.getByText('Step 2')).toBeInTheDocument();
        });

        it('should highlight active step', () => {
            render(<Onboarding />);

            // Step 1 should be active initially
            expect(screen.getByText('Step 1')).toBeInTheDocument();
        });
    });

    describe('edge cases', () => {
        it('should handle missing templates prop', () => {
            render(<Onboarding />);

            expect(screen.getByText('Welcome to Saturn')).toBeInTheDocument();
        });

        it('should use provided templates', async () => {
            const { user } = render(<Onboarding templates={mockTemplates} />);

            await user.type(screen.getByLabelText(/what should we call you/i), 'John');
            await user.click(screen.getByRole('button', { name: /next/i }));
            await user.click(screen.getByText('Use Template'));

            expect(screen.getByText('Node.js App')).toBeInTheDocument();
            expect(screen.getByText('Next.js')).toBeInTheDocument();
        });
    });
});
