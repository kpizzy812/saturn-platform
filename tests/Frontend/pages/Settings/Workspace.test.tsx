import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import WorkspaceSettings from '@/pages/Settings/Workspace';
import * as InertiaReact from '@inertiajs/react';

vi.mock('@/pages/Settings/Index', () => ({
    SettingsLayout: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Toast', () => ({
    useToast: () => ({
        addToast: vi.fn(),
    }),
    ToastProvider: ({ children }: any) => <>{children}</>,
}));

const mockWorkspace = {
    id: 1,
    name: 'Test Workspace',
    slug: 'test-workspace',
    logo: null,
    description: 'A test workspace for unit tests',
    timezone: 'Europe/London',
    defaultEnvironment: 'production',
    locale: 'en',
    dateFormat: 'Y-m-d',
    personalTeam: false,
    createdAt: '2024-01-15T10:00:00.000Z',
    owner: {
        name: 'John Doe',
        email: 'john@example.com',
    },
};

const mockStats = {
    projects: 5,
    servers: 3,
    applications: 12,
    members: 8,
};

const defaultProps = {
    workspace: mockWorkspace,
    stats: mockStats,
    timezones: ['Europe/London', 'America/New_York', 'Asia/Tokyo', 'UTC'],
    environmentOptions: [
        { value: 'production', label: 'Production' },
        { value: 'staging', label: 'Staging' },
        { value: 'development', label: 'Development' },
    ],
    localeOptions: [
        { value: 'en', label: 'English' },
        { value: 'ru', label: 'Russian' },
        { value: 'de', label: 'German' },
    ],
    dateFormatOptions: [
        { value: 'Y-m-d', label: 'YYYY-MM-DD' },
        { value: 'd/m/Y', label: 'DD/MM/YYYY' },
        { value: 'm/d/Y', label: 'MM/DD/YYYY' },
    ],
    canEdit: true,
    isOwner: true,
    editRoles: [],
};

describe('WorkspaceSettings', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // Mock usePage to return the props
        vi.spyOn(InertiaReact, 'usePage').mockReturnValue({
            props: defaultProps,
            url: '/settings/workspace',
            component: 'Settings/Workspace',
            version: null,
        } as any);
    });

    it('renders workspace name and description', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Test Workspace')).toBeInTheDocument();
        expect(screen.getByText(/A test workspace for unit tests/i)).toBeInTheDocument();
    });

    it('displays workspace owner information', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText(/Owner: John Doe/i)).toBeInTheDocument();
    });

    it('shows workspace statistics', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('5')).toBeInTheDocument(); // Projects
        expect(screen.getByText('3')).toBeInTheDocument(); // Servers
        expect(screen.getByText('12')).toBeInTheDocument(); // Applications
        expect(screen.getByText('8')).toBeInTheDocument(); // Members

        expect(screen.getByText('Projects')).toBeInTheDocument();
        expect(screen.getByText('Servers')).toBeInTheDocument();
        expect(screen.getByText('Applications')).toBeInTheDocument();
        expect(screen.getByText('Members')).toBeInTheDocument();
    });

    it('displays personal team badge when personalTeam is true', () => {
        const props = {
            ...defaultProps,
            workspace: { ...mockWorkspace, personalTeam: true },
        };
        vi.spyOn(InertiaReact, 'usePage').mockReturnValue({
            props,
            url: '/settings/workspace',
            component: 'Settings/Workspace',
            version: null,
        } as any);
        render(<WorkspaceSettings />);

        expect(screen.getByText('Personal')).toBeInTheDocument();
    });

    it('renders workspace name input field', () => {
        render(<WorkspaceSettings />);

        const nameInput = screen.getByDisplayValue('Test Workspace');
        expect(nameInput).toBeInTheDocument();
        expect(nameInput).toHaveAttribute('placeholder', 'My Workspace');
    });

    it('renders workspace slug input field as disabled', () => {
        render(<WorkspaceSettings />);

        const slugInput = screen.getByDisplayValue('test-workspace');
        expect(slugInput).toBeInTheDocument();
        expect(slugInput).toBeDisabled();
    });

    it('renders description textarea', () => {
        render(<WorkspaceSettings />);

        const descriptionTextarea = screen.getByDisplayValue('A test workspace for unit tests');
        expect(descriptionTextarea).toBeInTheDocument();
    });

    it('renders logo upload section', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Workspace Logo')).toBeInTheDocument();
        expect(screen.getByText('Upload Logo')).toBeInTheDocument();
        expect(screen.getByText(/Max 2MB/i)).toBeInTheDocument();
    });

    it('shows Change Logo button when logo exists', () => {
        const props = {
            ...defaultProps,
            workspace: { ...mockWorkspace, logo: 'logos/test.png' },
        };
        vi.spyOn(InertiaReact, 'usePage').mockReturnValue({
            props,
            url: '/settings/workspace',
            component: 'Settings/Workspace',
            version: null,
        } as any);
        render(<WorkspaceSettings />);

        expect(screen.getByText('Change Logo')).toBeInTheDocument();
    });

    it('renders timezone selector', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Timezone')).toBeInTheDocument();
    });

    it('renders default environment select', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Default Environment')).toBeInTheDocument();
        const select = screen.getByDisplayValue('Production');
        expect(select).toBeInTheDocument();
    });

    it('renders language select', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Language')).toBeInTheDocument();
        const select = screen.getByDisplayValue('English');
        expect(select).toBeInTheDocument();
    });

    it('renders date format select', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Date Format')).toBeInTheDocument();
        const select = screen.getByDisplayValue('YYYY-MM-DD');
        expect(select).toBeInTheDocument();
    });

    it('displays workspace URL with slug', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Workspace URL')).toBeInTheDocument();
        expect(screen.getByText('https://saturn.ac/w/test-workspace')).toBeInTheDocument();
    });

    it('renders save button when canEdit is true', () => {
        render(<WorkspaceSettings />);

        const saveButtons = screen.getAllByText('Save Changes');
        expect(saveButtons.length).toBeGreaterThan(0);
    });

    it('does not render save button when canEdit is false', () => {
        const props = { ...defaultProps, canEdit: false };
        vi.spyOn(InertiaReact, 'usePage').mockReturnValue({
            props,
            url: '/settings/workspace',
            component: 'Settings/Workspace',
            version: null,
        } as any);
        render(<WorkspaceSettings />);

        // Button with type="submit" inside form should not exist
        const saveButtons = screen.queryAllByText('Save Changes');
        expect(saveButtons.length).toBe(0);
    });

    it('renders danger zone for non-personal workspaces when canEdit is true', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Danger Zone')).toBeInTheDocument();
        expect(screen.getAllByText('Delete Workspace').length).toBeGreaterThan(0);
        expect(screen.getByText(/Permanently delete this workspace/i)).toBeInTheDocument();
    });

    it('does not render danger zone for personal workspaces', () => {
        const props = {
            ...defaultProps,
            workspace: { ...mockWorkspace, personalTeam: true },
        };
        vi.spyOn(InertiaReact, 'usePage').mockReturnValue({
            props,
            url: '/settings/workspace',
            component: 'Settings/Workspace',
            version: null,
        } as any);
        render(<WorkspaceSettings />);

        expect(screen.queryByText('Danger Zone')).not.toBeInTheDocument();
    });

    it('does not render danger zone when canEdit is false', () => {
        const props = { ...defaultProps, canEdit: false };
        vi.spyOn(InertiaReact, 'usePage').mockReturnValue({
            props,
            url: '/settings/workspace',
            component: 'Settings/Workspace',
            version: null,
        } as any);
        render(<WorkspaceSettings />);

        expect(screen.queryByText('Danger Zone')).not.toBeInTheDocument();
    });

    it('renders General settings section', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('General')).toBeInTheDocument();
        expect(screen.getByText('Basic workspace information')).toBeInTheDocument();
    });

    it('renders Defaults & Regional section', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText('Defaults & Regional')).toBeInTheDocument();
        expect(screen.getByText(/Configure default settings for new projects/i)).toBeInTheDocument();
    });

    it('displays created date when available', () => {
        render(<WorkspaceSettings />);

        expect(screen.getByText(/Created/i)).toBeInTheDocument();
    });

    it('disables form inputs when canEdit is false', () => {
        const props = { ...defaultProps, canEdit: false };
        vi.spyOn(InertiaReact, 'usePage').mockReturnValue({
            props,
            url: '/settings/workspace',
            component: 'Settings/Workspace',
            version: null,
        } as any);
        render(<WorkspaceSettings />);

        const nameInput = screen.getByDisplayValue('Test Workspace');
        expect(nameInput).toBeDisabled();

        const descriptionTextarea = screen.getByDisplayValue('A test workspace for unit tests');
        expect(descriptionTextarea).toBeDisabled();
    });
});
