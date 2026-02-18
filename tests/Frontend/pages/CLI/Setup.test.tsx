import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import CLISetup from '@/pages/CLI/Setup';

describe('CLI/Setup', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders settings heading and tab navigation', () => {
        render(<CLISetup />);

        expect(screen.getByRole('heading', { level: 1, name: /settings/i })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /^setup$/i })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /^commands$/i })).toBeInTheDocument();
    });

    it('renders latest version info', () => {
        render(<CLISetup />);

        expect(screen.getByText(/latest version/i)).toBeInTheDocument();
        expect(screen.getByText('v1.4.0')).toBeInTheDocument();
        expect(screen.getByText(/stable/i)).toBeInTheDocument();
    });

    it('renders installation section', () => {
        render(<CLISetup />);

        expect(screen.getByRole('heading', { name: /^installation$/i })).toBeInTheDocument();
        expect(screen.getByText(/choose your operating system and run the installation command/i)).toBeInTheDocument();
    });

    it('renders all OS selector buttons', () => {
        render(<CLISetup />);

        expect(screen.getByRole('button', { name: 'macOS' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Linux' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Windows' })).toBeInTheDocument();
    });

    it('shows macOS as selected by default', () => {
        render(<CLISetup />);

        const macButton = screen.getByRole('button', { name: 'macOS' });
        expect(macButton.className).toContain('bg-primary');
    });

    it('displays macOS installation command by default', () => {
        render(<CLISetup />);

        expect(screen.getByText('brew install saturn-cli')).toBeInTheDocument();
        expect(screen.getByText(/install via homebrew/i)).toBeInTheDocument();
    });

    it('switches to linux installation command', async () => {
        const { user } = render(<CLISetup />);

        const linuxButton = screen.getByRole('button', { name: 'Linux' });
        await user.click(linuxButton);

        expect(screen.getByText(/curl -fsSL https:\/\/get\.saturn\.app\/install\.sh \| sh/)).toBeInTheDocument();
        expect(screen.getByText(/install via shell script/i)).toBeInTheDocument();
    });

    it('switches to windows installation command', async () => {
        const { user } = render(<CLISetup />);

        const windowsButton = screen.getByRole('button', { name: 'Windows' });
        await user.click(windowsButton);

        expect(screen.getByText(/iwr https:\/\/get\.saturn\.app\/install\.ps1 -useb \| iex/)).toBeInTheDocument();
        expect(screen.getByText(/install via powershell/i)).toBeInTheDocument();
    });

    it('renders Go alternative installation', () => {
        render(<CLISetup />);

        expect(screen.getByText(/alternative: install via go/i)).toBeInTheDocument();
        expect(screen.getByText(/go install github\.com\/saturn-platform\/saturn-cli\/saturn@latest/)).toBeInTheDocument();
    });

    it('renders verify installation section', () => {
        render(<CLISetup />);

        expect(screen.getByRole('heading', { name: /verify installation/i })).toBeInTheDocument();
        expect(screen.getByText(/check that the cli was installed correctly/i)).toBeInTheDocument();
        expect(screen.getByText('saturn --version')).toBeInTheDocument();
    });

    it('displays expected output for version check', () => {
        render(<CLISetup />);

        expect(screen.getByText(/expected output/i)).toBeInTheDocument();
        const versionTexts = screen.getAllByText(/saturn version 1\.4\.0/);
        expect(versionTexts.length).toBeGreaterThan(0);
    });

    it('renders authentication section', () => {
        render(<CLISetup />);

        expect(screen.getByRole('heading', { name: /authentication/i })).toBeInTheDocument();
        expect(screen.getByText(/connect the cli to your saturn account/i)).toBeInTheDocument();
    });

    it('displays context add command', () => {
        render(<CLISetup />);

        expect(screen.getByText(/add your saturn instance with an api token/i)).toBeInTheDocument();
        expect(screen.getByText(/saves the connection to ~\/\.config\/saturn\/config\.json/i)).toBeInTheDocument();
    });

    it('displays context verify command', () => {
        render(<CLISetup />);

        expect(screen.getByText(/verify the connection/i)).toBeInTheDocument();
        expect(screen.getByText('saturn context verify')).toBeInTheDocument();
    });

    it('renders API token input', () => {
        render(<CLISetup />);

        expect(screen.getByPlaceholderText('Enter your API token')).toBeInTheDocument();
        expect(screen.getByText(/create a token in settings → api tokens/i)).toBeInTheDocument();
    });

    it('renders next steps section', () => {
        render(<CLISetup />);

        expect(screen.getByRole('heading', { name: /next steps/i })).toBeInTheDocument();
        expect(screen.getByText(/get started with the saturn cli/i)).toBeInTheDocument();
    });

    it('links to commands page', () => {
        render(<CLISetup />);

        const commandsLink = screen.getByRole('link', { name: /view all commands.*explore available cli commands/i });
        expect(commandsLink).toBeInTheDocument();
        expect(commandsLink).toHaveAttribute('href', '/cli/commands');
    });

    it('links to token settings page', () => {
        render(<CLISetup />);

        const tokenLink = screen.getByRole('link', { name: /create api token.*generate a token for ci\/cd pipelines/i });
        expect(tokenLink).toBeInTheDocument();
        expect(tokenLink).toHaveAttribute('href', '/settings/tokens');
    });

    it('displays expected version in expected output', () => {
        render(<CLISetup />);

        const codeElements = screen.getAllByText('brew install saturn-cli');
        expect(codeElements.length).toBeGreaterThan(0);
    });
});
