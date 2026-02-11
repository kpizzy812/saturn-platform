import React, { ReactElement } from 'react';
import { render, RenderOptions } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import { ToastProvider } from '@/components/ui/Toast';
import { ConfirmationProvider } from '@/components/ui/ConfirmationModal';
import { ThemeProvider } from '@/components/ui/ThemeProvider';

// Create router mock first so it can be referenced
const mockRouter = {
    visit: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
};

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Head: ({ children, title }: { children?: React.ReactNode; title?: string }) => (
        <title>{title}</title>
    ),
    Link: ({ children, href, method, as, ...props }: any) => {
        if (as === 'button') {
            return <button {...props}>{children}</button>;
        }
        return <a href={href} {...props}>{children}</a>;
    },
    usePage: () => ({
        url: '/dashboard',
        props: {
            // Support both legacy (auth.user) and new (auth) structure
            auth: {
                // New structure: auth directly contains user fields
                name: 'Test User',
                email: 'test@example.com',
                // Legacy structure: auth.user
                user: { id: 1, name: 'Test User', email: 'test@example.com' },
                team: { id: 1, name: 'Test Team' },
            },
            flash: {},
        },
    }),
    useForm: (initialValues: any = {}) => {
        const [data, setDataState] = React.useState(initialValues);
        const [errors, setErrors] = React.useState({});
        const [processing, setProcessing] = React.useState(false);

        const setData = (key: string | Record<string, any>, value?: any) => {
            if (typeof key === 'string') {
                setDataState((prev: any) => ({ ...prev, [key]: value }));
            } else {
                setDataState(key);
            }
        };

        const post = vi.fn((url: string, options?: any) => {
            setProcessing(true);
            // Call the router.post mock
            mockRouter.post(url, data, options);
            setProcessing(false);
        });

        const put = vi.fn((url: string, options?: any) => {
            setProcessing(true);
            mockRouter.put(url, data, options);
            setProcessing(false);
        });

        const patch = vi.fn((url: string, options?: any) => {
            setProcessing(true);
            mockRouter.patch(url, data, options);
            setProcessing(false);
        });

        const deleteMethod = vi.fn((url: string, options?: any) => {
            setProcessing(true);
            mockRouter.delete(url, options);
            setProcessing(false);
        });

        const reset = vi.fn(() => setDataState(initialValues));
        const clearErrors = vi.fn(() => setErrors({}));

        return {
            data,
            setData,
            post,
            put,
            patch,
            delete: deleteMethod,
            reset,
            errors,
            setError: vi.fn((field: string, value: string) => {
                setErrors((prev: any) => ({ ...prev, [field]: value }));
            }),
            clearErrors,
            processing,
            wasSuccessful: false,
            recentlySuccessful: false,
            hasErrors: Object.keys(errors).length > 0,
            transform: vi.fn((callback: (data: any) => any) => {
                return { data: callback(data) };
            }),
        };
    },
    router: mockRouter,
}));

// Providers wrapper
const AllTheProviders = ({ children }: { children: React.ReactNode }) => {
    return (
        <ThemeProvider>
            <ToastProvider>
                <ConfirmationProvider>
                    {children}
                </ConfirmationProvider>
            </ToastProvider>
        </ThemeProvider>
    );
};

const customRender = (
    ui: ReactElement,
    options?: Omit<RenderOptions, 'wrapper'>,
) => {
    return {
        user: userEvent.setup(),
        ...render(ui, { wrapper: AllTheProviders, ...options }),
    };
};

export * from '@testing-library/react';
export { customRender as render };
