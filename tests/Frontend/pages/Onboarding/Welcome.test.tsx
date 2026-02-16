import { describe, it, expect } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import OnboardingWelcome from '@/pages/Onboarding/Welcome';

describe('Onboarding/Welcome', () => {
    it('renders welcome heading with user first name', () => {
        render(<OnboardingWelcome user={{ name: 'John Doe', email: 'john@example.com' }} />);

        expect(screen.getByRole('heading', { level: 1, name: /welcome to saturn, john!/i })).toBeInTheDocument();
    });

    it('renders welcome heading with default name when user not provided', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 1, name: /welcome to saturn, there!/i })).toBeInTheDocument();
    });

    it('renders subtitle', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByText(/let's get you started with deploying your first application/i)).toBeInTheDocument();
    });

    it('renders choose how to get started heading', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 2, name: /choose how you want to get started/i })).toBeInTheDocument();
    });

    it('renders deploy from github option', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 3, name: /deploy from github/i })).toBeInTheDocument();
        expect(screen.getByText(/connect your github repository and deploy automatically/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /connect github/i })).toBeInTheDocument();
    });

    it('renders deploy from template option', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 3, name: /deploy from template/i })).toBeInTheDocument();
        expect(screen.getByText(/start with a pre-configured template/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /browse templates/i })).toBeInTheDocument();
    });

    it('renders create empty project option', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 3, name: /create empty project/i })).toBeInTheDocument();
        expect(screen.getByText(/start from scratch with a blank project/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /create project/i })).toBeInTheDocument();
    });

    it('links github option to correct URL', () => {
        render(<OnboardingWelcome />);

        const githubLink = screen.getByRole('link', { name: /deploy from github/i });
        expect(githubLink).toHaveAttribute('href', '/onboarding/connect-repo?provider=github');
    });

    it('links template option to correct URL', () => {
        render(<OnboardingWelcome />);

        const templateLink = screen.getByRole('link', { name: /deploy from template/i });
        expect(templateLink).toHaveAttribute('href', '/templates');
    });

    it('links create project option to correct URL', () => {
        render(<OnboardingWelcome />);

        const projectLink = screen.getByRole('link', { name: /create empty project/i });
        expect(projectLink).toHaveAttribute('href', '/projects/create');
    });

    it('renders what you can do section', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 2, name: /what you can do with saturn/i })).toBeInTheDocument();
    });

    it('renders all feature cards', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 4, name: /deploy instantly/i })).toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 4, name: /ssl & security/i })).toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 4, name: /monitor & scale/i })).toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 4, name: /scheduled tasks/i })).toBeInTheDocument();
    });

    it('renders feature descriptions', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByText(/push to deploy with automatic builds and zero downtime/i)).toBeInTheDocument();
        expect(screen.getByText(/automatic ssl certificates and security best practices/i)).toBeInTheDocument();
        expect(screen.getByText(/real-time metrics and effortless horizontal scaling/i)).toBeInTheDocument();
        expect(screen.getByText(/cron jobs and one-time tasks made simple/i)).toBeInTheDocument();
    });

    it('renders need help section', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 3, name: /need help getting started/i })).toBeInTheDocument();
        expect(screen.getByText(/check out our comprehensive documentation/i)).toBeInTheDocument();
    });

    it('renders help action buttons', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('link', { name: /view settings/i })).toHaveAttribute('href', '/settings');
        expect(screen.getByRole('link', { name: /add server/i })).toHaveAttribute('href', '/servers');
    });

    it('renders getting started checklist', () => {
        render(<OnboardingWelcome />);

        expect(screen.getByRole('heading', { level: 3, name: /getting started checklist/i })).toBeInTheDocument();
        expect(screen.getByText(/create your saturn account/i)).toBeInTheDocument();
        expect(screen.getByText(/connect a git repository/i)).toBeInTheDocument();
        expect(screen.getByText(/deploy your first application/i)).toBeInTheDocument();
        expect(screen.getByText(/set up custom domain and ssl/i)).toBeInTheDocument();
        expect(screen.getByText(/configure environment variables/i)).toBeInTheDocument();
    });

    it('marks first checklist item as completed', () => {
        render(<OnboardingWelcome />);

        const firstItem = screen.getByText(/create your saturn account/i);
        // Completed items have line-through class
        expect(firstItem.className).toContain('line-through');
    });

    it('renders skip onboarding link', () => {
        render(<OnboardingWelcome />);

        const skipLink = screen.getByRole('link', { name: /skip onboarding and go to dashboard/i });
        expect(skipLink).toBeInTheDocument();
        expect(skipLink).toHaveAttribute('href', '/dashboard');
    });
});
