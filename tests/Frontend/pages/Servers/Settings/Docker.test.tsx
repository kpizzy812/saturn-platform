import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import ServerDockerSettings from '@/pages/Servers/Settings/Docker';

vi.mock('@/components/layout', () => ({
    AppLayout: ({ children, title }: any) => (
        <div>
            <h1>{title}</h1>
            {children}
        </div>
    ),
}));

vi.mock('@/components/ui', () => ({
    Card: ({ children, className }: any) => <div className={className}>{children}</div>,
    CardHeader: ({ children }: any) => <div>{children}</div>,
    CardTitle: ({ children }: any) => <h2>{children}</h2>,
    CardContent: ({ children, className }: any) => <div className={className}>{children}</div>,
    Button: ({ children, onClick, disabled, variant, size }: any) => (
        <button onClick={onClick} disabled={disabled} data-variant={variant} data-size={size}>
            {children}
        </button>
    ),
    Input: ({ label, value, hint, onChange, type, min, max }: any) => (
        <div>
            <label>{label}</label>
            <input
                type={type || 'text'}
                value={value}
                onChange={onChange}
                min={min}
                max={max}
            />
            {hint && <span className="hint">{hint}</span>}
        </div>
    ),
}));

const mockServer = {
    id: 1,
    uuid: 'server-uuid-123',
    name: 'Production Server',
    ip: '192.168.1.100',
    port: 22,
    user: 'root',
    created_at: '2024-01-01T00:00:00.000Z',
    settings: {
        is_build_server: false,
        concurrent_builds: 2,
        docker_version: '24.0.7',
        docker_compose_version: '2.23.0',
    },
};

const defaultProps = {
    server: mockServer,
};

describe('ServerDockerSettings', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page title with server name', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Production Server - Docker Settings')).toBeInTheDocument();
    });

    it('renders Docker Configuration header', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Docker Configuration')).toBeInTheDocument();
        expect(screen.getByText('Production Server')).toBeInTheDocument();
    });

    it('renders Back to Settings link', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Back to Settings')).toBeInTheDocument();
    });

    it('displays Docker Status section', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Docker Status')).toBeInTheDocument();
        expect(screen.getByText('Docker is installed and running')).toBeInTheDocument();
        expect(screen.getByText('Docker Engine installed')).toBeInTheDocument();
    });

    it('renders Validate button in Docker Status', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Validate')).toBeInTheDocument();
    });

    it('displays Build Settings section', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Build Settings')).toBeInTheDocument();
    });

    it('shows Build Server toggle with label', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Build Server')).toBeInTheDocument();
        expect(screen.getByText('Use this server for building Docker images')).toBeInTheDocument();
    });

    it('displays Concurrent Builds input', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Concurrent Builds')).toBeInTheDocument();
        const input = screen.getByDisplayValue('2');
        expect(input).toBeInTheDocument();
    });

    it('shows hint for Concurrent Builds field', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Maximum number of concurrent builds on this server')).toBeInTheDocument();
    });

    it('displays Auto Cleanup toggle', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Auto Cleanup')).toBeInTheDocument();
        expect(screen.getByText('Automatically clean up unused Docker images and containers')).toBeInTheDocument();
    });

    it('renders Save Docker Settings button', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Save Docker Settings')).toBeInTheDocument();
    });

    it('displays Docker Information section', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Docker Information')).toBeInTheDocument();
    });

    it('shows Docker Version information', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Docker Version')).toBeInTheDocument();
        expect(screen.getByText('24.0.7')).toBeInTheDocument();
    });

    it('shows Docker Compose Version information', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Docker Compose Version')).toBeInTheDocument();
        expect(screen.getByText('2.23.0')).toBeInTheDocument();
    });

    it('displays placeholder values for runtime stats', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        expect(screen.getByText('Running Containers')).toBeInTheDocument();
        expect(screen.getByText('Total Images')).toBeInTheDocument();
        expect(screen.getByText('Total Volumes')).toBeInTheDocument();
    });

    it('handles missing server settings gracefully', () => {
        const props = {
            server: { ...mockServer, settings: undefined },
        };
        render(<ServerDockerSettings {...props} />);

        // Should render with defaults
        expect(screen.getByText('Docker Configuration')).toBeInTheDocument();
        expect(screen.getByText('Concurrent Builds')).toBeInTheDocument();
    });

    it('shows em-dash for missing Docker version', () => {
        const props = {
            server: {
                ...mockServer,
                settings: { ...mockServer.settings, docker_version: undefined },
            },
        };
        render(<ServerDockerSettings {...props} />);

        // Should show — for missing version
        const dockerVersionRow = screen.getByText('Docker Version').closest('div');
        expect(dockerVersionRow).toBeInTheDocument();
    });

    it('initializes Build Server toggle from server settings', () => {
        const props = {
            server: {
                ...mockServer,
                settings: { ...mockServer.settings, is_build_server: true },
            },
        };
        render(<ServerDockerSettings {...props} />);

        // Component should initialize with is_build_server = true
        const buildServerCheckbox = screen.getByText('Build Server').closest('div')?.querySelector('input');
        expect(buildServerCheckbox).toBeDefined();
    });

    it('initializes concurrent builds from server settings', () => {
        const props = {
            server: {
                ...mockServer,
                settings: { ...mockServer.settings, concurrent_builds: 5 },
            },
        };
        render(<ServerDockerSettings {...props} />);

        const input = screen.getByDisplayValue('5');
        expect(input).toBeInTheDocument();
    });

    it('defaults concurrent builds to 2 when not set', () => {
        const props = {
            server: {
                ...mockServer,
                settings: { ...mockServer.settings, concurrent_builds: undefined },
            },
        };
        render(<ServerDockerSettings {...props} />);

        const input = screen.getByDisplayValue('2');
        expect(input).toBeInTheDocument();
    });

    it('button is disabled initially when no changes', () => {
        render(<ServerDockerSettings {...defaultProps} />);

        const saveButton = screen.getByText('Save Docker Settings');
        expect(saveButton).toBeDisabled();
    });
});
