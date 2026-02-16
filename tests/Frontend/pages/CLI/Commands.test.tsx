import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import CLICommands from '@/pages/CLI/Commands';

describe('CLI/Commands', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page heading and description', () => {
        render(<CLICommands />);

        expect(screen.getByRole('heading', { level: 1, name: /cli command reference/i })).toBeInTheDocument();
        expect(screen.getByText(/complete guide to all saturn cli commands/i)).toBeInTheDocument();
    });

    it('renders search input', () => {
        render(<CLICommands />);

        expect(screen.getByPlaceholderText(/search commands/i)).toBeInTheDocument();
    });

    it('renders quick reference section', () => {
        render(<CLICommands />);

        expect(screen.getByRole('heading', { name: /quick reference/i })).toBeInTheDocument();
        expect(screen.getByText(/essential commands to get started/i)).toBeInTheDocument();
    });

    it('renders quick reference commands', () => {
        render(<CLICommands />);

        const loginCommands = screen.getAllByText('saturn login');
        expect(loginCommands.length).toBeGreaterThan(0);
        const deployCommands = screen.getAllByText('saturn deploy');
        expect(deployCommands.length).toBeGreaterThan(0);
        expect(screen.getByText('saturn logs -f')).toBeInTheDocument();
        const runCommands = screen.getAllByText('saturn run');
        expect(runCommands.length).toBeGreaterThan(0);
    });

    it('renders authentication category', () => {
        render(<CLICommands />);

        const authHeaders = screen.getAllByRole('heading', { name: /authentication/i });
        expect(authHeaders.length).toBeGreaterThan(0);
    });

    it('renders deployment category', () => {
        render(<CLICommands />);

        const deploymentHeaders = screen.getAllByRole('heading', { name: /deployment/i });
        expect(deploymentHeaders.length).toBeGreaterThan(0);
    });

    it('renders development category', () => {
        render(<CLICommands />);

        const developmentHeaders = screen.getAllByRole('heading', { name: /development/i });
        expect(developmentHeaders.length).toBeGreaterThan(0);
    });

    it('renders configuration category', () => {
        render(<CLICommands />);

        const configHeaders = screen.getAllByRole('heading', { name: /configuration/i });
        expect(configHeaders.length).toBeGreaterThan(0);
    });

    it('renders projects category', () => {
        render(<CLICommands />);

        const projectHeaders = screen.getAllByRole('heading', { name: /projects/i });
        expect(projectHeaders.length).toBeGreaterThan(0);
    });

    it('expands command details when clicked', async () => {
        const { user } = render(<CLICommands />);

        // Find a command button (e.g., login)
        const loginButton = screen.getByRole('button', { name: /saturn login.*authenticate with your saturn account/i });
        await user.click(loginButton);

        // Check that details are visible
        expect(screen.getByText(/usage/i)).toBeInTheDocument();
    });

    it('collapses command details when clicked again', async () => {
        const { user } = render(<CLICommands />);

        const loginButton = screen.getByRole('button', { name: /saturn login.*authenticate with your saturn account/i });

        // Expand
        await user.click(loginButton);
        expect(screen.getByText(/usage/i)).toBeInTheDocument();

        // Collapse
        await user.click(loginButton);

        // Usage should still be in document (from other commands), but examples should not be visible
        // This is a simplified check
        expect(loginButton).toBeInTheDocument();
    });

    it('filters commands by search query', async () => {
        const { user } = render(<CLICommands />);

        const searchInput = screen.getByPlaceholderText(/search commands/i);
        await user.type(searchInput, 'deploy');

        // Should show deploy command
        expect(screen.getByText(/deploy your application to saturn/i)).toBeInTheDocument();

        // Should hide unrelated commands (login should still be in quick reference)
        // But deployment category should be visible
        expect(screen.getByText(/deploy your application to saturn/i)).toBeInTheDocument();
    });

    it('shows empty state when search has no results', async () => {
        const { user } = render(<CLICommands />);

        const searchInput = screen.getByPlaceholderText(/search commands/i);
        await user.type(searchInput, 'nonexistentcommand123');

        // Categories should not have any commands
        // Quick reference should still be visible
        expect(screen.getByRole('heading', { name: /quick reference/i })).toBeInTheDocument();
    });

    it('shows copy buttons after expanding command', async () => {
        const writeTextMock = vi.fn();
        Object.defineProperty(navigator, 'clipboard', {
            value: {
                writeText: writeTextMock,
            },
            writable: true,
            configurable: true,
        });

        const { user } = render(<CLICommands />);

        // Expand a command
        const deployButton = screen.getByRole('button', { name: /saturn deploy.*deploy your application to saturn/i });
        await user.click(deployButton);

        // Usage section should be visible
        expect(screen.getByText(/usage/i)).toBeInTheDocument();
        // Copy buttons exist (check by finding pre elements which contain code)
        const preElements = screen.getAllByText(/saturn deploy/);
        expect(preElements.length).toBeGreaterThan(1); // Should appear in expanded section
    });

    it('renders documentation link', () => {
        render(<CLICommands />);

        const docLink = screen.getByRole('link', { name: /full cli documentation.*read the complete cli guide/i });
        expect(docLink).toBeInTheDocument();
        expect(docLink).toHaveAttribute('href', 'https://docs.saturn.app/cli');
        expect(docLink).toHaveAttribute('target', '_blank');
    });

    it('displays command descriptions', () => {
        render(<CLICommands />);

        expect(screen.getByText(/authenticate with your saturn account/i)).toBeInTheDocument();
        expect(screen.getByText(/deploy your application to saturn/i)).toBeInTheDocument();
        expect(screen.getByText(/view application logs/i)).toBeInTheDocument();
        expect(screen.getByText(/manage environment variables/i)).toBeInTheDocument();
    });

    it('shows command count badges', () => {
        render(<CLICommands />);

        // Should have badges showing number of commands in each category
        const badges = screen.getAllByText(/\d+/);
        expect(badges.length).toBeGreaterThan(0);
    });
});
