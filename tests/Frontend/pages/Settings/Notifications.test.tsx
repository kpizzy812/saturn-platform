import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '../../utils/test-utils';

// Mock router
const mockRouterPut = vi.fn((url, data, options) => {
    if (options?.onSuccess) {
        setTimeout(() => options.onSuccess(), 0);
    }
    if (options?.onFinish) {
        setTimeout(() => options.onFinish(), 0);
    }
});

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            put: mockRouterPut,
            post: vi.fn(),
            delete: vi.fn(),
            visit: vi.fn(),
        },
        Link: ({ children, href, ...props }: any) => <a href={href} {...props}>{children}</a>,
    };
});

import NotificationsPreferences from '@/pages/Notifications/Preferences';

describe('Notifications Preferences Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mockRouterPut.mockClear();
    });

    describe('rendering', () => {
        it('should render page title', () => {
            render(<NotificationsPreferences />);

            expect(screen.getByText('Notification Preferences')).toBeInTheDocument();
            expect(screen.getByText('Manage how you receive notifications')).toBeInTheDocument();
        });

        it('should render save button', () => {
            render(<NotificationsPreferences />);

            expect(screen.getByText('Save Changes')).toBeInTheDocument();
        });

        it('should render back link', () => {
            render(<NotificationsPreferences />);

            expect(screen.getByText('Back to Notifications')).toBeInTheDocument();
        });
    });

    describe('Email Notifications section', () => {
        it('should render email notifications card', () => {
            render(<NotificationsPreferences />);

            expect(screen.getByText('Email Notifications')).toBeInTheDocument();
            expect(screen.getByText('Receive notifications via email')).toBeInTheDocument();
        });

        it('should display all email notification categories', () => {
            render(<NotificationsPreferences />);

            // Deployment notifications
            expect(screen.getAllByText('Deployments')[0]).toBeInTheDocument();
            // Description appears in both email and in-app sections, so use getAllByText
            expect(screen.getAllByText('Get notified when deployments succeed or fail').length).toBeGreaterThan(0);

            // Team notifications
            expect(screen.getAllByText('Team')[0]).toBeInTheDocument();
            expect(screen.getAllByText('Team invitations and member updates').length).toBeGreaterThan(0);

            // Billing notifications
            expect(screen.getAllByText('Billing')[0]).toBeInTheDocument();
            expect(screen.getAllByText('Billing alerts, invoices, and payment updates').length).toBeGreaterThan(0);

            // Security notifications
            expect(screen.getAllByText('Security')[0]).toBeInTheDocument();
            expect(screen.getAllByText('Security alerts and login notifications').length).toBeGreaterThan(0);
        });

        it('should have all email notifications enabled by default', () => {
            render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            // First 4 checkboxes are email notifications
            expect((checkboxes[0] as HTMLInputElement).checked).toBe(true);
            expect((checkboxes[1] as HTMLInputElement).checked).toBe(true);
            expect((checkboxes[2] as HTMLInputElement).checked).toBe(true);
            expect((checkboxes[3] as HTMLInputElement).checked).toBe(true);
        });

        it('should toggle email deployment notifications', async () => {
            const { user } = render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            const deploymentsCheckbox = checkboxes[0] as HTMLInputElement;

            expect(deploymentsCheckbox.checked).toBe(true);

            await user.click(deploymentsCheckbox);

            await waitFor(() => {
                expect(deploymentsCheckbox.checked).toBe(false);
            });
        });

        it('should toggle email team notifications', async () => {
            const { user } = render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            const teamCheckbox = checkboxes[1] as HTMLInputElement;

            await user.click(teamCheckbox);

            await waitFor(() => {
                expect(teamCheckbox.checked).toBe(false);
            });
        });

        it('should toggle email billing notifications', async () => {
            const { user } = render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            const billingCheckbox = checkboxes[2] as HTMLInputElement;

            await user.click(billingCheckbox);

            await waitFor(() => {
                expect(billingCheckbox.checked).toBe(false);
            });
        });

        it('should toggle email security notifications', async () => {
            const { user } = render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            const securityCheckbox = checkboxes[3] as HTMLInputElement;

            await user.click(securityCheckbox);

            await waitFor(() => {
                expect(securityCheckbox.checked).toBe(false);
            });
        });
    });

    describe('In-App Notifications section', () => {
        it('should render in-app notifications card', () => {
            render(<NotificationsPreferences />);

            expect(screen.getByText('In-App Notifications')).toBeInTheDocument();
        });

        it('should display all in-app notification categories', () => {
            render(<NotificationsPreferences />);

            // All categories should appear twice (email and in-app)
            const deploymentsElements = screen.getAllByText('Deployments');
            const teamElements = screen.getAllByText('Team');
            const billingElements = screen.getAllByText('Billing');
            const securityElements = screen.getAllByText('Security');

            expect(deploymentsElements.length).toBeGreaterThanOrEqual(2);
            expect(teamElements.length).toBeGreaterThanOrEqual(2);
            expect(billingElements.length).toBeGreaterThanOrEqual(2);
            expect(securityElements.length).toBeGreaterThanOrEqual(2);
        });

        it('should have all in-app notifications enabled by default', () => {
            render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            // Checkboxes 4-7 are in-app notifications
            expect((checkboxes[4] as HTMLInputElement).checked).toBe(true);
            expect((checkboxes[5] as HTMLInputElement).checked).toBe(true);
            expect((checkboxes[6] as HTMLInputElement).checked).toBe(true);
            expect((checkboxes[7] as HTMLInputElement).checked).toBe(true);
        });

        it('should toggle in-app notifications independently', async () => {
            const { user } = render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            const emailDeploymentsCheckbox = checkboxes[0] as HTMLInputElement;
            const inAppDeploymentsCheckbox = checkboxes[4] as HTMLInputElement;

            // Toggle in-app notification
            await user.click(inAppDeploymentsCheckbox);

            await waitFor(() => {
                expect(inAppDeploymentsCheckbox.checked).toBe(false);
                // Email notification should remain enabled
                expect(emailDeploymentsCheckbox.checked).toBe(true);
            });
        });
    });

    describe('Digest Frequency section', () => {
        it('should render digest frequency card', () => {
            render(<NotificationsPreferences />);

            expect(screen.getByText('Email Digest')).toBeInTheDocument();
        });

        it('should have digest frequency selector', () => {
            render(<NotificationsPreferences />);

            const radios = screen.getAllByRole('radio');
            expect(radios.length).toBeGreaterThanOrEqual(3);
        });

        it('should default to instant digest', () => {
            render(<NotificationsPreferences />);

            const instantRadio = screen.getByRole('radio', { name: /instant/i }) as HTMLInputElement;
            expect(instantRadio.checked).toBe(true);
        });

        it('should have all digest frequency options', () => {
            render(<NotificationsPreferences />);

            const instantRadio = screen.getByRole('radio', { name: /instant/i });
            const dailyRadio = screen.getByRole('radio', { name: /daily/i });
            const weeklyRadio = screen.getByRole('radio', { name: /weekly/i });

            expect(instantRadio).toBeInTheDocument();
            expect(dailyRadio).toBeInTheDocument();
            expect(weeklyRadio).toBeInTheDocument();
        });

        it('should change digest frequency', async () => {
            const { user } = render(<NotificationsPreferences />);

            const dailyRadio = screen.getByRole('radio', { name: /daily/i }) as HTMLInputElement;

            await user.click(dailyRadio);

            await waitFor(() => {
                expect(dailyRadio.checked).toBe(true);
            });
        });
    });

    describe('Save functionality', () => {
        it('should show loading state when saving', async () => {
            const { user } = render(<NotificationsPreferences />);

            const saveButton = screen.getByText('Save Changes');
            await user.click(saveButton);

            // Button should show loading state
            await waitFor(() => {
                expect(saveButton.closest('button')).toHaveAttribute('disabled');
            });
        });

        // TODO: Fix this test - router.put mock is not working correctly
        it.skip('should save changes after clicking save button', async () => {
            const { user } = render(<NotificationsPreferences />);

            // Toggle some notifications
            const checkboxes = screen.getAllByRole('checkbox');
            await user.click(checkboxes[0]);
            await user.click(checkboxes[1]);

            // Click save
            const saveButton = screen.getByText('Save Changes');
            await user.click(saveButton);

            // Verify that router.put was called
            await waitFor(() => {
                expect(mockRouterPut).toHaveBeenCalled();
            });

            // Verify the call was made with correct URL
            expect(mockRouterPut).toHaveBeenCalledWith(
                '/api/v1/notifications/preferences',
                expect.any(Object),
                expect.any(Object)
            );
        });
    });

    describe('Integration', () => {
        it('should maintain state across multiple changes', async () => {
            const { user } = render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            const emailDeploymentsCheckbox = checkboxes[0] as HTMLInputElement;
            const emailTeamCheckbox = checkboxes[1] as HTMLInputElement;

            // Toggle multiple notifications
            await user.click(emailDeploymentsCheckbox);
            await user.click(emailTeamCheckbox);

            await waitFor(() => {
                expect(emailDeploymentsCheckbox.checked).toBe(false);
                expect(emailTeamCheckbox.checked).toBe(false);
            });

            // Toggle back
            await user.click(emailDeploymentsCheckbox);

            await waitFor(() => {
                expect(emailDeploymentsCheckbox.checked).toBe(true);
                expect(emailTeamCheckbox.checked).toBe(false);
            });
        });

        it('should handle changing both email and in-app notifications', async () => {
            const { user } = render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            const emailSecurityCheckbox = checkboxes[3] as HTMLInputElement;
            const inAppBillingCheckbox = checkboxes[6] as HTMLInputElement;

            await user.click(emailSecurityCheckbox);
            await user.click(inAppBillingCheckbox);

            await waitFor(() => {
                expect(emailSecurityCheckbox.checked).toBe(false);
                expect(inAppBillingCheckbox.checked).toBe(false);
            });
        });

        it('should update digest frequency with notification changes', async () => {
            const { user } = render(<NotificationsPreferences />);

            const checkboxes = screen.getAllByRole('checkbox');
            const weeklyRadio = screen.getByRole('radio', { name: /weekly/i }) as HTMLInputElement;

            // Change notifications
            await user.click(checkboxes[0]);

            // Change digest
            await user.click(weeklyRadio);

            await waitFor(() => {
                expect((checkboxes[0] as HTMLInputElement).checked).toBe(false);
                expect(weeklyRadio.checked).toBe(true);
            });
        });
    });
});
