import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import VolumeCreate from '../../../../resources/js/pages/Volumes/Create';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            post: vi.fn(),
        },
        Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
            <a href={href}>{children}</a>
        ),
    };
});

describe('Volumes/Create', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const mockServices = [
        { uuid: 'service-1', name: 'postgres-main', type: 'postgresql' },
        { uuid: 'service-2', name: 'redis-cache', type: 'redis' },
    ];

    it('renders create volume page with heading', () => {
        render(<VolumeCreate />);

        expect(screen.getByRole('heading', { level: 1, name: /create volume/i })).toBeInTheDocument();
        expect(screen.getByText(/add persistent storage for your services/i)).toBeInTheDocument();
    });

    it('renders back link to volumes page', () => {
        render(<VolumeCreate />);

        const backLink = screen.getByRole('link', { name: /back to volumes/i });
        expect(backLink).toBeInTheDocument();
        expect(backLink).toHaveAttribute('href', '/volumes');
    });

    it('displays volume configuration form', () => {
        render(<VolumeCreate />);

        expect(screen.getByLabelText(/volume name/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/mount path/i)).toBeInTheDocument();
    });

    it('shows size selection buttons', () => {
        render(<VolumeCreate />);

        expect(screen.getByText('1 GB')).toBeInTheDocument();
        expect(screen.getByText('5 GB')).toBeInTheDocument();
        // "10 GB" appears in both button label and pricing summary (default selection)
        expect(screen.getAllByText('10 GB').length).toBeGreaterThanOrEqual(1);
        expect(screen.getByText('20 GB')).toBeInTheDocument();
        expect(screen.getByText('50 GB')).toBeInTheDocument();
        expect(screen.getByText('100 GB')).toBeInTheDocument();
    });

    it('displays pricing for each size option', () => {
        render(<VolumeCreate />);

        expect(screen.getByText('$0.10/month')).toBeInTheDocument();
        expect(screen.getByText('$0.50/month')).toBeInTheDocument();
        // "$1.00/month" appears in both 10 GB button and pricing summary (default selection)
        expect(screen.getAllByText('$1.00/month').length).toBeGreaterThanOrEqual(1);
    });

    it('shows service attachment dropdown', () => {
        render(<VolumeCreate services={mockServices} />);

        expect(screen.getByLabelText(/attach to service/i)).toBeInTheDocument();
    });

    it('includes none option in service dropdown', () => {
        render(<VolumeCreate services={mockServices} />);

        const select = screen.getByLabelText(/attach to service/i) as HTMLSelectElement;
        const options = Array.from(select.options).map(opt => opt.text);

        expect(options).toContain("None - I'll attach it later");
    });

    it('displays available services in dropdown', () => {
        render(<VolumeCreate services={mockServices} />);

        const select = screen.getByLabelText(/attach to service/i) as HTMLSelectElement;
        const options = Array.from(select.options).map(opt => opt.text);

        expect(options).toContain('postgres-main (postgresql)');
        expect(options).toContain('redis-cache (redis)');
    });

    it('shows sidebar with volume information', () => {
        render(<VolumeCreate />);

        expect(screen.getByText('About Volumes')).toBeInTheDocument();
        expect(screen.getByText(/volumes provide persistent storage for your services/i)).toBeInTheDocument();
    });

    it('displays key features in sidebar', () => {
        render(<VolumeCreate />);

        expect(screen.getByText(/data persists across deployments/i)).toBeInTheDocument();
        expect(screen.getByText(/automatic backups available/i)).toBeInTheDocument();
        expect(screen.getByText(/can be shared across services/i)).toBeInTheDocument();
        expect(screen.getByText(/resize anytime without downtime/i)).toBeInTheDocument();
    });

    it('shows common use cases', () => {
        render(<VolumeCreate />);

        expect(screen.getByText('Common Use Cases')).toBeInTheDocument();
        expect(screen.getByText('Database Storage')).toBeInTheDocument();
        expect(screen.getByText('File Uploads')).toBeInTheDocument();
        expect(screen.getByText('Application Logs')).toBeInTheDocument();
    });

    it('displays example mount paths for use cases', () => {
        render(<VolumeCreate />);

        expect(screen.getByText('/var/lib/postgresql/data')).toBeInTheDocument();
        expect(screen.getByText('/app/storage/uploads')).toBeInTheDocument();
        expect(screen.getByText('/var/log/app')).toBeInTheDocument();
    });

    it('shows important notes section', () => {
        render(<VolumeCreate />);

        expect(screen.getByText('Important Notes')).toBeInTheDocument();
        expect(screen.getByText(/volumes are billed hourly based on size/i)).toBeInTheDocument();
        expect(screen.getByText(/data is not automatically backed up/i)).toBeInTheDocument();
        expect(screen.getByText(/mount paths must be absolute/i)).toBeInTheDocument();
        expect(screen.getByText(/deleting a volume is permanent/i)).toBeInTheDocument();
    });

    it('validates volume name format', async () => {
        const { user } = render(<VolumeCreate />);

        const nameInput = screen.getByLabelText(/volume name/i);
        await user.type(nameInput, 'Invalid_Name');

        const submitButton = screen.getByRole('button', { name: /create volume/i });
        await user.click(submitButton);

        expect(screen.getByText(/name must be lowercase alphanumeric with hyphens/i)).toBeInTheDocument();
    });

    it('validates mount path format', async () => {
        const { user } = render(<VolumeCreate />);

        const nameInput = screen.getByLabelText(/volume name/i);
        await user.type(nameInput, 'my-volume');

        const mountPathInput = screen.getByLabelText(/mount path/i);
        await user.clear(mountPathInput);
        await user.type(mountPathInput, 'invalid-path');

        const submitButton = screen.getByRole('button', { name: /create volume/i });
        await user.click(submitButton);

        expect(screen.getByText(/mount path must start with \//i)).toBeInTheDocument();
    });

    it('converts volume name to lowercase automatically', async () => {
        const { user } = render(<VolumeCreate />);

        const nameInput = screen.getByLabelText(/volume name/i) as HTMLInputElement;
        await user.type(nameInput, 'MyVolume');

        expect(nameInput.value).toBe('myvolume');
    });

    it('shows selected size with pricing summary', async () => {
        const { user } = render(<VolumeCreate />);

        const size20Button = screen.getByRole('button', { name: /20 gb.*\$2\.00\/month/i });
        await user.click(size20Button);

        expect(screen.getByText(/selected size:/i)).toBeInTheDocument();
        expect(screen.getByText(/estimated cost:/i)).toBeInTheDocument();
    });

    it('submits form with correct data', async () => {
        const { user } = render(<VolumeCreate services={mockServices} />);

        await user.type(screen.getByLabelText(/volume name/i), 'postgres-data');
        await user.clear(screen.getByLabelText(/mount path/i));
        await user.type(screen.getByLabelText(/mount path/i), '/var/lib/postgresql/data');

        const size50Button = screen.getByRole('button', { name: /50 gb/i });
        await user.click(size50Button);

        const serviceSelect = screen.getByLabelText(/attach to service/i);
        await user.selectOptions(serviceSelect, 'service-1');

        const submitButton = screen.getByRole('button', { name: /create volume/i });
        await user.click(submitButton);

        expect(router.post).toHaveBeenCalledWith('/volumes', expect.objectContaining({
            name: 'postgres-data',
            size: 50,
            mount_path: '/var/lib/postgresql/data',
            service_id: 'service-1',
        }), expect.any(Object));
    });

    it('renders cancel button linking to volumes page', () => {
        render(<VolumeCreate />);

        const cancelLink = screen.getByRole('link', { name: /cancel/i });
        expect(cancelLink).toHaveAttribute('href', '/volumes');
    });

    it('shows hint text for form fields', () => {
        render(<VolumeCreate />);

        expect(screen.getByText(/lowercase alphanumeric with hyphens only/i)).toBeInTheDocument();
        expect(screen.getByText(/the path where this volume will be mounted in the container/i)).toBeInTheDocument();
    });

    it('displays service attachment info message', () => {
        render(<VolumeCreate />);

        expect(screen.getByText(/you can attach this volume to a service now or later from the volume details page/i)).toBeInTheDocument();
    });
});
