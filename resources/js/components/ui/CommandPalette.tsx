import * as React from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { useSearch } from '@/hooks/useSearch';
import { usePaletteBrowse, type BrowseItem } from '@/hooks/usePaletteBrowse';
import type { RecentResource } from '@/hooks/useRecentResources';
import type { FavoriteResource } from '@/hooks/useResourceFrequency';
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
    ArrowRightLeft,
    GitBranch,
    Clock,
    Loader2,
    Star,
    ChevronRight,
} from 'lucide-react';

export interface CommandItem {
    id: string;
    name: string;
    description?: string;
    context?: string;
    icon: React.ReactNode;
    href?: string;
    action?: () => void;
    group: string;
    has_children?: boolean;
    child_type?: string;
    parent_uuid?: string;
}

interface DrillDownLevel {
    type: string;
    label: string;
    parentUuid?: string;
}

const commands: CommandItem[] = [
    // Navigation (drillable)
    { id: 'dashboard', name: 'Dashboard', icon: <FolderKanban className="h-4 w-4" />, href: '/dashboard', group: 'navigation', has_children: true, child_type: 'projects' },
    { id: 'servers', name: 'Servers', icon: <Server className="h-4 w-4" />, href: '/servers', group: 'navigation', has_children: true, child_type: 'servers' },
    { id: 'applications', name: 'Applications', icon: <Layers className="h-4 w-4" />, href: '/applications', group: 'navigation', has_children: true, child_type: 'applications' },
    { id: 'services', name: 'Services', icon: <Box className="h-4 w-4" />, href: '/services', group: 'navigation', has_children: true, child_type: 'services' },
    { id: 'databases', name: 'Databases', icon: <Database className="h-4 w-4" />, href: '/databases', group: 'navigation', has_children: true, child_type: 'databases' },
    { id: 'activity', name: 'Activity', icon: <Activity className="h-4 w-4" />, href: '/activity', group: 'navigation' },
    { id: 'approvals', name: 'Approvals', description: 'Pending deployment, migration, and transfer approvals', icon: <ClipboardCheck className="h-4 w-4" />, href: '/approvals', group: 'navigation' },
    { id: 'transfers', name: 'Transfer History', description: 'View resource transfer history', icon: <ArrowRightLeft className="h-4 w-4" />, href: '/transfers', group: 'navigation' },
    { id: 'migrations', name: 'Migrations', description: 'Environment migrations (dev \u2192 uat \u2192 prod)', icon: <GitBranch className="h-4 w-4" />, href: '/migrations', group: 'navigation' },

    // Actions
    { id: 'new-project', name: 'New Project', description: 'Create a new project', icon: <Plus className="h-4 w-4" />, href: '/projects/create', group: 'actions' },
    { id: 'new-service', name: 'New Service', description: 'Add a new service to a project', icon: <Plus className="h-4 w-4" />, group: 'actions' },
    { id: 'new-database', name: 'New Database', description: 'Create a new database', icon: <Database className="h-4 w-4" />, group: 'actions' },
    { id: 'deploy', name: 'Deploy', description: 'Trigger a deployment', icon: <Terminal className="h-4 w-4" />, group: 'actions' },

    // Settings
    { id: 'settings', name: 'Settings', icon: <Settings className="h-4 w-4" />, href: '/settings', group: 'settings' },
    { id: 'team', name: 'Team', icon: <Users className="h-4 w-4" />, href: '/settings/team', group: 'settings' },
];

const RESOURCE_ICONS: Record<string, React.ReactNode> = {
    project: <FolderKanban className="h-4 w-4" />,
    server: <Server className="h-4 w-4" />,
    application: <Layers className="h-4 w-4" />,
    database: <Database className="h-4 w-4" />,
    service: <Box className="h-4 w-4" />,
};

const groupLabels: Record<string, string> = {
    favorites: 'Favorites',
    recent: 'Recent',
    resources: 'Resources',
    navigation: 'Navigate',
    actions: 'Actions',
    settings: 'Settings',
};

const groupOrder = ['favorites', 'recent', 'resources', 'navigation', 'actions', 'settings'];

function browseItemToCommand(item: BrowseItem): CommandItem {
    const metaType = item.meta?.type;
    const icon = metaType ? (RESOURCE_ICONS[metaType] || <Box className="h-4 w-4" />) : <FolderKanban className="h-4 w-4" />;
    const context = item.meta?.project && item.meta?.environment
        ? `${item.meta.project} / ${item.meta.environment}`
        : undefined;

    return {
        id: `browse-${item.id}`,
        name: item.name,
        description: item.description || undefined,
        context,
        icon,
        href: item.href,
        group: 'navigation',
        has_children: item.has_children,
        child_type: item.child_type || undefined,
        parent_uuid: item.id,
    };
}

interface CommandPaletteProps {
    open: boolean;
    onClose: () => void;
    recentItems?: RecentResource[];
    favorites?: FavoriteResource[];
}

export function CommandPalette({ open, onClose, recentItems = [], favorites = [] }: CommandPaletteProps) {
    const [query, setQuery] = React.useState('');
    const [selectedIndex, setSelectedIndex] = React.useState(0);
    const [stack, setStack] = React.useState<DrillDownLevel[]>([]);
    const inputRef = React.useRef<HTMLInputElement>(null);
    const listRef = React.useRef<HTMLDivElement>(null);
    const { results: searchResults, isLoading: isSearching } = useSearch(open ? query : '');
    const { items: browseItems, isLoading: isBrowsing, fetchBrowse, clearCache } = usePaletteBrowse();

    const isInDrillDown = stack.length > 0;

    // Fetch browse data when navigating into a level
    React.useEffect(() => {
        if (!open || stack.length === 0) return;
        const current = stack[stack.length - 1];
        fetchBrowse(current.type, current.parentUuid);
    }, [open, stack, fetchBrowse]);

    // Build favorite items
    const favoriteCommandItems: CommandItem[] = React.useMemo(() => {
        if (query !== '' || isInDrillDown || favorites.length === 0) return [];
        return favorites.map((item) => ({
            id: `fav-${item.type}-${item.id}`,
            name: item.name,
            icon: <Star className="h-4 w-4" />,
            href: item.href,
            group: 'favorites',
            description: item.type.charAt(0).toUpperCase() + item.type.slice(1),
        }));
    }, [query, favorites, isInDrillDown]);

    // Build recent items as CommandItems
    const recentCommandItems: CommandItem[] = React.useMemo(() => {
        if (query !== '' || isInDrillDown || recentItems.length === 0) return [];
        return recentItems.map((item) => ({
            id: `recent-${item.type}-${item.uuid}`,
            name: item.name,
            description: item.type.charAt(0).toUpperCase() + item.type.slice(1),
            icon: <Clock className="h-4 w-4" />,
            href: item.href,
            group: 'recent',
        }));
    }, [query, recentItems, isInDrillDown]);

    // Build search results as CommandItems
    const searchCommandItems: CommandItem[] = React.useMemo(() => {
        if (query.length < 2 || searchResults.length === 0) return [];
        return searchResults.map((item) => {
            const context = item.project_name && item.environment_name
                ? `${item.project_name} / ${item.environment_name}`
                : item.project_name || undefined;
            return {
                id: `search-${item.type}-${item.uuid}`,
                name: item.name,
                description: item.description || (item.type.charAt(0).toUpperCase() + item.type.slice(1)),
                context,
                icon: RESOURCE_ICONS[item.type] || <Box className="h-4 w-4" />,
                href: item.href,
                group: 'resources',
            };
        });
    }, [query, searchResults]);

    // Build items for drill-down mode
    const drillDownCommandItems: CommandItem[] = React.useMemo(() => {
        if (!isInDrillDown) return [];
        return browseItems.map(browseItemToCommand);
    }, [isInDrillDown, browseItems]);

    // Filter static commands by query (only in root mode)
    const filteredCommands = React.useMemo(() => {
        if (isInDrillDown) return [];
        if (query === '') return commands;
        return commands.filter((command) =>
            command.name.toLowerCase().includes(query.toLowerCase()) ||
            command.description?.toLowerCase().includes(query.toLowerCase()),
        );
    }, [query, isInDrillDown]);

    // Combine all items into groups
    const allItems = isInDrillDown
        ? drillDownCommandItems
        : [...favoriteCommandItems, ...recentCommandItems, ...searchCommandItems, ...filteredCommands];

    // Build grouped commands maintaining order
    const groupedCommands = isInDrillDown
        ? (drillDownCommandItems.length > 0 ? [{ group: 'navigation', items: drillDownCommandItems }] : [])
        : groupOrder.reduce((acc, group) => {
            const items = allItems.filter((c) => c.group === group);
            if (items.length > 0) {
                acc.push({ group, items });
            }
            return acc;
        }, [] as Array<{ group: string; items: CommandItem[] }>);

    // Flat list for keyboard navigation
    const flatItems = groupedCommands.flatMap((g) => g.items);

    const drillInto = React.useCallback((command: CommandItem) => {
        if (command.child_type) {
            setStack((prev) => [
                ...prev,
                { type: command.child_type!, label: command.name, parentUuid: command.parent_uuid },
            ]);
            setQuery('');
            setSelectedIndex(0);
        }
    }, []);

    const drillBack = React.useCallback(() => {
        setStack((prev) => {
            if (prev.length === 0) return prev;
            return prev.slice(0, -1);
        });
        setSelectedIndex(0);
    }, []);

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
        setStack([]);
    }, [onClose]);

    // Reset selected index when query changes
    React.useEffect(() => {
        setSelectedIndex(0);
    }, [query]);

    // Reset selected index when search results change
    React.useEffect(() => {
        setSelectedIndex(0);
    }, [searchResults]);

    // Focus input when opened, reset state
    React.useEffect(() => {
        if (open) {
            setQuery('');
            setSelectedIndex(0);
            setStack([]);
            clearCache();
            requestAnimationFrame(() => {
                inputRef.current?.focus();
            });
        }
    }, [open, clearCache]);

    // Scroll selected item into view
    React.useEffect(() => {
        if (listRef.current) {
            const el = listRef.current.querySelector(`[data-index="${selectedIndex}"]`);
            if (el) {
                el.scrollIntoView?.({ block: 'nearest' });
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
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            const selected = flatItems[selectedIndex];
            if (selected?.has_children && selected?.child_type) {
                drillInto(selected);
            }
        } else if (e.key === 'ArrowLeft' || (e.key === 'Backspace' && query === '')) {
            if (isInDrillDown) {
                e.preventDefault();
                drillBack();
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const selected = flatItems[selectedIndex];
            if (selected) {
                executeCommand(selected);
            }
        } else if (e.key === 'Escape') {
            e.preventDefault();
            if (isInDrillDown) {
                setStack([]);
                setSelectedIndex(0);
            } else {
                onClose();
            }
        }
    };

    if (!open) return null;

    let globalIndex = 0;
    const hasQuery = query.length >= 2;
    const isLoadingAny = isSearching || isBrowsing;
    const noResults = isInDrillDown
        ? !isBrowsing && drillDownCommandItems.length === 0
        : hasQuery && !isSearching && searchCommandItems.length === 0 && filteredCommands.length === 0;

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
                        {isLoadingAny ? (
                            <Loader2 className="h-5 w-5 animate-spin text-foreground-muted" />
                        ) : (
                            <Search className="h-5 w-5 text-foreground-muted" />
                        )}
                        <input
                            ref={inputRef}
                            type="text"
                            className="h-12 w-full bg-transparent text-foreground placeholder-foreground-muted focus:outline-none"
                            placeholder={isInDrillDown ? `Search in ${stack[stack.length - 1]?.label}...` : 'Search commands and resources...'}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={handleKeyDown}
                        />
                        <kbd className="rounded-md bg-background-tertiary px-2.5 py-1 text-xs font-medium text-foreground-muted">
                            ESC
                        </kbd>
                    </div>

                    {/* Breadcrumb */}
                    {isInDrillDown && (
                        <div className="flex items-center gap-1.5 border-b border-border px-5 py-2 text-xs text-foreground-muted">
                            <button
                                className="hover:text-foreground transition-colors"
                                onClick={() => { setStack([]); setSelectedIndex(0); }}
                            >
                                Commands
                            </button>
                            {stack.map((level, i) => (
                                <React.Fragment key={i}>
                                    <ChevronRight className="h-3 w-3" />
                                    <button
                                        className={cn(
                                            'transition-colors',
                                            i === stack.length - 1 ? 'text-foreground font-medium' : 'hover:text-foreground',
                                        )}
                                        onClick={() => {
                                            if (i < stack.length - 1) {
                                                setStack((prev) => prev.slice(0, i + 1));
                                                setSelectedIndex(0);
                                            }
                                        }}
                                    >
                                        {level.label}
                                    </button>
                                </React.Fragment>
                            ))}
                        </div>
                    )}

                    {/* Results */}
                    <div ref={listRef} className="max-h-80 overflow-y-auto p-2">
                        {noResults ? (
                            <div className="px-4 py-10 text-center text-foreground-muted">
                                {isInDrillDown
                                    ? 'No items found'
                                    : <>No results found for &ldquo;{query}&rdquo;</>
                                }
                            </div>
                        ) : (
                            groupedCommands.map(({ group, items }) => {
                                const startIndex = globalIndex;
                                const rendered = (
                                    <div key={group} className="mb-2">
                                        {!isInDrillDown && (
                                            <div className="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-foreground-subtle">
                                                {groupLabels[group]}
                                            </div>
                                        )}
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
                                                        itemIndex === selectedIndex ? 'bg-background-tertiary' : '',
                                                    )}
                                                >
                                                    <span className="text-foreground-muted">{command.icon}</span>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-sm text-foreground">{command.name}</span>
                                                            {command.context && (
                                                                <span className="truncate text-xs text-foreground-subtle">
                                                                    {command.context}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {command.description && (
                                                            <div className="truncate text-xs text-foreground-muted">
                                                                {command.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                    {command.has_children && (
                                                        <ChevronRight
                                                            className="h-4 w-4 shrink-0 text-foreground-subtle"
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                drillInto(command);
                                                            }}
                                                        />
                                                    )}
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
                                <kbd className="rounded-md bg-background-tertiary px-2 py-1 font-medium">&uarr;&darr;</kbd>
                                <span>navigate</span>
                            </span>
                            <span className="flex items-center gap-2">
                                <kbd className="rounded-md bg-background-tertiary px-2 py-1 font-medium">&crarr;</kbd>
                                <span>select</span>
                            </span>
                            <span className="flex items-center gap-2">
                                <kbd className="rounded-md bg-background-tertiary px-2 py-1 font-medium">&rarr;</kbd>
                                <span>drill in</span>
                            </span>
                            {isInDrillDown && (
                                <span className="flex items-center gap-2">
                                    <kbd className="rounded-md bg-background-tertiary px-2 py-1 font-medium">&larr;</kbd>
                                    <span>back</span>
                                </span>
                            )}
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
