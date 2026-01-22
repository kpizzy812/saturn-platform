import * as React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { HelpCircle, Bell, ChevronDown, Settings, LogOut, Moon, Sun, FileText, Headphones, Plus, Search, Command, PanelLeftClose, PanelLeft } from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';
import { useSidebar } from '@/contexts/SidebarContext';

interface HeaderProps {
    showNewProject?: boolean;
    onCommandPalette?: () => void;
}

export function Header({ showNewProject = true, onCommandPalette }: HeaderProps) {
    const { props } = usePage();
    const user = props.auth?.user as { name?: string; email?: string } | undefined;
    const [isDark, setIsDark] = React.useState(true);
    const { isExpanded, toggleSidebar } = useSidebar();

    // Detect OS for keyboard shortcut display
    const isMac = typeof window !== 'undefined' && navigator.platform.toUpperCase().indexOf('MAC') >= 0;

    return (
        <header className="flex h-14 items-center justify-between border-b border-border bg-background px-4">
            {/* Left: Sidebar toggle + Search */}
            <div className="flex items-center gap-2">
                {/* Sidebar Toggle */}
                <button
                    onClick={toggleSidebar}
                    className="rounded-lg p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-secondary hover:text-foreground"
                    title={isExpanded ? 'Collapse sidebar' : 'Expand sidebar'}
                >
                    {isExpanded ? (
                        <PanelLeftClose className="h-5 w-5" />
                    ) : (
                        <PanelLeft className="h-5 w-5" />
                    )}
                </button>

                {/* Command Palette Trigger */}
                <button
                    onClick={onCommandPalette}
                    className="hidden items-center gap-3 rounded-lg border border-border bg-background-secondary px-4 py-2 text-sm text-foreground-muted transition-all duration-200 hover:border-border/80 hover:bg-background-tertiary hover:text-foreground md:flex"
                >
                    <Search className="h-4 w-4" />
                    <span>Search...</span>
                    <kbd className="ml-6 flex items-center gap-1 rounded-md bg-background-tertiary px-2 py-1 text-xs font-medium">
                        {isMac ? <Command className="h-3 w-3" /> : <span>Ctrl</span>}
                        <span>K</span>
                    </kbd>
                </button>
            </div>

            {/* Right: Actions */}
            <div className="flex items-center gap-1">
                {/* Mobile search button */}
                <button
                    onClick={onCommandPalette}
                    className="rounded-lg p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-secondary hover:text-foreground md:hidden"
                >
                    <Search className="h-5 w-5" />
                </button>

                {showNewProject && (
                    <Link
                        href="/projects/create"
                        className="flex items-center gap-2 rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-foreground/90 hover:shadow-md"
                    >
                        <Plus className="h-4 w-4" />
                        <span>New</span>
                    </Link>
                )}

                {/* Help */}
                <Dropdown>
                    <DropdownTrigger>
                        <button className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-foreground-muted transition-all duration-200 hover:bg-background-secondary hover:text-foreground">
                            <HelpCircle className="h-4 w-4" />
                            <span className="hidden sm:inline">Help</span>
                        </button>
                    </DropdownTrigger>
                    <DropdownContent align="right">
                        <DropdownItem>
                            <FileText className="h-4 w-4" />
                            Documentation
                        </DropdownItem>
                        <DropdownItem>
                            <Headphones className="h-4 w-4" />
                            Support
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>

                {/* Notifications */}
                <Link href="/notifications">
                    <button className="relative rounded-lg p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-secondary hover:text-foreground">
                        <Bell className="h-5 w-5" />
                        {/* Notification dot */}
                        <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-primary" />
                    </button>
                </Link>

                {/* User Menu */}
                <Dropdown>
                    <DropdownTrigger>
                        <button className="flex items-center gap-2 rounded-lg p-2 transition-all duration-200 hover:bg-background-secondary">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white shadow-sm">
                                {user?.name?.charAt(0).toUpperCase() || 'U'}
                            </div>
                            <ChevronDown className="h-4 w-4 text-foreground-muted transition-transform duration-200" />
                        </button>
                    </DropdownTrigger>
                    <DropdownContent align="right" className="w-56">
                        <div className="px-3 py-2">
                            <p className="text-sm font-medium text-foreground">{user?.name || 'User'}</p>
                            <p className="text-xs text-foreground-muted">{user?.email || 'user@example.com'}</p>
                        </div>
                        <DropdownDivider />
                        <DropdownItem>
                            <Settings className="h-4 w-4" />
                            Account Settings
                        </DropdownItem>
                        <DropdownItem>
                            <Settings className="h-4 w-4" />
                            Workspace Settings
                        </DropdownItem>
                        <DropdownDivider />
                        <DropdownItem onClick={() => setIsDark(!isDark)}>
                            {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
                            {isDark ? 'Light Theme' : 'Dark Theme'}
                        </DropdownItem>
                        <DropdownDivider />
                        <DropdownItem>
                            <LogOut className="h-4 w-4" />
                            Logout
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>
            </div>
        </header>
    );
}
