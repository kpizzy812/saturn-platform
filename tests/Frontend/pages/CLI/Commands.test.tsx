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

        const contextAddElements = screen.getAllByText(/saturn context add/);
        expect(contextAddElements.length).toBeGreaterThan(0);
        const deployElements = screen.getAllByText(/saturn deploy uuid/);
        expect(deployElements.length).toBeGreaterThan(0);
        const appListElements = screen.getAllByText(/saturn app list/);
        expect(appListElements.length).toBeGreaterThan(0);
        const serviceCreateElements = screen.getAllByText(/saturn service create/);
        expect(serviceCreateElements.length).toBeGreaterThan(0);
    });

    it('renders context category', () => {
        render(<CLICommands />);

        const contextHeaders = screen.getAllByRole('heading', { name: /^context$/i });
        expect(contextHeaders.length).toBeGreaterThan(0);
    });

    it('renders applications category', () => {
        render(<CLICommands />);

        const appHeaders = screen.getAllByRole('heading', { name: /applications/i });
        expect(appHeaders.length).toBeGreaterThan(0);
    });

    it('renders deployments category', () => {
        render(<CLICommands />);

        const deployHeaders = screen.getAllByRole('heading', { name: /deployments/i });
        expect(deployHeaders.length).toBeGreaterThan(0);
    });

    it('renders services category', () => {
        render(<CLICommands />);

        const serviceHeaders = screen.getAllByRole('heading', { name: /services/i });
        expect(serviceHeaders.length).toBeGreaterThan(0);
    });

    it('renders global flags section', () => {
        render(<CLICommands />);

        expect(screen.getByRole('heading', { name: /global flags/i })).toBeInTheDocument();
        expect(screen.getByText(/--token/)).toBeInTheDocument();
        expect(screen.getByText(/--context/)).toBeInTheDocument();
        expect(screen.getByText(/--format table\|json\|pretty/)).toBeInTheDocument();
        expect(screen.getByText(/--debug/)).toBeInTheDocument();
    });

    it('expands command details when clicked', async () => {
        const { user } = render(<CLICommands />);

        const contextButton = screen.getByRole('button', { name: /saturn context add.*add a new saturn instance connection/i });
        await user.click(contextButton);

        expect(screen.getByText(/^usage$/i)).toBeInTheDocument();
    });

    it('collapses command details when clicked again', async () => {
        const { user } = render(<CLICommands />);

        const contextButton = screen.getByRole('button', { name: /saturn context add.*add a new saturn instance connection/i });

        await user.click(contextButton);
        expect(screen.getByText(/^usage$/i)).toBeInTheDocument();

        await user.click(contextButton);
        expect(contextButton).toBeInTheDocument();
    });

    it('filters commands by search query', async () => {
        const { user } = render(<CLICommands />);

        const searchInput = screen.getByPlaceholderText(/search commands/i);
        await user.type(searchInput, 'deploy');

        expect(screen.getByText(/deploy a resource by uuid/i)).toBeInTheDocument();
    });

    it('shows empty state when search has no results', async () => {
        const { user } = render(<CLICommands />);

        const searchInput = screen.getByPlaceholderText(/search commands/i);
        await user.type(searchInput, 'nonexistentcommand123');

        expect(screen.getByRole('heading', { name: /quick reference/i })).toBeInTheDocument();
    });

    it('displays command descriptions for key commands', () => {
        render(<CLICommands />);

        const instanceElements = screen.getAllByText(/add a new saturn instance connection/i);
        expect(instanceElements.length).toBeGreaterThan(0);
        expect(screen.getByText(/deploy a resource by uuid/i)).toBeInTheDocument();
        const appListDescs = screen.getAllByText(/list all applications/i);
        expect(appListDescs.length).toBeGreaterThan(0);
        expect(screen.getByText(/create a one-click service/i)).toBeInTheDocument();
    });

    it('shows command count badges', () => {
        render(<CLICommands />);

        const badges = screen.getAllByText(/\d+/);
        expect(badges.length).toBeGreaterThan(0);
    });
});
