import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import DestinationsCreate from '@/pages/Destinations/Create';

// Mock AppLayout
vi.mock('@/components/layout', () => ({
    AppLayout: ({ children }: any) => <div>{children}</div>,
}));

// Mock useForm from test-utils
const mockPost = vi.fn();
const mockSetData = vi.fn();

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('../../utils/test-utils');
    return {
        ...actual,
        useForm: () => ({
            data: {
                name: '',
                server_uuid: 'server-uuid-1',
                network: 'saturn',
                is_default: false,
            },
            setData: mockSetData,
            post: mockPost,
            processing: false,
            errors: {},
        }),
    };
});

describe('Destinations Create Page', () => {
    const mockServers = [
        { id: 1, uuid: 'server-uuid-1', name: 'Production Server', ip: '192.168.1.10' },
        { id: 2, uuid: 'server-uuid-2', name: 'Staging Server', ip: '192.168.1.20' },
    ];

    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renders page title', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByRole('heading', { name: 'Create Destination' })).toBeInTheDocument();
    });

    it('renders Destination Details section with icon', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByText('Destination Details')).toBeInTheDocument();
    });

    it('renders server selection dropdown', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByText('Server')).toBeInTheDocument();
        expect(screen.getByText(/The server where this Docker network will be created/)).toBeInTheDocument();
    });

    it('renders all form fields', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.getByText('Docker Network Name')).toBeInTheDocument();
    });

    it('displays server options in select dropdown', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByText(/Production Server \(192.168.1.10\)/)).toBeInTheDocument();
        expect(screen.getByText(/Staging Server \(192.168.1.20\)/)).toBeInTheDocument();
    });

    it('shows "No servers available" when servers list is empty', () => {
        render(<DestinationsCreate servers={[]} />);

        expect(screen.getByText('No servers available')).toBeInTheDocument();
    });

    it('renders default destination checkbox', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByText(/Set as default destination for this server/)).toBeInTheDocument();
    });

    it('renders "What happens next?" info card', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByText('What happens next?')).toBeInTheDocument();
        expect(screen.getByText(/A new Docker network will be created on the selected server/)).toBeInTheDocument();
        expect(screen.getByText(/You can deploy applications, databases, and services to this destination/)).toBeInTheDocument();
        expect(screen.getByText(/Resources within the same destination can communicate via the network/)).toBeInTheDocument();
    });

    it('renders submit button with icon', () => {
        render(<DestinationsCreate servers={mockServers} />);

        const submitButton = screen.getByRole('button', { name: /Create Destination/ });
        expect(submitButton).toBeInTheDocument();
    });

    it('renders cancel button', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByText('Cancel')).toBeInTheDocument();
    });

    it('has placeholder text for form fields', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByPlaceholderText('Production, Staging, etc.')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('saturn')).toBeInTheDocument();
    });

    it('displays helper text for Docker Network Name field', () => {
        render(<DestinationsCreate servers={mockServers} />);

        expect(screen.getByText('This will be the name of the Docker network on the server')).toBeInTheDocument();
    });

    it('disables submit button when no servers are available', () => {
        render(<DestinationsCreate servers={[]} />);

        const submitButton = screen.getByRole('button', { name: /Create Destination/ });
        expect(submitButton).toBeDisabled();
    });
});
