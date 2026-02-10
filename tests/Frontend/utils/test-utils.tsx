import React, { ReactElement } from 'react';
import { render, RenderOptions } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import { ToastProvider } from '@/components/ui/Toast';
import { ConfirmationProvider } from '@/components/ui/ConfirmationModal';
import { ThemeProvider } from '@/components/ui/ThemeProvider';

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
    router: {
        visit: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
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
