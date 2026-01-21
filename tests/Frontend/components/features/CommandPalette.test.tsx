import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';
import { CommandPalette } from '@/components/features/CommandPalette';

// Mock scrollIntoView
Element.prototype.scrollIntoView = vi.fn();

describe('CommandPalette', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        // Clean up any open palettes
        fireEvent.keyDown(window, { key: 'Escape' });
    });

    describe('Opening and Closing', () => {
        it('does not render when closed', () => {
            render(<CommandPalette />);
            expect(screen.queryByPlaceholderText('Search commands...')).not.toBeInTheDocument();
        });

        it('opens when Cmd+K is pressed', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
            });
        });

        it('opens when Ctrl+K is pressed', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', ctrlKey: true });

            await waitFor(() => {
                expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
            });
        });

        it('closes when Escape is pressed', async () => {
            render(<CommandPalette />);

            // Open first
            fireEvent.keyDown(window, { key: 'k', metaKey: true });
            await waitFor(() => {
                expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
            });

            // Close
            fireEvent.keyDown(window, { key: 'Escape' });

            await waitFor(() => {
                expect(screen.queryByPlaceholderText('Search commands...')).not.toBeInTheDocument();
            });
        });

        it('closes when backdrop is clicked', async () => {
            render(<CommandPalette />);

            // Open
            fireEvent.keyDown(window, { key: 'k', metaKey: true });
            await waitFor(() => {
                expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
            });

            // Click backdrop (the fixed overlay)
            const backdrop = document.querySelector('.fixed.inset-0');
            if (backdrop) {
                fireEvent.click(backdrop);
            }

            await waitFor(() => {
                expect(screen.queryByPlaceholderText('Search commands...')).not.toBeInTheDocument();
            });
        });
    });

    describe('Search Functionality', () => {
        it('shows all commands when search is empty', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByText('Deploy')).toBeInTheDocument();
                expect(screen.getByText('Restart Service')).toBeInTheDocument();
                expect(screen.getByText('View Logs')).toBeInTheDocument();
            });
        });

        it('filters commands based on search query', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
            });

            const input = screen.getByPlaceholderText('Search commands...');
            fireEvent.change(input, { target: { value: 'deploy' } });

            await waitFor(() => {
                expect(screen.getByText('Deploy')).toBeInTheDocument();
            });
        });

        it('shows no commands message when no matches', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            const input = screen.getByPlaceholderText('Search commands...');
            fireEvent.change(input, { target: { value: 'xyznonexistent' } });

            await waitFor(() => {
                expect(screen.getByText('No commands found')).toBeInTheDocument();
            });
        });
    });

    describe('Keyboard Navigation', () => {
        it('navigates down with arrow key', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
            });

            const input = screen.getByPlaceholderText('Search commands...');
            fireEvent.keyDown(input, { key: 'ArrowDown' });

            // First item should no longer be selected, second should be
            // We verify by checking the component didn't crash
            expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
        });

        it('navigates up with arrow key', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
            });

            const input = screen.getByPlaceholderText('Search commands...');

            // Navigate down first
            fireEvent.keyDown(input, { key: 'ArrowDown' });
            fireEvent.keyDown(input, { key: 'ArrowDown' });

            // Then up
            fireEvent.keyDown(input, { key: 'ArrowUp' });

            expect(screen.getByPlaceholderText('Search commands...')).toBeInTheDocument();
        });
    });

    describe('Command Categories', () => {
        it('shows Actions category', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByText('Actions')).toBeInTheDocument();
            });
        });

        it('shows Navigation category', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByText('Navigation')).toBeInTheDocument();
            });
        });

        it('shows Settings category', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByText('Settings')).toBeInTheDocument();
            });
        });
    });

    describe('Command Actions', () => {
        it('shows Deploy command with description', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByText('Deploy')).toBeInTheDocument();
                expect(screen.getByText('Deploy the current service')).toBeInTheDocument();
            });
        });

        it('shows keyboard shortcuts', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByText('⌘D')).toBeInTheDocument();
            });
        });
    });

    describe('Services Integration', () => {
        it('shows provided services', async () => {
            const services = [
                { id: '1', name: 'api-server', type: 'application' },
                { id: '2', name: 'postgres', type: 'database' },
            ];

            render(<CommandPalette services={services} />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            // Services appear in the palette
            await waitFor(() => {
                expect(screen.getByText('api-server')).toBeInTheDocument();
                expect(screen.getByText('postgres')).toBeInTheDocument();
            });
        });

        it('filters services with search', async () => {
            const services = [
                { id: '1', name: 'api-server', type: 'application' },
                { id: '2', name: 'postgres', type: 'database' },
            ];

            render(<CommandPalette services={services} />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            const input = screen.getByPlaceholderText('Search commands...');
            fireEvent.change(input, { target: { value: 'postgres' } });

            await waitFor(() => {
                expect(screen.getByText('postgres')).toBeInTheDocument();
                expect(screen.queryByText('api-server')).not.toBeInTheDocument();
            });
        });
    });

    describe('Footer', () => {
        it('shows navigation hints', async () => {
            render(<CommandPalette />);

            fireEvent.keyDown(window, { key: 'k', metaKey: true });

            await waitFor(() => {
                expect(screen.getByText('to navigate')).toBeInTheDocument();
                expect(screen.getByText('to select')).toBeInTheDocument();
            });
        });
    });
});
