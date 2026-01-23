import React, { ReactElement } from 'react';
import { render, RenderOptions } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import { ToastProvider } from '@/components/ui/Toast';
import { ConfirmationProvider } from '@/components/ui/ConfirmationModal';

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
            auth: {
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
        <ToastProvider>
            <ConfirmationProvider>
                {children}
            </ConfirmationProvider>
        </ToastProvider>
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
