import * as React from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import {
    Search,
    FolderKanban,
    Server,
    Database,
    Plus,
    Settings,
    Users,
    Activity,
    Terminal,
    Layers,
    Box,
    ClipboardCheck,
} from 'lucide-react';

interface CommandItem {
    id: string;
    name: string;
    description?: string;
    icon: React.ReactNode;
    href?: string;
    action?: () => void;
    group: 'navigation' | 'actions' | 'settings';
}

const commands: CommandItem[] = [
    // Navigation
    { id: 'dashboard', name: 'Dashboard', icon: <FolderKanban className="h-4 w-4" />, href: '/dashboard', group: 'navigation' },
    { id: 'projects', name: 'Projects', icon: <FolderKanban className="h-4 w-4" />, href: '/projects', group: 'navigation' },
    { id: 'servers', name: 'Servers', icon: <Server className="h-4 w-4" />, href: '/servers', group: 'navigation' },
    { id: 'applications', name: 'Applications', icon: <Layers className="h-4 w-4" />, href: '/applications', group: 'navigation' },
    { id: 'services', name: 'Services', icon: <Box className="h-4 w-4" />, href: '/services', group: 'navigation' },
    { id: 'databases', name: 'Databases', icon: <Database className="h-4 w-4" />, href: '/databases', group: 'navigation' },
    { id: 'activity', name: 'Activity', icon: <Activity className="h-4 w-4" />, href: '/activity', group: 'navigation' },
    { id: 'approvals', name: 'Approvals', description: 'Pending deployment approvals', icon: <ClipboardCheck className="h-4 w-4" />, href: '/approvals', group: 'navigation' },

    // Actions
    { id: 'new-project', name: 'New Project', description: 'Create a new project', icon: <Plus className="h-4 w-4" />, href: '/projects/create', group: 'actions' },
    { id: 'new-service', name: 'New Service', description: 'Add a new service to a project', icon: <Plus className="h-4 w-4" />, group: 'actions' },
    { id: 'new-database', name: 'New Database', description: 'Create a new database', icon: <Database className="h-4 w-4" />, group: 'actions' },
    { id: 'deploy', name: 'Deploy', description: 'Trigger a deployment', icon: <Terminal className="h-4 w-4" />, group: 'actions' },

    // Settings
    { id: 'settings', name: 'Settings', icon: <Settings className="h-4 w-4" />, href: '/settings', group: 'settings' },
    { id: 'team', name: 'Team', icon: <Users className="h-4 w-4" />, href: '/settings/team', group: 'settings' },
];

const groupLabels: Record<string, string> = {
    navigation: 'Navigate',
    actions: 'Actions',
    settings: 'Settings',
};

const groupOrder: Array<CommandItem['group']> = ['navigation', 'actions', 'settings'];

interface CommandPaletteProps {
    open: boolean;
    onClose: () => void;
}

export function CommandPalette({ open, onClose }: CommandPaletteProps) {
    const [query, setQuery] = React.useState('');
    const [selectedIndex, setSelectedIndex] = React.useState(0);
    const inputRef = React.useRef<HTMLInputElement>(null);
    const listRef = React.useRef<HTMLDivElement>(null);

    const filteredCommands = query === ''
        ? commands
        : commands.filter((command) =>
            command.name.toLowerCase().includes(query.toLowerCase()) ||
            command.description?.toLowerCase().includes(query.toLowerCase())
        );

    // Build grouped commands maintaining order
    const groupedCommands = groupOrder.reduce((acc, group) => {
        const items = filteredCommands.filter((c) => c.group === group);
        if (items.length > 0) {
            acc.push({ group, items });
        }
        return acc;
    }, [] as Array<{ group: string; items: CommandItem[] }>);

    // Flat list for keyboard navigation
    const flatItems = groupedCommands.flatMap((g) => g.items);

    const executeCommand = React.useCallback((command: CommandItem) => {
        if (command.action) {
            command.action();
        }
        if (command.href) {
            router.visit(command.href);
        }
        onClose();
        setQuery('');
        setSelectedIndex(0);
    }, [onClose]);

    // Reset selected index when query changes
    React.useEffect(() => {
        setSelectedIndex(0);
    }, [query]);

    // Focus input when opened, reset state
    React.useEffect(() => {
        if (open) {
            setQuery('');
            setSelectedIndex(0);
            // Small delay to ensure DOM is ready
            requestAnimationFrame(() => {
                inputRef.current?.focus();
            });
        }
    }, [open]);

    // Scroll selected item into view
    React.useEffect(() => {
        if (listRef.current) {
            const el = listRef.current.querySelector(`[data-index="${selectedIndex}"]`);
            if (el) {
                el.scrollIntoView({ block: 'nearest' });
            }
        }
    }, [selectedIndex]);

    // Handle keyboard navigation
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelectedIndex((prev) => Math.min(prev + 1, flatItems.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelectedIndex((prev) => Math.max(prev - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const selected = flatItems[selectedIndex];
            if (selected) {
                executeCommand(selected);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            onClose();
        }
    };

    if (!open) return null;

    let globalIndex = 0;

    return (
        <>
            {/* Backdrop */}
            <div
                className="fixed inset-0 z-50 bg-black/50"
                onClick={onClose}
            />

            {/* Palette */}
            <div className="fixed inset-0 z-50 overflow-y-auto pt-[20vh]" onClick={onClose}>
                <div
                    className="mx-auto max-w-xl overflow-hidden rounded-xl border border-border bg-background-secondary shadow-2xl shadow-black/40"
                    onClick={(e) => e.stopPropagation()}
                >
                    {/* Search Input */}
                    <div className="flex items-center gap-3 border-b border-border px-5 py-1">
                        <Search className="h-5 w-5 text-foreground-muted" />
                        <input
                            ref={inputRef}
                            type="text"
                            className="h-12 w-full bg-transparent text-foreground placeholder-foreground-muted focus:outline-none"
                            placeholder="Search commands..."
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={handleKeyDown}
                        />
                        <kbd className="rounded-md bg-background-tertiary px-2.5 py-1 text-xs font-medium text-foreground-muted">
                            ESC
                        </kbd>
                    </div>

                    {/* Results */}
                    <div ref={listRef} className="max-h-80 overflow-y-auto p-2">
                        {flatItems.length === 0 && query !== '' ? (
                            <div className="px-4 py-10 text-center text-foreground-muted">
                                No commands found for &ldquo;{query}&rdquo;
                            </div>
                        ) : (
                            groupedCommands.map(({ group, items }) => {
                                const startIndex = globalIndex;
                                const rendered = (
                                    <div key={group} className="mb-2">
                                        <div className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-foreground-subtle">
                                            {groupLabels[group]}
                                        </div>
                                        {items.map((command, idx) => {
                                            const itemIndex = startIndex + idx;
                                            return (
                                                <button
                                                    key={command.id}
                                                    data-index={itemIndex}
                                                    onClick={() => executeCommand(command)}
                                                    onMouseEnter={() => setSelectedIndex(itemIndex)}
                                                    className={cn(
                                                        'flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors duration-100',
                                                        itemIndex === selectedIndex ? 'bg-background-tertiary' : ''
                                                    )}
                                                >
                                                    <span className="text-foreground-muted">{command.icon}</span>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="text-sm text-foreground">{command.name}</div>
                                                        {command.description && (
                                                            <div className="text-xs text-foreground-muted">
                                                                {command.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                );
                                globalIndex += items.length;
                                return rendered;
                            })
                        )}
                    </div>

                    {/* Footer */}
                    <div className="flex items-center justify-between border-t border-border px-5 py-3 text-xs text-foreground-muted">
                        <div className="flex items-center gap-5">
                            <span className="flex items-center gap-2">
                                <kbd className="rounded-md bg-background-tertiary px-2 py-1 font-medium">↑↓</kbd>
                                <span>navigate</span>
                            </span>
                            <span className="flex items-center gap-2">
                                <kbd className="rounded-md bg-background-tertiary px-2 py-1 font-medium">↵</kbd>
                                <span>select</span>
                            </span>
                        </div>
                        <span className="text-foreground-subtle">Saturn</span>
                    </div>
                </div>
            </div>
        </>
    );
}

// Hook to manage command palette state
export function useCommandPalette() {
    const [isOpen, setIsOpen] = React.useState(false);

    React.useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setIsOpen((prev) => !prev);
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    return {
        isOpen,
        open: () => setIsOpen(true),
        close: () => setIsOpen(false),
        toggle: () => setIsOpen((prev) => !prev),
    };
}
