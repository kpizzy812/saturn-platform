import { useState, useEffect, useCallback, useRef } from 'react';
import {
    Search,
    Play,
    RefreshCw,
    Settings,
    Database,
    Box,
    GitBranch,
    Terminal,
    Eye,
    Plus,
    Layers,
    Globe,
    Key,
    Users,
    HardDrive,
    ArrowRight,
    Command,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface CommandItem {
    id: string;
    title: string;
    description?: string;
    icon: React.ReactNode;
    action?: () => void;
    href?: string;
    category: 'actions' | 'navigation' | 'services' | 'settings';
    shortcut?: string;
}

interface CommandPaletteProps {
    services?: Array<{ name: string; type: string; id: string }>;
    onDeploy?: () => void;
    onRestart?: () => void;
    onViewLogs?: () => void;
    onAddService?: () => void;
    onAddDatabase?: () => void;
    onAddTemplate?: () => void;
}

export function CommandPalette({
    services = [],
    onDeploy,
    onRestart,
    onViewLogs,
    onAddService,
    onAddDatabase,
    onAddTemplate,
}: CommandPaletteProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);

    // Define all commands
    const commands: CommandItem[] = [
        // Actions
        {
            id: 'deploy',
            title: 'Deploy',
            description: 'Deploy the current service',
            icon: <Play className="h-4 w-4" />,
            category: 'actions',
            shortcut: '⌘D',
            action: onDeploy,
        },
        {
            id: 'restart',
            title: 'Restart Service',
            description: 'Restart the selected service',
            icon: <RefreshCw className="h-4 w-4" />,
            category: 'actions',
            shortcut: '⌘R',
            action: onRestart,
        },
        {
            id: 'view-logs',
            title: 'View Logs',
            description: 'Open deployment logs',
            icon: <Terminal className="h-4 w-4" />,
            category: 'actions',
            shortcut: '⌘L',
            action: onViewLogs,
        },
        {
            id: 'add-service',
            title: 'Add Service',
            description: 'Add a new service to this project',
            icon: <Plus className="h-4 w-4" />,
            category: 'actions',
            shortcut: '⌘N',
            action: onAddService,
            href: onAddService ? undefined : '/services/create',
        },
        {
            id: 'add-database',
            title: 'Add Database',
            description: 'Add a new database',
            icon: <Database className="h-4 w-4" />,
            category: 'actions',
            action: onAddDatabase,
            href: onAddDatabase ? undefined : '/databases/create',
        },
        {
            id: 'add-template',
            title: 'Deploy Template',
            description: 'Deploy from a template',
            icon: <Layers className="h-4 w-4" />,
            category: 'actions',
            action: onAddTemplate,
            href: onAddTemplate ? undefined : '/templates',
        },

        // Navigation
        {
            id: 'nav-dashboard',
            title: 'Go to Dashboard',
            description: 'View all projects',
            icon: <Box className="h-4 w-4" />,
            category: 'navigation',
            href: '/dashboard',
        },
        {
            id: 'nav-settings',
            title: 'Project Settings',
            description: 'Configure project settings',
            icon: <Settings className="h-4 w-4" />,
            category: 'navigation',
            shortcut: '⌘,',
        },
        {
            id: 'nav-variables',
            title: 'Environment Variables',
            description: 'Manage environment variables',
            icon: <Key className="h-4 w-4" />,
            category: 'navigation',
        },
        {
            id: 'nav-domains',
            title: 'Custom Domains',
            description: 'Manage domain settings',
            icon: <Globe className="h-4 w-4" />,
            category: 'navigation',
        },
        {
            id: 'nav-team',
            title: 'Team Settings',
            description: 'Manage team members',
            icon: <Users className="h-4 w-4" />,
            category: 'navigation',
            href: '/settings/team',
        },

        // Settings
        {
            id: 'settings-account',
            title: 'Account Settings',
            description: 'Manage your account',
            icon: <Settings className="h-4 w-4" />,
            category: 'settings',
            href: '/settings/account',
        },
        {
            id: 'settings-integrations',
            title: 'Integrations',
            description: 'Manage integrations',
            icon: <GitBranch className="h-4 w-4" />,
            category: 'settings',
            href: '/settings/integrations',
        },
        {
            id: 'settings-storage',
            title: 'Storage',
            description: 'Manage volumes and storage',
            icon: <HardDrive className="h-4 w-4" />,
            category: 'settings',
        },

        // Dynamic services
        ...services.map((service) => ({
            id: `service-${service.id}`,
            title: service.name,
            description: `Go to ${service.type}`,
            icon: service.type === 'database' ? <Database className="h-4 w-4" /> : <Box className="h-4 w-4" />,
            category: 'services' as const,
        })),
    ];

    // Filter commands based on search
    const filteredCommands = commands.filter((cmd) => {
        const searchLower = search.toLowerCase();
        return (
            cmd.title.toLowerCase().includes(searchLower) ||
            cmd.description?.toLowerCase().includes(searchLower)
        );
    });

    // Group commands by category
    const groupedCommands = {
        actions: filteredCommands.filter((c) => c.category === 'actions'),
        services: filteredCommands.filter((c) => c.category === 'services'),
        navigation: filteredCommands.filter((c) => c.category === 'navigation'),
        settings: filteredCommands.filter((c) => c.category === 'settings'),
    };

    // Get all visible commands in order
    const visibleCommands = [
        ...groupedCommands.actions,
        ...groupedCommands.services,
        ...groupedCommands.navigation,
        ...groupedCommands.settings,
    ];

    // Handle keyboard shortcuts
    const handleKeyDown = useCallback(
        (e: KeyboardEvent) => {
            // Open palette with Cmd+K or Ctrl+K
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setIsOpen(true);
                setSearch('');
                setSelectedIndex(0);
            }

            // Close with Escape
            if (e.key === 'Escape') {
                setIsOpen(false);
            }
        },
        []
    );

    // Handle navigation within palette
    const handlePaletteKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setSelectedIndex((prev) => Math.min(prev + 1, visibleCommands.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setSelectedIndex((prev) => Math.max(prev - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const selected = visibleCommands[selectedIndex];
            if (selected) {
                executeCommand(selected);
            }
        }
    };

    const executeCommand = (command: CommandItem) => {
        if (command.action) {
            command.action();
        }
        if (command.href) {
            window.location.href = command.href;
        }
        setIsOpen(false);
    };

    // Attach global keyboard listener
    useEffect(() => {
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [handleKeyDown]);

    // Focus input when opened
    useEffect(() => {
        if (isOpen && inputRef.current) {
            inputRef.current.focus();
        }
    }, [isOpen]);

    // Scroll selected item into view
    useEffect(() => {
        if (listRef.current) {
            const selectedElement = listRef.current.querySelector(`[data-index="${selectedIndex}"]`);
            if (selectedElement) {
                selectedElement.scrollIntoView({ block: 'nearest' });
            }
        }
    }, [selectedIndex]);

    // Reset selected index when search changes
    useEffect(() => {
        setSelectedIndex(0);
    }, [search]);

    if (!isOpen) return null;

    const renderCategory = (title: string, items: CommandItem[], startIndex: number) => {
        if (items.length === 0) return null;

        return (
            <div key={title}>
                <div className="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-foreground-subtle">
                    {title}
                </div>
                {items.map((cmd, idx) => {
                    const globalIndex = startIndex + idx;
                    return (
                        <button
                            key={cmd.id}
                            data-index={globalIndex}
                            onClick={() => executeCommand(cmd)}
                            onMouseEnter={() => setSelectedIndex(globalIndex)}
                            className={cn(
                                'flex w-full items-center gap-3 px-3 py-2 text-left transition-colors',
                                globalIndex === selectedIndex
                                    ? 'bg-primary/10 text-foreground'
                                    : 'text-foreground-muted hover:bg-background-secondary hover:text-foreground'
                            )}
                        >
                            <span className={cn(
                                'flex h-8 w-8 items-center justify-center rounded-md',
                                globalIndex === selectedIndex
                                    ? 'bg-primary text-white'
                                    : 'bg-background-secondary text-foreground-muted'
                            )}>
                                {cmd.icon}
                            </span>
                            <div className="flex-1 min-w-0">
                                <p className="font-medium">{cmd.title}</p>
                                {cmd.description && (
                                    <p className="text-sm text-foreground-subtle truncate">{cmd.description}</p>
                                )}
                            </div>
                            {cmd.shortcut && (
                                <kbd className="hidden sm:flex items-center gap-1 rounded bg-background-secondary px-2 py-1 text-xs text-foreground-muted">
                                    {cmd.shortcut}
                                </kbd>
                            )}
                            <ArrowRight className="h-4 w-4 text-foreground-subtle" />
                        </button>
                    );
                })}
            </div>
        );
    };

    let currentIndex = 0;

    return (
        <>
            {/* Backdrop */}
            <div
                className="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm"
                onClick={() => setIsOpen(false)}
            />

            {/* Palette */}
            <div className="fixed left-1/2 top-[20%] z-50 w-full max-w-xl -translate-x-1/2 rounded-xl border border-border bg-background shadow-2xl">
                {/* Search Input */}
                <div className="flex items-center gap-3 border-b border-border px-4 py-3">
                    <Search className="h-5 w-5 text-foreground-muted" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={handlePaletteKeyDown}
                        placeholder="Search commands..."
                        className="flex-1 bg-transparent text-foreground placeholder:text-foreground-muted focus:outline-none"
                    />
                    <kbd className="hidden sm:flex items-center gap-1 rounded bg-background-secondary px-2 py-1 text-xs text-foreground-muted">
                        ESC
                    </kbd>
                </div>

                {/* Command List */}
                <div ref={listRef} className="max-h-80 overflow-y-auto py-2">
                    {visibleCommands.length === 0 ? (
                        <div className="px-4 py-8 text-center text-foreground-muted">
                            <p>No commands found</p>
                            <p className="mt-1 text-sm text-foreground-subtle">Try a different search term</p>
                        </div>
                    ) : (
                        <>
                            {groupedCommands.actions.length > 0 && (
                                <>
                                    {renderCategory('Actions', groupedCommands.actions, currentIndex)}
                                    {(currentIndex += groupedCommands.actions.length)}
                                </>
                            )}
                            {groupedCommands.services.length > 0 && (
                                <>
                                    {renderCategory('Services', groupedCommands.services, currentIndex)}
                                    {(currentIndex += groupedCommands.services.length)}
                                </>
                            )}
                            {groupedCommands.navigation.length > 0 && (
                                <>
                                    {renderCategory('Navigation', groupedCommands.navigation, currentIndex)}
                                    {(currentIndex += groupedCommands.navigation.length)}
                                </>
                            )}
                            {groupedCommands.settings.length > 0 && (
                                <>
                                    {renderCategory('Settings', groupedCommands.settings, currentIndex)}
                                </>
                            )}
                        </>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-between border-t border-border px-4 py-2 text-xs text-foreground-subtle">
                    <div className="flex items-center gap-4">
                        <span className="flex items-center gap-1">
                            <kbd className="rounded bg-background-secondary px-1.5 py-0.5">↑</kbd>
                            <kbd className="rounded bg-background-secondary px-1.5 py-0.5">↓</kbd>
                            <span>to navigate</span>
                        </span>
                        <span className="flex items-center gap-1">
                            <kbd className="rounded bg-background-secondary px-1.5 py-0.5">↵</kbd>
                            <span>to select</span>
                        </span>
                    </div>
                    <div className="flex items-center gap-1">
                        <Command className="h-3 w-3" />
                        <span>K to open</span>
                    </div>
                </div>
            </div>
        </>
    );
}

export default CommandPalette;
