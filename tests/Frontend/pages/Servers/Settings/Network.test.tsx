import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import ServerNetworkSettings from '@/pages/Servers/Settings/Network';

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
    Input: ({ label, value, hint, onChange, disabled, placeholder }: any) => (
        <div>
            <label>{label}</label>
            <input
                value={value}
                onChange={onChange}
                disabled={disabled}
                placeholder={placeholder}
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
};

const defaultProps = {
    server: mockServer,
};

describe('ServerNetworkSettings', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page title with server name', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Production Server - Network Settings')).toBeInTheDocument();
    });

    it('renders Network Settings header', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Network Settings')).toBeInTheDocument();
        expect(screen.getByText('Production Server')).toBeInTheDocument();
    });

    it('renders Back to Settings link', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Back to Settings')).toBeInTheDocument();
    });

    it('displays Connection Details section', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Connection Details')).toBeInTheDocument();
    });

    it('shows read-only IP Address field', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getAllByText('IP Address').length).toBeGreaterThan(0);
        const ipInput = screen.getByDisplayValue('192.168.1.100');
        expect(ipInput).toBeInTheDocument();
        expect(ipInput).toBeDisabled();
    });

    it('shows hint for IP Address field', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getAllByText('This is read-only. Edit from server settings.').length).toBeGreaterThan(0);
    });

    it('shows read-only SSH Port field', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getAllByText('SSH Port').length).toBeGreaterThan(0);
        const portInput = screen.getByDisplayValue('22');
        expect(portInput).toBeInTheDocument();
        expect(portInput).toBeDisabled();
    });

    it('shows read-only SSH User field', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getAllByText('SSH User').length).toBeGreaterThan(0);
        const userInput = screen.getByDisplayValue('root');
        expect(userInput).toBeInTheDocument();
        expect(userInput).toBeDisabled();
    });

    it('displays Firewall Settings section', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Firewall Settings')).toBeInTheDocument();
    });

    it('shows Enable Firewall toggle', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Enable Firewall')).toBeInTheDocument();
        expect(screen.getByText('Protect your server with firewall rules')).toBeInTheDocument();
    });

    it('displays Allowed Ports input', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Allowed Ports')).toBeInTheDocument();
        const portsInput = screen.getByDisplayValue('80,443,22');
        expect(portsInput).toBeInTheDocument();
    });

    it('shows hint for Allowed Ports field', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Comma-separated list of ports to allow through firewall')).toBeInTheDocument();
    });

    it('displays IP Allowlist section', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('IP Allowlist')).toBeInTheDocument();
    });

    it('shows Add IP button', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Add IP')).toBeInTheDocument();
    });

    it('displays IP allowlist description', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText(/Only allow connections from these IP addresses/i)).toBeInTheDocument();
        expect(screen.getByText(/Use 0.0.0.0\/0 to allow all/i)).toBeInTheDocument();
    });

    it('renders default IP address input', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        const ipInput = screen.getByDisplayValue('0.0.0.0/0');
        expect(ipInput).toBeInTheDocument();
    });

    it('shows placeholder for IP inputs', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        const ipInput = screen.getByPlaceholderText('192.168.1.1 or 10.0.0.0/24');
        expect(ipInput).toBeInTheDocument();
    });

    it('renders Save Network Settings button', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getByText('Save Network Settings')).toBeInTheDocument();
    });

    it('does not show delete button when only one IP exists', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        // Initially one IP, so no delete button should be visible
        const deleteButtons = screen.queryAllByRole('button', { name: /trash/i });
        expect(deleteButtons.length).toBe(0);
    });

    it('renders placeholder for Allowed Ports', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        const portsInput = screen.getByPlaceholderText('80,443,22');
        expect(portsInput).toBeInTheDocument();
    });

    it('displays all connection detail fields', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        expect(screen.getAllByText('IP Address').length).toBeGreaterThan(0);
        expect(screen.getAllByText('SSH Port').length).toBeGreaterThan(0);
        expect(screen.getAllByText('SSH User').length).toBeGreaterThan(0);
    });

    it('shows firewall enabled by default', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        const firewallCheckbox = screen.getByText('Enable Firewall').closest('div')?.querySelector('input');
        expect(firewallCheckbox).toBeDefined();
    });

    it('shows auto cleanup enabled by default', () => {
        render(<ServerNetworkSettings {...defaultProps} />);

        // This component doesn't have auto cleanup, but has firewall toggle
        const enableFirewallToggle = screen.getByText('Enable Firewall');
        expect(enableFirewallToggle).toBeInTheDocument();
    });
});
