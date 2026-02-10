import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '../../utils/test-utils';

// Mock the @inertiajs/react module
const mockRouterPost = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    router: {
        visit: vi.fn(),
        post: mockRouterPost,
        delete: vi.fn(),
        patch: vi.fn(),
    },
    usePage: () => ({
        props: {
            auth: {
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
            },
        },
    }),
}));

// Mock validation library
vi.mock('@/lib/validation', () => ({
    validateDockerCompose: vi.fn((value: string) => {
        if (!value || value.trim() === '') {
            return { valid: false, error: 'Docker Compose configuration is required' };
        }
        if (!value.includes('services:')) {
            return { valid: false, error: 'Invalid Docker Compose format: missing services section' };
        }
        return { valid: true };
    }),
}));

// Import after mocks
import ServiceCreate from '@/pages/Services/Create';

describe('Service Create Page', () => {
    beforeEach(() => {
        mockRouterPost.mockClear();
    });

    const navigateToCustomMode = async () => {
        const customButton = screen.getByText('Custom Docker Compose');
        fireEvent.click(customButton);
        await waitFor(() => {
            expect(screen.getByText('Configure')).toBeInTheDocument();
        });
    };

    it('renders the page header', () => {
        render(<ServiceCreate />);
        expect(screen.getByText('Create a new service')).toBeInTheDocument();
        expect(screen.getByText('Choose how you want to create your service')).toBeInTheDocument();
    });

    it('shows back to services link', () => {
        render(<ServiceCreate />);
        const backLink = screen.getByText('Back to Services').closest('a');
        expect(backLink).toBeInTheDocument();
        expect(backLink?.getAttribute('href')).toBe('/services');
    });

    it('shows step indicators', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();
        expect(screen.getByText('Configure')).toBeInTheDocument();
        expect(screen.getByText('Review')).toBeInTheDocument();
    });

    it('starts on step 1 (Configure)', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();
        // Step 1 indicator should be active
        const stepIndicators = document.querySelectorAll('[class*="border-primary"]');
        expect(stepIndicators.length).toBeGreaterThan(0);
    });

    it('renders service name input on step 1', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();
        const nameInput = screen.getByPlaceholderText('my-service');
        expect(nameInput).toBeInTheDocument();
        expect(screen.getByText('Service Name')).toBeInTheDocument();
    });

    it('renders description input on step 1', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();
        const descInput = screen.getByPlaceholderText('Production multi-container application');
        expect(descInput).toBeInTheDocument();
        expect(screen.getByText('Description (Optional)')).toBeInTheDocument();
    });

    it('renders docker compose textarea on step 1', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();
        expect(screen.getByText('Docker Compose Configuration')).toBeInTheDocument();
        const textarea = document.querySelector('textarea');
        expect(textarea).toBeInTheDocument();
    });

    it('continue button is disabled when form is incomplete', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();
        const continueButton = screen.getByText('Continue').closest('button');
        expect(continueButton).toBeDisabled();
    });

    it('continue button is enabled when required fields are filled', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        fireEvent.change(nameInput, { target: { value: 'test-service' } });
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"\nservices:\n  web:\n    image: nginx' } });

        await waitFor(() => {
            const continueButton = screen.getByText('Continue').closest('button');
            expect(continueButton).not.toBeDisabled();
        });
    });

    it('validates docker compose configuration', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        // Invalid docker compose (missing services section)
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"' } });

        await waitFor(() => {
            expect(screen.getByText('Invalid Docker Compose format: missing services section')).toBeInTheDocument();
        });
    });

    it('allows user to fill in service name', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();
        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;

        fireEvent.change(nameInput, { target: { value: 'production-service' } });

        expect(nameInput.value).toBe('production-service');
    });

    it('allows user to fill in description', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();
        const descInput = screen.getByPlaceholderText('Production multi-container application') as HTMLInputElement;

        fireEvent.change(descInput, { target: { value: 'My test service' } });

        expect(descInput.value).toBe('My test service');
    });

    it('advances to step 2 when continue is clicked', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        fireEvent.change(nameInput, { target: { value: 'test-service' } });
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"\nservices:\n  web:\n    image: nginx' } });

        await waitFor(() => {
            const continueButton = screen.getByText('Continue');
            fireEvent.click(continueButton);
        });

        await waitFor(() => {
            expect(screen.getByText('Review Configuration')).toBeInTheDocument();
        });
    });

    it('shows review summary on step 2', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        fireEvent.change(nameInput, { target: { value: 'my-production-service' } });
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"\nservices:\n  web:\n    image: nginx' } });

        const continueButton = screen.getByText('Continue');
        fireEvent.click(continueButton);

        await waitFor(() => {
            expect(screen.getByText('Review Configuration')).toBeInTheDocument();
            expect(screen.getByText('my-production-service')).toBeInTheDocument();
            expect(screen.getByText('Docker Compose Service')).toBeInTheDocument();
        });
    });

    it('shows what happens next section on step 2', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        fireEvent.change(nameInput, { target: { value: 'test-service' } });
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"\nservices:\n  web:\n    image: nginx' } });

        const continueButton = screen.getByText('Continue');
        fireEvent.click(continueButton);

        await waitFor(() => {
            expect(screen.getByText('What happens next?')).toBeInTheDocument();
            expect(screen.getByText('Service will be created on your server')).toBeInTheDocument();
            expect(screen.getByText('Docker containers will be deployed')).toBeInTheDocument();
            expect(screen.getByText("You'll be redirected to the service dashboard")).toBeInTheDocument();
        });
    });

    it('shows back button on step 2', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        fireEvent.change(nameInput, { target: { value: 'test-service' } });
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"\nservices:\n  web:\n    image: nginx' } });

        const continueButton = screen.getByText('Continue');
        fireEvent.click(continueButton);

        await waitFor(() => {
            expect(screen.getByText('Back')).toBeInTheDocument();
        });
    });

    it('shows create service button on step 2', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        fireEvent.change(nameInput, { target: { value: 'test-service' } });
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"\nservices:\n  web:\n    image: nginx' } });

        const continueButton = screen.getByText('Continue');
        fireEvent.click(continueButton);

        await waitFor(() => {
            expect(screen.getByText('Create Service')).toBeInTheDocument();
        });
    });

    it('can navigate back from step 2 to step 1', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        fireEvent.change(nameInput, { target: { value: 'test-service' } });
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"\nservices:\n  web:\n    image: nginx' } });

        const continueButton = screen.getByText('Continue');
        fireEvent.click(continueButton);

        await waitFor(() => {
            expect(screen.getByText('Review Configuration')).toBeInTheDocument();
        });

        const backButton = screen.getByText('Back');
        fireEvent.click(backButton);

        await waitFor(() => {
            expect(screen.getByPlaceholderText('my-service')).toBeInTheDocument();
        });
    });

    it('create service button is available on step 2', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const descInput = screen.getByPlaceholderText('Production multi-container application') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;

        fireEvent.change(nameInput, { target: { value: 'test-service' } });
        fireEvent.change(descInput, { target: { value: 'Test description' } });
        fireEvent.change(dockerComposeInput, { target: { value: 'version: "3.8"\nservices:\n  web:\n    image: nginx' } });

        const continueButton = screen.getByText('Continue');
        fireEvent.click(continueButton);

        await waitFor(() => {
            const createButton = screen.getByText('Create Service').closest('button');
            expect(createButton).toBeInTheDocument();
            expect(createButton).not.toBeDisabled();
        });
    });

    it('displays docker compose preview on step 2', async () => {
        render(<ServiceCreate />);
        await navigateToCustomMode();

        const nameInput = screen.getByPlaceholderText('my-service') as HTMLInputElement;
        const dockerComposeInput = document.querySelector('textarea') as HTMLTextAreaElement;
        const composeConfig = 'version: "3.8"\nservices:\n  web:\n    image: nginx';

        fireEvent.change(nameInput, { target: { value: 'test-service' } });
        fireEvent.change(dockerComposeInput, { target: { value: composeConfig } });

        const continueButton = screen.getByText('Continue');
        fireEvent.click(continueButton);

        await waitFor(() => {
            const pre = document.querySelector('pre');
            expect(pre?.textContent).toBe(composeConfig);
        });
    });
});
