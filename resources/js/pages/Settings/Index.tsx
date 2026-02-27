import * as React from 'react';
import { AppLayout } from '@/components/layout';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { User, Users, Key, Plug, Building2, FileText, Bell, Shield, Cloud, Zap } from 'lucide-react';

interface SettingSection {
    id: string;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    href: string;
}

const settingSections: SettingSection[] = [
    { id: 'account', label: 'Account', icon: User, href: '/settings/account' },
    { id: 'team', label: 'Team', icon: Users, href: '/settings/team' },
    { id: 'tokens', label: 'API Tokens', icon: Key, href: '/settings/tokens' },
    { id: 'integrations', label: 'Integrations', icon: Plug, href: '/settings/integrations' },
    { id: 'cloud-providers', label: 'Cloud Providers', icon: Cloud, href: '/settings/cloud-providers' },
    { id: 'auto-provisioning', label: 'Auto-Provisioning', icon: Zap, href: '/settings/auto-provisioning' },
    { id: 'notifications', label: 'Notifications', icon: Bell, href: '/settings/notifications' },
    { id: 'workspace', label: 'Workspace', icon: Building2, href: '/settings/workspace' },
    { id: 'audit-log', label: 'Audit Log', icon: FileText, href: '/settings/audit-log' },
    { id: 'security', label: 'Security', icon: Shield, href: '/settings/security' },
];

interface SettingsLayoutProps {
    children: React.ReactNode;
    activeSection?: string;
}

export function SettingsLayout({ children, activeSection }: SettingsLayoutProps) {
    return (
        <AppLayout title="Settings" showNewProject={false}>
            <div className="mx-auto max-w-7xl">
                {/* Header */}
                <div className="mb-8">
                    <h1 className="text-2xl font-semibold text-foreground">Settings</h1>
                    <p className="mt-1 text-sm text-foreground-muted">
                        Manage your account and team settings
                    </p>
                </div>

                {/* Layout */}
                <div className="flex gap-8">
                    {/* Sidebar */}
                    <aside className="w-56 flex-shrink-0">
                        <nav className="space-y-1">
                            {settingSections.map((section) => {
                                const Icon = section.icon;
                                const isActive = activeSection === section.id;

                                return (
                                    <Link
                                        key={section.id}
                                        href={section.href}
                                        className={cn(
                                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                            isActive
                                                ? 'bg-background-tertiary text-foreground'
                                                : 'text-foreground-muted hover:bg-background-secondary hover:text-foreground'
                                        )}
                                    >
                                        <Icon className="h-4 w-4" />
                                        {section.label}
                                    </Link>
                                );
                            })}
                        </nav>
                    </aside>

                    {/* Content */}
                    <main className="min-w-0 flex-1">
                        {children}
                    </main>
                </div>
            </div>
        </AppLayout>
    );
}

export default function SettingsIndex() {
    return (
        <SettingsLayout activeSection="account">
            <div className="rounded-lg border border-border bg-background-secondary p-8 text-center">
                <p className="text-foreground-muted">Select a section from the sidebar</p>
            </div>
        </SettingsLayout>
    );
}
