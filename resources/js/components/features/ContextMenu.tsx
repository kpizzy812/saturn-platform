import { useState, useEffect, useRef, useCallback } from 'react';
import {
    Play,
    RefreshCw,
    StopCircle,
    Terminal,
    Settings,
    Trash2,
    Copy,
    ExternalLink,
    GitBranch,
    Variable,
    Database,
    Gauge,
    Link2,
    Download,
    RotateCcw,
} from 'lucide-react';

export interface ContextMenuPosition {
    x: number;
    y: number;
}

export interface ContextMenuNode {
    id: string;
    uuid: string;
    type: 'app' | 'db';
    name: string;
    status: string;
    fqdn?: string;
}

interface ContextMenuAction {
    id: string;
    label: string;
    icon: React.ReactNode;
    action: () => void;
    danger?: boolean;
    divider?: boolean;
}

interface ContextMenuProps {
    position: ContextMenuPosition | null;
    node: ContextMenuNode | null;
    onClose: () => void;
    onDeploy?: (nodeId: string) => void;
    onRestart?: (nodeId: string) => void;
    onStop?: (nodeId: string) => void;
    onViewLogs?: (nodeId: string) => void;
    onOpenSettings?: (nodeId: string) => void;
    onDelete?: (nodeId: string) => void;
    onCopyId?: (nodeId: string) => void;
    onOpenUrl?: (url: string) => void;
    onCreateBackup?: (nodeId: string) => void;
    onRestoreBackup?: (nodeId: string) => void;
}

export function ContextMenu({
    position,
    node,
    onClose,
    onDeploy,
    onRestart,
    onStop,
    onViewLogs,
    onOpenSettings,
    onDelete,
    onCopyId,
    onOpenUrl,
    onCreateBackup,
    onRestoreBackup,
}: ContextMenuProps) {
    const menuRef = useRef<HTMLDivElement>(null);

    // Close on outside click
    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
                onClose();
            }
        };

        const handleEscape = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEscape);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [onClose]);

    if (!position || !node) return null;

    const isApp = node.type === 'app';
    const isRunning = node.status?.startsWith('running') ?? false;

    // Build actions based on node type
    const appActions: ContextMenuAction[] = [
        {
            id: 'deploy',
            label: 'Deploy',
            icon: <Play className="h-4 w-4" />,
            action: () => onDeploy?.(node.id),
        },
        {
            id: 'restart',
            label: 'Restart',
            icon: <RefreshCw className="h-4 w-4" />,
            action: () => onRestart?.(node.id),
        },
        {
            id: 'stop',
            label: isRunning ? 'Stop' : 'Start',
            icon: isRunning ? <StopCircle className="h-4 w-4" /> : <Play className="h-4 w-4" />,
            action: () => onStop?.(node.id),
        },
        {
            id: 'logs',
            label: 'View Logs',
            icon: <Terminal className="h-4 w-4" />,
            action: () => onViewLogs?.(node.id),
            divider: true,
        },
        {
            id: 'settings',
            label: 'Settings',
            icon: <Settings className="h-4 w-4" />,
            action: () => onOpenSettings?.(node.id),
        },
        {
            id: 'metrics',
            label: 'View Metrics',
            icon: <Gauge className="h-4 w-4" />,
            action: () => onOpenSettings?.(node.id),
        },
        {
            id: 'variables',
            label: 'Environment Variables',
            icon: <Variable className="h-4 w-4" />,
            action: () => onOpenSettings?.(node.id),
        },
        {
            id: 'networking',
            label: 'Networking',
            icon: <Link2 className="h-4 w-4" />,
            action: () => onOpenSettings?.(node.id),
            divider: true,
        },
    ];

    if (node.fqdn) {
        const fqdnUrl = node.fqdn.startsWith('http') ? node.fqdn : `https://${node.fqdn}`;
        const fqdnDisplay = node.fqdn.replace(/^https?:\/\//, '');
        appActions.push({
            id: 'open-url',
            label: `Open ${fqdnDisplay}`,
            icon: <ExternalLink className="h-4 w-4" />,
            action: () => onOpenUrl?.(fqdnUrl),
        });
    }

    appActions.push(
        {
            id: 'copy-id',
            label: 'Copy Service ID',
            icon: <Copy className="h-4 w-4" />,
            action: () => onCopyId?.(node.id),
            divider: true,
        },
        {
            id: 'delete',
            label: 'Delete Service',
            icon: <Trash2 className="h-4 w-4" />,
            action: () => onDelete?.(node.id),
            danger: true,
        }
    );

    const dbActions: ContextMenuAction[] = [
        {
            id: 'restart',
            label: 'Restart',
            icon: <RefreshCw className="h-4 w-4" />,
            action: () => onRestart?.(node.id),
        },
        {
            id: 'stop',
            label: isRunning ? 'Stop' : 'Start',
            icon: isRunning ? <StopCircle className="h-4 w-4" /> : <Play className="h-4 w-4" />,
            action: () => onStop?.(node.id),
        },
        {
            id: 'logs',
            label: 'View Logs',
            icon: <Terminal className="h-4 w-4" />,
            action: () => onViewLogs?.(node.id),
            divider: true,
        },
        {
            id: 'connect',
            label: 'Connection Info',
            icon: <Database className="h-4 w-4" />,
            action: () => onOpenSettings?.(node.id),
        },
        {
            id: 'backup',
            label: 'Create Backup',
            icon: <Download className="h-4 w-4" />,
            action: () => onCreateBackup?.(node.id),
        },
        {
            id: 'restore',
            label: 'Restore from Backup',
            icon: <RotateCcw className="h-4 w-4" />,
            action: () => onRestoreBackup?.(node.id),
            divider: true,
        },
        {
            id: 'settings',
            label: 'Settings',
            icon: <Settings className="h-4 w-4" />,
            action: () => onOpenSettings?.(node.id),
        },
        {
            id: 'copy-id',
            label: 'Copy Database ID',
            icon: <Copy className="h-4 w-4" />,
            action: () => onCopyId?.(node.id),
            divider: true,
        },
        {
            id: 'delete',
            label: 'Delete Database',
            icon: <Trash2 className="h-4 w-4" />,
            action: () => onDelete?.(node.id),
            danger: true,
        },
    ];

    const actions = isApp ? appActions : dbActions;

    const handleClick = (action: ContextMenuAction) => {
        action.action();
        onClose();
    };

    // Adjust position to keep menu in viewport
    const adjustedPosition = { ...position };
    if (typeof window !== 'undefined') {
        const menuWidth = 220;
        const menuHeight = actions.length * 36 + 20;

        if (position.x + menuWidth > window.innerWidth) {
            adjustedPosition.x = position.x - menuWidth;
        }
        if (position.y + menuHeight > window.innerHeight) {
            adjustedPosition.y = position.y - menuHeight;
        }
    }

    return (
        <div
            ref={menuRef}
            className="fixed z-[100] min-w-[200px] rounded-lg border border-white/[0.08] backdrop-blur-xl bg-background-tertiary/95 shadow-2xl shadow-black/50 animate-fade-in"
            style={{
                left: adjustedPosition.x,
                top: adjustedPosition.y,
            }}
        >
            {/* Header */}
            <div className="border-b border-border px-3 py-2">
                <p className="text-sm font-medium text-foreground">{node.name}</p>
                <p className="text-xs text-foreground-muted capitalize">{node.type === 'app' ? 'Application' : 'Database'}</p>
            </div>

            {/* Actions */}
            <div className="py-1">
                {actions.map((action) => (
                    <div key={action.id}>
                        <button
                            onClick={() => handleClick(action)}
                            className={`flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-all duration-200 ${
                                action.danger
                                    ? 'text-red-400 hover:bg-red-500/10'
                                    : 'text-foreground-muted hover:bg-background-secondary hover:text-foreground'
                            }`}
                        >
                            {action.icon}
                            {action.label}
                        </button>
                        {action.divider && <div className="my-1 border-t border-border" />}
                    </div>
                ))}
            </div>
        </div>
    );
}

export default ContextMenu;
