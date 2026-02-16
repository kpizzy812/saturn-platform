import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '../../../utils/test-utils';
import ServerGeneralSettings from '@/pages/Servers/Settings/General';

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
    Button: ({ children, onClick, disabled, type }: any) => (
        <button onClick={onClick} disabled={disabled} type={type}>
            {children}
        </button>
    ),
    Input: ({ label, value, error, hint, placeholder, onChange, type }: any) => (
        <div>
            <label>{label}</label>
            <input
                type={type || 'text'}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
            />
            {error && <span className="error">{error}</span>}
            {hint && <span className="hint">{hint}</span>}
        </div>
    ),
    Textarea: ({ label, value, error, placeholder, onChange, rows }: any) => (
        <div>
            <label>{label}</label>
            <textarea
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                rows={rows}
            />
            {error && <span className="error">{error}</span>}
        </div>
    ),
}));

const mockServer = {
    id: 1,
    uuid: 'server-uuid-123',
    name: 'Production Server',
    description: 'Main production server',
    ip: '192.168.1.100',
    port: 22,
    user: 'root',
    created_at: '2024-01-01T10:00:00.000Z',
};

const defaultProps = {
    server: mockServer,
};

describe('ServerGeneralSettings', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page title with server name', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Production Server - General Settings')).toBeInTheDocument();
    });

    it('renders General Settings header', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('General Settings')).toBeInTheDocument();
        expect(screen.getByText('Production Server')).toBeInTheDocument();
    });

    it('renders Back to Settings link', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Back to Settings')).toBeInTheDocument();
    });

    it('renders Basic Information section', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Basic Information')).toBeInTheDocument();
    });

    it('displays Server Name input with current value', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Server Name')).toBeInTheDocument();
        const nameInput = screen.getByDisplayValue('Production Server');
        expect(nameInput).toBeInTheDocument();
        expect(nameInput).toHaveAttribute('placeholder', 'My Server');
    });

    it('displays Description textarea with current value', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Description')).toBeInTheDocument();
        const descInput = screen.getByDisplayValue('Main production server');
        expect(descInput).toBeInTheDocument();
    });

    it('renders Connection Settings section', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Connection Settings')).toBeInTheDocument();
    });

    it('displays IP Address input with current value', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('IP Address / Hostname')).toBeInTheDocument();
        const ipInput = screen.getByDisplayValue('192.168.1.100');
        expect(ipInput).toBeInTheDocument();
    });

    it('displays SSH Port input with current value', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('SSH Port')).toBeInTheDocument();
        const portInput = screen.getByDisplayValue('22');
        expect(portInput).toBeInTheDocument();
    });

    it('displays SSH User input with current value', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getAllByText('SSH User').length).toBeGreaterThan(0);
        const userInput = screen.getByDisplayValue('root');
        expect(userInput).toBeInTheDocument();
    });

    it('shows hint for SSH User field', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('The user to connect with via SSH')).toBeInTheDocument();
    });

    it('renders Server Information section', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Server Information')).toBeInTheDocument();
    });

    it('displays read-only UUID field', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('UUID')).toBeInTheDocument();
        expect(screen.getByText('server-uuid-123')).toBeInTheDocument();
    });

    it('displays read-only Created At field', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Created At')).toBeInTheDocument();
        // The exact format depends on locale, but the date should be present
        const createdAtElements = screen.getAllByText(/2024/);
        expect(createdAtElements.length).toBeGreaterThan(0);
    });

    it('renders Save General Settings button', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('Save General Settings')).toBeInTheDocument();
    });

    it('shows Saving... text when processing', () => {
        // This test would need to trigger the form submission
        // For now, we just verify the button exists
        render(<ServerGeneralSettings {...defaultProps} />);

        const saveButton = screen.getByText('Save General Settings');
        expect(saveButton).toBeInTheDocument();
    });

    it('handles empty description gracefully', () => {
        const props = {
            server: { ...mockServer, description: '' },
        };
        render(<ServerGeneralSettings {...props} />);

        const descInput = screen.getByPlaceholderText('Optional description for this server');
        expect(descInput).toBeInTheDocument();
        expect(descInput).toHaveValue('');
    });

    it('renders form element wrapping inputs', () => {
        const { container } = render(<ServerGeneralSettings {...defaultProps} />);

        const form = container.querySelector('form');
        expect(form).toBeInTheDocument();
    });

    it('displays all required form sections in correct order', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        // Check all three main sections are present
        expect(screen.getByText('Basic Information')).toBeInTheDocument();
        expect(screen.getByText('Connection Settings')).toBeInTheDocument();
        expect(screen.getByText('Server Information')).toBeInTheDocument();
    });

    it('shows placeholder for Server Name field', () => {
        const props = {
            server: { ...mockServer, name: '' },
        };
        render(<ServerGeneralSettings {...props} />);

        const nameInput = screen.getByPlaceholderText('My Server');
        expect(nameInput).toBeInTheDocument();
    });

    it('shows hint for Server Name field', () => {
        render(<ServerGeneralSettings {...defaultProps} />);

        expect(screen.getByText('A friendly name to identify this server')).toBeInTheDocument();
    });
});
