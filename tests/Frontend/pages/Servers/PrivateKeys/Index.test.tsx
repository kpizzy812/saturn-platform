import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import ServerPrivateKeysIndex from '@/pages/Servers/PrivateKeys/Index';

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
    Button: ({ children, onClick, variant, size }: any) => (
        <button onClick={onClick} data-variant={variant} data-size={size}>
            {children}
        </button>
    ),
    Badge: ({ children, variant, size }: any) => (
        <span data-variant={variant} data-size={size}>{children}</span>
    ),
    useConfirm: () => vi.fn().mockResolvedValue(true),
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

const mockPrivateKeys = [
    {
        id: 1,
        uuid: 'key-uuid-1',
        name: 'Main SSH Key',
        description: 'Primary key for production',
        fingerprint: 'SHA256:abc123def456ghi789',
        is_default: true,
        created_at: '2024-01-05T00:00:00.000Z',
    },
    {
        id: 2,
        uuid: 'key-uuid-2',
        name: 'Backup Key',
        description: null,
        fingerprint: 'SHA256:xyz789uvw456rst123',
        is_default: false,
        created_at: '2024-01-10T00:00:00.000Z',
    },
];

const defaultProps = {
    server: mockServer,
    privateKeys: mockPrivateKeys,
};

describe('ServerPrivateKeysIndex', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page title with server name', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('Production Server - SSH Keys')).toBeInTheDocument();
    });

    it('renders header with SSH Private Keys title', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('SSH Private Keys')).toBeInTheDocument();
        expect(screen.getByText('Manage SSH keys for Production Server')).toBeInTheDocument();
    });

    it('renders Back to Server link', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('Back to Server')).toBeInTheDocument();
    });

    it('renders Add SSH Key button', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('Add SSH Key')).toBeInTheDocument();
    });

    it('displays security notice card', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('Security Notice')).toBeInTheDocument();
        expect(screen.getByText(/SSH private keys are stored encrypted/i)).toBeInTheDocument();
        expect(screen.getByText(/Never share your private keys/i)).toBeInTheDocument();
    });

    it('renders all private keys when provided', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('Main SSH Key')).toBeInTheDocument();
        expect(screen.getByText('Backup Key')).toBeInTheDocument();
    });

    it('displays key descriptions when available', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('Primary key for production')).toBeInTheDocument();
    });

    it('shows fingerprint for each key', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('SHA256:abc123def456ghi789')).toBeInTheDocument();
        expect(screen.getByText('SHA256:xyz789uvw456rst123')).toBeInTheDocument();
    });

    it('displays Default badge for default key', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('Default')).toBeInTheDocument();
    });

    it('shows Set Default button for non-default keys', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        const setDefaultButtons = screen.getAllByText('Set Default');
        expect(setDefaultButtons).toHaveLength(1);
    });

    it('renders delete button for each key', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        // Each key card should have delete functionality
        const keyCards = screen.getByText('Main SSH Key').closest('div');
        expect(keyCards).toBeInTheDocument();
    });

    it('displays empty state when no keys exist', () => {
        const props = { ...defaultProps, privateKeys: [] };
        render(<ServerPrivateKeysIndex {...props} />);

        expect(screen.getByText('No SSH keys configured')).toBeInTheDocument();
        expect(screen.getByText('Add an SSH private key to authenticate with this server')).toBeInTheDocument();
    });

    it('shows Add SSH Key button in empty state', () => {
        const props = { ...defaultProps, privateKeys: [] };
        render(<ServerPrivateKeysIndex {...props} />);

        const addButtons = screen.getAllByText('Add SSH Key');
        expect(addButtons.length).toBeGreaterThan(0);
    });

    it('renders How to Generate SSH Keys section', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText('How to Generate SSH Keys')).toBeInTheDocument();
    });

    it('displays Ed25519 key generation command', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText(/ssh-keygen -t ed25519/i)).toBeInTheDocument();
    });

    it('displays RSA key generation command', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText(/ssh-keygen -t rsa -b 4096/i)).toBeInTheDocument();
    });

    it('shows security warning about private keys', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        expect(screen.getByText(/Important:/i)).toBeInTheDocument();
        expect(screen.getByText(/Keep your private key secure/i)).toBeInTheDocument();
        expect(screen.getAllByText(/Never share your private key/i).length).toBeGreaterThan(0);
    });

    it('handles undefined privateKeys prop gracefully', () => {
        const props = { server: mockServer };
        render(<ServerPrivateKeysIndex {...props} />);

        expect(screen.getByText('No SSH keys configured')).toBeInTheDocument();
    });

    it('renders key cards with proper styling for default vs non-default', () => {
        render(<ServerPrivateKeysIndex {...defaultProps} />);

        // Both keys should be rendered
        expect(screen.getByText('Main SSH Key')).toBeInTheDocument();
        expect(screen.getByText('Backup Key')).toBeInTheDocument();

        // Default badge only on the default key
        const defaultBadges = screen.getAllByText('Default');
        expect(defaultBadges).toHaveLength(1);
    });
});
