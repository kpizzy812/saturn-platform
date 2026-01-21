import * as React from 'react';
import { Link } from '@inertiajs/react';
import { Dialog, Transition, Combobox } from '@headlessui/react';
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
    LogOut,
    FileCode,
    Terminal,
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
    { id: 'databases', name: 'Databases', icon: <Database className="h-4 w-4" />, href: '/databases', group: 'navigation' },
    { id: 'activity', name: 'Activity', icon: <Activity className="h-4 w-4" />, href: '/activity', group: 'navigation' },

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

interface CommandPaletteProps {
    open: boolean;
    onClose: () => void;
}

export function CommandPalette({ open, onClose }: CommandPaletteProps) {
    const [query, setQuery] = React.useState('');

    const filteredCommands = query === ''
        ? commands
        : commands.filter((command) =>
            command.name.toLowerCase().includes(query.toLowerCase()) ||
            command.description?.toLowerCase().includes(query.toLowerCase())
        );

    const groupedCommands = filteredCommands.reduce((acc, command) => {
        if (!acc[command.group]) {
            acc[command.group] = [];
        }
        acc[command.group].push(command);
        return acc;
    }, {} as Record<string, CommandItem[]>);

    const handleSelect = (command: CommandItem) => {
        if (command.action) {
            command.action();
        }
        onClose();
        setQuery('');
    };

    return (
        <Transition appear show={open} as={React.Fragment}>
            <Dialog as="div" className="relative z-50" onClose={onClose}>
                <Transition.Child
                    as={React.Fragment}
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/50" />
                </Transition.Child>

                <div className="fixed inset-0 overflow-y-auto pt-[20vh]">
                    <Transition.Child
                        as={React.Fragment}
                        enter="ease-out duration-200"
                        enterFrom="opacity-0 scale-95"
                        enterTo="opacity-100 scale-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100 scale-100"
                        leaveTo="opacity-0 scale-95"
                    >
                        <Dialog.Panel className="mx-auto max-w-xl">
                            <Combobox onChange={handleSelect}>
                                <div className="overflow-hidden rounded-xl border border-border bg-background-secondary shadow-2xl shadow-black/40">
                                    {/* Search Input */}
                                    <div className="flex items-center gap-3 border-b border-border px-5 py-1">
                                        <Search className="h-5 w-5 text-foreground-muted" />
                                        <Combobox.Input
                                            className="h-12 w-full bg-transparent text-foreground placeholder-foreground-muted focus:outline-none"
                                            placeholder="Search commands..."
                                            value={query}
                                            onChange={(e) => setQuery(e.target.value)}
                                        />
                                        <kbd className="rounded-md bg-background-tertiary px-2.5 py-1 text-xs font-medium text-foreground-muted">
                                            ESC
                                        </kbd>
                                    </div>

                                    {/* Results */}
                                    <Combobox.Options static className="max-h-80 overflow-y-auto p-2">
                                        {filteredCommands.length === 0 && query !== '' ? (
                                            <div className="px-4 py-10 text-center text-foreground-muted">
                                                No commands found for "{query}"
                                            </div>
                                        ) : (
                                            Object.entries(groupedCommands).map(([group, items]) => (
                                                <div key={group} className="mb-2">
                                                    <div className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-foreground-subtle">
                                                        {groupLabels[group]}
                                                    </div>
                                                    {items.map((command) => (
                                                        <Combobox.Option
                                                            key={command.id}
                                                            value={command}
                                                            className={({ active }) =>
                                                                cn(
                                                                    'flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 transition-colors duration-100',
                                                                    active ? 'bg-background-tertiary' : ''
                                                                )
                                                            }
                                                        >
                                                            {({ active }) => (
                                                                <>
                                                                    {command.href ? (
                                                                        <Link
                                                                            href={command.href}
                                                                            className="flex w-full items-center gap-3"
                                                                            onClick={onClose}
                                                                        >
                                                                            <span className="text-foreground-muted">{command.icon}</span>
                                                                            <div className="flex-1">
                                                                                <div className="text-sm text-foreground">{command.name}</div>
                                                                                {command.description && (
                                                                                    <div className="text-xs text-foreground-muted">
                                                                                        {command.description}
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        </Link>
                                                                    ) : (
                                                                        <>
                                                                            <span className="text-foreground-muted">{command.icon}</span>
                                                                            <div className="flex-1">
                                                                                <div className="text-sm text-foreground">{command.name}</div>
                                                                                {command.description && (
                                                                                    <div className="text-xs text-foreground-muted">
                                                                                        {command.description}
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        </>
                                                                    )}
                                                                </>
                                                            )}
                                                        </Combobox.Option>
                                                    ))}
                                                </div>
                                            ))
                                        )}
                                    </Combobox.Options>

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
                            </Combobox>
                        </Dialog.Panel>
                    </Transition.Child>
                </div>
            </Dialog>
        </Transition>
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
