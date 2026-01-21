import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import { ContextMenu, type ContextMenuNode, type ContextMenuPosition } from '@/components/features/ContextMenu';

describe('ContextMenu', () => {
    const mockAppNode: ContextMenuNode = {
        id: '1',
        type: 'app',
        name: 'api-server',
        status: 'running',
        fqdn: 'api.example.com',
    };

    const mockDbNode: ContextMenuNode = {
        id: '2',
        type: 'db',
        name: 'postgres',
        status: 'running',
    };

    const mockPosition: ContextMenuPosition = { x: 100, y: 100 };

    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Rendering', () => {
        it('does not render when position is null', () => {
            render(
                <ContextMenu
                    position={null}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.queryByText('api-server')).not.toBeInTheDocument();
        });

        it('does not render when node is null', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={null}
                    onClose={() => {}}
                />
            );
            expect(screen.queryByRole('button')).not.toBeInTheDocument();
        });

        it('renders when both position and node are provided', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('api-server')).toBeInTheDocument();
        });

        it('shows node name in header', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('api-server')).toBeInTheDocument();
        });

        it('shows node type in header', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Application')).toBeInTheDocument();
        });

        it('shows Database type for db nodes', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockDbNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Database')).toBeInTheDocument();
        });
    });

    describe('App Actions', () => {
        it('shows Deploy action for apps', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Deploy')).toBeInTheDocument();
        });

        it('shows Restart action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Restart')).toBeInTheDocument();
        });

        it('shows Stop action when running', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Stop')).toBeInTheDocument();
        });

        it('shows Start action when stopped', () => {
            const stoppedNode = { ...mockAppNode, status: 'stopped' };
            render(
                <ContextMenu
                    position={mockPosition}
                    node={stoppedNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Start')).toBeInTheDocument();
        });

        it('shows View Logs action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('View Logs')).toBeInTheDocument();
        });

        it('shows Settings action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Settings')).toBeInTheDocument();
        });

        it('shows View Metrics action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('View Metrics')).toBeInTheDocument();
        });

        it('shows Environment Variables action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Environment Variables')).toBeInTheDocument();
        });

        it('shows Networking action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Networking')).toBeInTheDocument();
        });

        it('shows Open URL action when fqdn exists', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText(/Open api.example.com/)).toBeInTheDocument();
        });

        it('shows Copy Service ID action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Copy Service ID')).toBeInTheDocument();
        });

        it('shows Delete Service action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Delete Service')).toBeInTheDocument();
        });
    });

    describe('Database Actions', () => {
        it('shows Connection Info action for databases', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockDbNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Connection Info')).toBeInTheDocument();
        });

        it('shows Create Backup action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockDbNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Create Backup')).toBeInTheDocument();
        });

        it('shows Restore from Backup action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockDbNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Restore from Backup')).toBeInTheDocument();
        });

        it('shows Copy Database ID action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockDbNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Copy Database ID')).toBeInTheDocument();
        });

        it('shows Delete Database action', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockDbNode}
                    onClose={() => {}}
                />
            );
            expect(screen.getByText('Delete Database')).toBeInTheDocument();
        });

        it('does not show Deploy action for databases', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockDbNode}
                    onClose={() => {}}
                />
            );
            expect(screen.queryByText('Deploy')).not.toBeInTheDocument();
        });
    });

    describe('Action Callbacks', () => {
        it('calls onDeploy when Deploy is clicked', async () => {
            const onDeploy = vi.fn();
            const onClose = vi.fn();
            const { user } = render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={onClose}
                    onDeploy={onDeploy}
                />
            );

            await user.click(screen.getByText('Deploy'));
            expect(onDeploy).toHaveBeenCalledWith('1');
            expect(onClose).toHaveBeenCalled();
        });

        it('calls onViewLogs when View Logs is clicked', async () => {
            const onViewLogs = vi.fn();
            const onClose = vi.fn();
            const { user } = render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={onClose}
                    onViewLogs={onViewLogs}
                />
            );

            await user.click(screen.getByText('View Logs'));
            expect(onViewLogs).toHaveBeenCalledWith('1');
            expect(onClose).toHaveBeenCalled();
        });

        it('calls onCopyId when Copy Service ID is clicked', async () => {
            const onCopyId = vi.fn();
            const onClose = vi.fn();
            const { user } = render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={onClose}
                    onCopyId={onCopyId}
                />
            );

            await user.click(screen.getByText('Copy Service ID'));
            expect(onCopyId).toHaveBeenCalledWith('1');
            expect(onClose).toHaveBeenCalled();
        });

        it('calls onDelete when Delete is clicked', async () => {
            const onDelete = vi.fn();
            const onClose = vi.fn();
            const { user } = render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={onClose}
                    onDelete={onDelete}
                />
            );

            await user.click(screen.getByText('Delete Service'));
            expect(onDelete).toHaveBeenCalledWith('1');
            expect(onClose).toHaveBeenCalled();
        });
    });

    describe('Closing', () => {
        it('closes on Escape key', () => {
            const onClose = vi.fn();
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={onClose}
                />
            );

            fireEvent.keyDown(document, { key: 'Escape' });
            expect(onClose).toHaveBeenCalled();
        });

        it('closes on outside click', () => {
            const onClose = vi.fn();
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={onClose}
                />
            );

            fireEvent.mouseDown(document.body);
            expect(onClose).toHaveBeenCalled();
        });
    });

    describe('Positioning', () => {
        it('positions at specified coordinates', () => {
            const { container } = render(
                <ContextMenu
                    position={{ x: 200, y: 300 }}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );

            const menu = container.querySelector('.fixed');
            expect(menu).toHaveStyle({ left: '200px', top: '300px' });
        });
    });

    describe('Danger Actions', () => {
        it('styles delete action as danger', () => {
            render(
                <ContextMenu
                    position={mockPosition}
                    node={mockAppNode}
                    onClose={() => {}}
                />
            );

            const deleteButton = screen.getByText('Delete Service').closest('button');
            expect(deleteButton).toHaveClass('text-red-400');
        });
    });
});
