import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import SupportIndex from '../../../../resources/js/pages/Support/Index';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            post: vi.fn(),
        },
    };
});

describe('Support/Index', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders support page with heading', () => {
        render(<SupportIndex />);

        expect(screen.getByRole('heading', { level: 1, name: /how can we help\?/i })).toBeInTheDocument();
        expect(screen.getByText(/search our documentation or contact support/i)).toBeInTheDocument();
    });

    it('displays search input', () => {
        render(<SupportIndex />);

        expect(screen.getByPlaceholderText(/search for help articles/i)).toBeInTheDocument();
    });

    it('shows quick link cards', () => {
        render(<SupportIndex />);

        expect(screen.getByText('Documentation')).toBeInTheDocument();
        expect(screen.getByText(/comprehensive guides and api reference/i)).toBeInTheDocument();

        expect(screen.getByText('Community')).toBeInTheDocument();
        expect(screen.getByText(/join our discord community/i)).toBeInTheDocument();

        expect(screen.getByText('GitHub')).toBeInTheDocument();
        expect(screen.getByText(/report issues and contribute/i)).toBeInTheDocument();
    });

    it('renders external links for quick links', () => {
        render(<SupportIndex />);

        const links = screen.getAllByRole('link');
        const externalLinks = links.filter(link =>
            link.getAttribute('href')?.includes('coolify.io/docs') ||
            link.getAttribute('href')?.includes('discord.gg') ||
            link.getAttribute('href')?.includes('github.com')
        );

        expect(externalLinks.length).toBeGreaterThan(0);
        externalLinks.forEach(link => {
            expect(link).toHaveAttribute('target', '_blank');
            expect(link).toHaveAttribute('rel', 'noopener noreferrer');
        });
    });

    it('displays popular topics section', () => {
        render(<SupportIndex />);

        expect(screen.getByText('Popular Topics')).toBeInTheDocument();
        // Topic titles appear in topic cards AND as FAQ category labels/buttons
        expect(screen.getAllByText('Getting Started').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('Deployments').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('Databases').length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByText('Security').length).toBeGreaterThanOrEqual(1);
    });

    it('shows article counts for topics', () => {
        render(<SupportIndex />);

        expect(screen.getByText('12 articles')).toBeInTheDocument();
        expect(screen.getByText('18 articles')).toBeInTheDocument();
        expect(screen.getByText('15 articles')).toBeInTheDocument();
        expect(screen.getByText('8 articles')).toBeInTheDocument();
    });

    it('renders FAQ section', () => {
        render(<SupportIndex />);

        expect(screen.getByText('Frequently Asked Questions')).toBeInTheDocument();
    });

    it('shows category filter buttons in FAQ section', () => {
        render(<SupportIndex />);

        // Category text also appears in FAQ question buttons (which include category labels)
        expect(screen.getByRole('button', { name: /^all$/i })).toBeInTheDocument();
        expect(screen.getAllByRole('button', { name: /getting started/i }).length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByRole('button', { name: /deployment/i }).length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByRole('button', { name: /databases/i }).length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByRole('button', { name: /configuration/i }).length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByRole('button', { name: /billing/i }).length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByRole('button', { name: /troubleshooting/i }).length).toBeGreaterThanOrEqual(1);
        expect(screen.getAllByRole('button', { name: /security/i }).length).toBeGreaterThanOrEqual(1);
    });

    it('displays FAQ questions', () => {
        render(<SupportIndex />);

        expect(screen.getByText(/how do i deploy my first application\?/i)).toBeInTheDocument();
        expect(screen.getByText(/what types of applications can i deploy\?/i)).toBeInTheDocument();
        expect(screen.getByText(/what databases are supported\?/i)).toBeInTheDocument();
    });

    it('expands FAQ answer when question is clicked', async () => {
        const { user } = render(<SupportIndex />);

        const question = screen.getByText(/how do i deploy my first application\?/i);
        await user.click(question);

        expect(screen.getByText(/to deploy your first application:/i)).toBeInTheDocument();
        expect(screen.getByText(/connect your git repository/i)).toBeInTheDocument();
    });

    it('collapses FAQ answer when clicked again', async () => {
        const { user } = render(<SupportIndex />);

        const question = screen.getByText(/how do i deploy my first application\?/i);

        // Expand
        await user.click(question);
        expect(screen.getByText(/to deploy your first application:/i)).toBeInTheDocument();

        // Collapse
        await user.click(question);
        expect(screen.queryByText(/to deploy your first application:/i)).not.toBeInTheDocument();
    });

    it('filters FAQs by search query', async () => {
        const { user } = render(<SupportIndex />);

        const searchInput = screen.getByPlaceholderText(/search for help articles/i);
        await user.type(searchInput, 'database');

        expect(screen.getByText(/what databases are supported\?/i)).toBeInTheDocument();
        expect(screen.getByText(/how do i backup my database\?/i)).toBeInTheDocument();
        expect(screen.queryByText(/how do i deploy my first application\?/i)).not.toBeInTheDocument();
    });

    it('filters FAQs by category', async () => {
        const { user } = render(<SupportIndex />);

        // "Deployment" appears in filter button AND FAQ question buttons (which include category label)
        const deploymentButtons = screen.getAllByRole('button', { name: /deployment/i });
        await user.click(deploymentButtons[0]);

        expect(screen.getByText(/how do automatic deployments work\?/i)).toBeInTheDocument();
        expect(screen.getByText(/can i rollback to a previous deployment\?/i)).toBeInTheDocument();
        expect(screen.queryByText(/what databases are supported\?/i)).not.toBeInTheDocument();
    });

    it('shows no results message when search has no matches', async () => {
        const { user } = render(<SupportIndex />);

        const searchInput = screen.getByPlaceholderText(/search for help articles/i);
        await user.type(searchInput, 'nonexistent query xyz');

        expect(screen.getByText(/no results found/i)).toBeInTheDocument();
        expect(screen.getByText(/try adjusting your search or category filter/i)).toBeInTheDocument();
    });

    it('displays contact support form', () => {
        render(<SupportIndex />);

        expect(screen.getByText('Contact Support')).toBeInTheDocument();
        expect(screen.getByText(/can't find what you're looking for\?/i)).toBeInTheDocument();
        expect(screen.getByText(/send us a message and we'll get back to you within 24 hours/i)).toBeInTheDocument();
    });

    it('shows contact form fields', () => {
        render(<SupportIndex />);

        // Labels have no htmlFor, so use placeholder text for inputs and role for select
        expect(screen.getByPlaceholderText('Your name')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('your@email.com')).toBeInTheDocument();
        expect(screen.getByRole('combobox')).toBeInTheDocument();
        expect(screen.getByPlaceholderText(/describe your issue/i)).toBeInTheDocument();
    });

    it('displays subject options in contact form', () => {
        render(<SupportIndex />);

        const subjectSelect = screen.getByRole('combobox') as HTMLSelectElement;
        const options = Array.from(subjectSelect.options).map(opt => opt.text);

        expect(options).toContain('General Inquiry');
        expect(options).toContain('Technical Support');
        expect(options).toContain('Billing Question');
        expect(options).toContain('Feature Request');
        expect(options).toContain('Bug Report');
    });

    it('shows send message button', () => {
        render(<SupportIndex />);

        expect(screen.getByRole('button', { name: /send message/i })).toBeInTheDocument();
    });

    it('submits contact form with correct data', async () => {
        const { user } = render(<SupportIndex />);

        await user.type(screen.getByPlaceholderText('Your name'), 'John Doe');
        await user.type(screen.getByPlaceholderText('your@email.com'), 'john@example.com');
        await user.selectOptions(screen.getByRole('combobox'), 'technical');
        await user.type(screen.getByPlaceholderText(/describe your issue/i), 'I need help with deployment');

        const submitButton = screen.getByRole('button', { name: /send message/i });
        await user.click(submitButton);

        expect(router.post).toHaveBeenCalledWith('/support/contact', {
            name: 'John Doe',
            email: 'john@example.com',
            subject: 'technical',
            message: 'I need help with deployment',
        });
    });

    it('resets form after submission', async () => {
        const { user } = render(<SupportIndex />);

        const nameInput = screen.getByPlaceholderText('Your name') as HTMLInputElement;
        const emailInput = screen.getByPlaceholderText('your@email.com') as HTMLInputElement;
        const messageInput = screen.getByPlaceholderText(/describe your issue/i) as HTMLTextAreaElement;

        await user.type(nameInput, 'John Doe');
        await user.type(emailInput, 'john@example.com');
        await user.type(messageInput, 'Test message');

        const submitButton = screen.getByRole('button', { name: /send message/i });
        await user.click(submitButton);

        // Form should be reset after submission
        expect(nameInput.value).toBe('');
        expect(emailInput.value).toBe('');
        expect(messageInput.value).toBe('');
    });

    it('displays Saturn Platform status', () => {
        render(<SupportIndex />);

        expect(screen.getByText(/saturn platform/i)).toBeInTheDocument();
    });

    it('shows specific FAQ answers for different categories', async () => {
        const { user } = render(<SupportIndex />);

        // Deployment category - filter button is first match among multiple
        const deploymentButtons = screen.getAllByRole('button', { name: /deployment/i });
        await user.click(deploymentButtons[0]);

        const deploymentQuestion = screen.getByText(/how do automatic deployments work\?/i);
        await user.click(deploymentQuestion);

        expect(screen.getByText(/when you connect your git repository/i)).toBeInTheDocument();

        // Database category - filter button is first match among multiple
        const databaseButtons = screen.getAllByRole('button', { name: /databases/i });
        await user.click(databaseButtons[0]);

        const databaseQuestion = screen.getByText(/what databases are supported\?/i);
        await user.click(databaseQuestion);

        expect(screen.getByText(/saturn supports postgresql/i)).toBeInTheDocument();
    });
});
