
import React from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { ChevronDown, Settings, Users, LogOut, Moon, Sun, Plus, Search, Command, BarChart3 } from 'lucide-react';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider, useDropdown } from '@/components/ui/Dropdown';
import { NotificationDropdown } from '@/components/ui/NotificationDropdown';
import { SaturnLogo } from '@/components/ui/SaturnLogo';
import { TeamSwitcher } from '@/components/ui/TeamSwitcher';
import { useTheme } from '@/components/ui/ThemeProvider';

const UserMenuButton = React.forwardRef<HTMLButtonElement, { user: { name?: string; email?: string; avatar?: string | null } | null | undefined }>(
    function UserMenuButton({ user, ...props }, ref) {
        const { isOpen } = useDropdown();
        return (
            <button ref={ref} {...props} className="group flex items-center gap-2 rounded-lg p-2 transition-all duration-200 hover:bg-background-secondary">
                {user?.avatar ? (
                    <img
                        src={user.avatar}
                        alt={user.name || 'User'}
                        className="h-8 w-8 rounded-full object-cover shadow-sm transition-transform duration-200 group-hover:scale-110"
                    />
                ) : (
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-500 text-sm font-medium text-white shadow-sm transition-transform duration-200 group-hover:scale-110">
                        {user?.name?.charAt(0).toUpperCase() || 'U'}
                    </div>
                )}
                <ChevronDown className={`h-4 w-4 text-foreground-muted transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
            </button>
        );
    }
);

interface HeaderProps {
    showNewProject?: boolean;
    onCommandPalette?: () => void;
}

export function Header({ showNewProject = true, onCommandPalette }: HeaderProps) {
    const { props } = usePage();
    const user = props.auth;
    const notificationsData = props.notifications;
    const { isDark, toggleTheme } = useTheme();

    // Detect OS for keyboard shortcut display
    const isMac = typeof window !== 'undefined' && navigator.platform.toUpperCase().indexOf('MAC') >= 0;

    return (
        <header className="flex h-14 items-center justify-between border-b border-white/[0.06] bg-white/[0.02] backdrop-blur-xl backdrop-saturate-150 px-4">
            {/* Left: Logo + Search */}
            <div className="flex items-center gap-4">
                {/* Logo */}
                <Link href="/dashboard" className="flex items-center gap-2 transition-opacity hover:opacity-80">
                    <SaturnLogo size="sm" />
                    <span className="text-lg font-semibold tracking-tight text-foreground">Saturn</span>
                </Link>

                {/* Team Switcher */}
                <TeamSwitcher />

                {/* Command Palette Trigger */}
                <button
                    onClick={onCommandPalette}
                    className="group hidden items-center gap-3 rounded-lg border border-border bg-background-secondary px-4 py-2 text-sm text-foreground-muted transition-all duration-200 hover:border-border/80 hover:bg-background-tertiary hover:text-foreground md:flex"
                >
                    <Search className="h-4 w-4 group-hover:animate-wiggle" />
                    <span>Search...</span>
                    <kbd className="ml-6 flex items-center gap-1 rounded-md bg-background-tertiary px-2 py-1 text-xs font-medium transition-transform duration-200 group-hover:scale-110">
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
                        className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-primary-hover hover:shadow-glow-primary"
                    >
                        <Plus className="h-4 w-4" />
                        <span>New</span>
                    </Link>
                )}

                {/* Observability */}
                <Link
                    href="/observability"
                    className="group rounded-lg p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-secondary hover:text-foreground"
                    title="Observability"
                >
                    <BarChart3 className="h-5 w-5 group-hover:animate-wiggle" />
                </Link>

                {/* Theme Toggle */}
                <button
                    onClick={toggleTheme}
                    className="group rounded-lg p-2.5 text-foreground-muted transition-all duration-200 hover:bg-background-secondary hover:text-foreground"
                    title={isDark ? 'Switch to light theme' : 'Switch to dark theme'}
                >
                    {isDark ? <Sun className="h-5 w-5 group-hover:animate-wiggle" /> : <Moon className="h-5 w-5 group-hover:animate-wiggle" />}
                </button>

                {/* Notifications */}
                <NotificationDropdown
                    unreadCount={notificationsData?.unreadCount ?? 0}
                    notifications={notificationsData?.recent ?? []}
                />

                {/* User Menu */}
                <Dropdown>
                    <DropdownTrigger>
                        <UserMenuButton user={user} />
                    </DropdownTrigger>
                    <DropdownContent align="right" className="w-56">
                        <div className="px-3 py-2">
                            <p className="text-sm font-medium text-foreground">{user?.name || 'User'}</p>
                            <p className="text-xs text-foreground-muted">{user?.email || 'user@example.com'}</p>
                        </div>
                        <DropdownDivider />
                        <DropdownItem
                            onClick={() => router.visit('/settings/account')}
                            icon={<Settings className="h-4 w-4" />}
                        >
                            Account Settings
                        </DropdownItem>
                        <DropdownItem
                            onClick={() => router.visit('/settings/workspace')}
                            icon={<Users className="h-4 w-4" />}
                        >
                            Workspace Settings
                        </DropdownItem>
                        <DropdownDivider />
                        <DropdownItem
                            onClick={() => router.post('/logout')}
                            icon={<LogOut className="h-4 w-4" />}
                        >
                            Logout
                        </DropdownItem>
                    </DropdownContent>
                </Dropdown>
            </div>
        </header>
    );
}
