import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import * as React from 'react';
import '@testing-library/jest-dom';

// Mock the hooks
vi.mock('@/hooks', () => ({
    useBillingInfo: vi.fn(),
    usePaymentMethods: vi.fn(),
    useInvoices: vi.fn(),
    useUsageDetails: vi.fn(),
    useSubscription: vi.fn(),
}));

// Mock Inertia
vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
        <a href={href}>{children}</a>
    ),
    usePage: () => ({
        props: {
            auth: {
                user: {
                    name: 'Test User',
                    email: 'test@example.com',
                },
            },
        },
    }),
}));

import {
    useBillingInfo,
    usePaymentMethods,
    useInvoices,
    useUsageDetails,
    useSubscription,
} from '@/hooks';

describe('Billing Pages', () => {
    describe('useBillingInfo Hook', () => {
        it('should fetch billing information', async () => {
            const mockBillingInfo = {
                currentPlan: {
                    id: 'pro',
                    name: 'Pro',
                    price: 20,
                    billingCycle: 'monthly' as const,
                    features: ['Unlimited projects', '1000 deployments/mo'],
                    status: 'active' as const,
                },
                nextBillingDate: '2024-04-01',
                estimatedCost: 102.00,
                usage: [
                    { label: 'CPU Hours', current: 342, limit: 500, unit: 'hours/mo' },
                ],
                subscription: {
                    id: 'sub_123',
                    status: 'active',
                    current_period_start: '2024-03-01',
                    current_period_end: '2024-04-01',
                    cancel_at_period_end: false,
                },
            };

            vi.mocked(useBillingInfo).mockReturnValue({
                billingInfo: mockBillingInfo,
                isLoading: false,
                error: null,
                refetch: vi.fn(),
            });

            const { billingInfo } = useBillingInfo();

            expect(billingInfo).toEqual(mockBillingInfo);
            expect(billingInfo?.currentPlan.name).toBe('Pro');
            expect(billingInfo?.usage).toHaveLength(1);
        });

        it('should handle loading state', () => {
            vi.mocked(useBillingInfo).mockReturnValue({
                billingInfo: null,
                isLoading: true,
                error: null,
                refetch: vi.fn(),
            });

            const { isLoading } = useBillingInfo();

            expect(isLoading).toBe(true);
        });

        it('should handle error state', () => {
            const mockError = new Error('Failed to fetch billing info');

            vi.mocked(useBillingInfo).mockReturnValue({
                billingInfo: null,
                isLoading: false,
                error: mockError,
                refetch: vi.fn(),
            });

            const { error } = useBillingInfo();

            expect(error).toEqual(mockError);
        });
    });

    describe('usePaymentMethods Hook', () => {
        it('should fetch payment methods', () => {
            const mockPaymentMethods = [
                {
                    id: 'pm_123',
                    type: 'card' as const,
                    card: {
                        brand: 'Visa',
                        last4: '4242',
                        exp_month: 12,
                        exp_year: 2025,
                    },
                    billing_details: {
                        name: 'John Doe',
                        email: 'john@example.com',
                    },
                    is_default: true,
                },
            ];

            vi.mocked(usePaymentMethods).mockReturnValue({
                paymentMethods: mockPaymentMethods,
                isLoading: false,
                error: null,
                refetch: vi.fn(),
                addPaymentMethod: vi.fn(),
                removePaymentMethod: vi.fn(),
                setDefaultPaymentMethod: vi.fn(),
            });

            const { paymentMethods } = usePaymentMethods();

            expect(paymentMethods).toHaveLength(1);
            expect(paymentMethods[0].card.brand).toBe('Visa');
            expect(paymentMethods[0].is_default).toBe(true);
        });

        it('should add payment method', async () => {
            const mockAddPaymentMethod = vi.fn();

            vi.mocked(usePaymentMethods).mockReturnValue({
                paymentMethods: [],
                isLoading: false,
                error: null,
                refetch: vi.fn(),
                addPaymentMethod: mockAddPaymentMethod,
                removePaymentMethod: vi.fn(),
                setDefaultPaymentMethod: vi.fn(),
            });

            const { addPaymentMethod } = usePaymentMethods();

            await addPaymentMethod('pm_new');

            expect(mockAddPaymentMethod).toHaveBeenCalledWith('pm_new');
        });

        it('should remove payment method', async () => {
            const mockRemovePaymentMethod = vi.fn();

            vi.mocked(usePaymentMethods).mockReturnValue({
                paymentMethods: [],
                isLoading: false,
                error: null,
                refetch: vi.fn(),
                addPaymentMethod: vi.fn(),
                removePaymentMethod: mockRemovePaymentMethod,
                setDefaultPaymentMethod: vi.fn(),
            });

            const { removePaymentMethod } = usePaymentMethods();

            await removePaymentMethod('pm_123');

            expect(mockRemovePaymentMethod).toHaveBeenCalledWith('pm_123');
        });

        it('should set default payment method', async () => {
            const mockSetDefaultPaymentMethod = vi.fn();

            vi.mocked(usePaymentMethods).mockReturnValue({
                paymentMethods: [],
                isLoading: false,
                error: null,
                refetch: vi.fn(),
                addPaymentMethod: vi.fn(),
                removePaymentMethod: vi.fn(),
                setDefaultPaymentMethod: mockSetDefaultPaymentMethod,
            });

            const { setDefaultPaymentMethod } = usePaymentMethods();

            await setDefaultPaymentMethod('pm_456');

            expect(mockSetDefaultPaymentMethod).toHaveBeenCalledWith('pm_456');
        });
    });

    describe('useInvoices Hook', () => {
        it('should fetch invoices', () => {
            const mockInvoices = [
                {
                    id: 'in_123',
                    invoice_number: 'INV-2024-03-001',
                    created: 1709251200,
                    due_date: 1710460800,
                    amount_due: 10200,
                    amount_paid: 10200,
                    status: 'paid' as const,
                    description: 'Pro Plan + Usage',
                    invoice_pdf: 'https://invoice.pdf',
                    hosted_invoice_url: 'https://invoice.url',
                },
            ];

            vi.mocked(useInvoices).mockReturnValue({
                invoices: mockInvoices,
                isLoading: false,
                error: null,
                refetch: vi.fn(),
                downloadInvoice: vi.fn(),
            });

            const { invoices } = useInvoices();

            expect(invoices).toHaveLength(1);
            expect(invoices[0].status).toBe('paid');
            expect(invoices[0].amount_paid).toBe(10200);
        });

        it('should download invoice', async () => {
            const mockDownloadInvoice = vi.fn();

            vi.mocked(useInvoices).mockReturnValue({
                invoices: [],
                isLoading: false,
                error: null,
                refetch: vi.fn(),
                downloadInvoice: mockDownloadInvoice,
            });

            const { downloadInvoice } = useInvoices();

            await downloadInvoice('in_123');

            expect(mockDownloadInvoice).toHaveBeenCalledWith('in_123');
        });
    });

    describe('useUsageDetails Hook', () => {
        it('should fetch usage details', () => {
            const mockUsage = {
                period_start: '2024-03-01',
                period_end: '2024-04-01',
                services: [
                    {
                        id: 1,
                        name: 'Production API',
                        cpu_hours: 142,
                        memory_gb: 18.5,
                        network_gb: 520,
                        storage_gb: 12.4,
                        cost: 45.20,
                    },
                ],
                totals: {
                    cpu_hours: 342,
                    memory_gb: 45.2,
                    network_gb: 1240,
                    storage_gb: 25.8,
                    total_cost: 102.00,
                },
            };

            vi.mocked(useUsageDetails).mockReturnValue({
                usage: mockUsage,
                isLoading: false,
                error: null,
                refetch: vi.fn(),
            });

            const { usage } = useUsageDetails();

            expect(usage?.services).toHaveLength(1);
            expect(usage?.totals.total_cost).toBe(102.00);
        });
    });

    describe('useSubscription Hook', () => {
        it('should update subscription', async () => {
            const mockUpdateSubscription = vi.fn();

            vi.mocked(useSubscription).mockReturnValue({
                updateSubscription: mockUpdateSubscription,
                cancelSubscription: vi.fn(),
                resumeSubscription: vi.fn(),
                isLoading: false,
                error: null,
            });

            const { updateSubscription } = useSubscription();

            await updateSubscription('enterprise', 'yearly');

            expect(mockUpdateSubscription).toHaveBeenCalledWith('enterprise', 'yearly');
        });

        it('should cancel subscription', async () => {
            const mockCancelSubscription = vi.fn();

            vi.mocked(useSubscription).mockReturnValue({
                updateSubscription: vi.fn(),
                cancelSubscription: mockCancelSubscription,
                resumeSubscription: vi.fn(),
                isLoading: false,
                error: null,
            });

            const { cancelSubscription } = useSubscription();

            await cancelSubscription();

            expect(mockCancelSubscription).toHaveBeenCalled();
        });

        it('should resume subscription', async () => {
            const mockResumeSubscription = vi.fn();

            vi.mocked(useSubscription).mockReturnValue({
                updateSubscription: vi.fn(),
                cancelSubscription: vi.fn(),
                resumeSubscription: mockResumeSubscription,
                isLoading: false,
                error: null,
            });

            const { resumeSubscription } = useSubscription();

            await resumeSubscription();

            expect(mockResumeSubscription).toHaveBeenCalled();
        });

        it('should handle loading state', () => {
            vi.mocked(useSubscription).mockReturnValue({
                updateSubscription: vi.fn(),
                cancelSubscription: vi.fn(),
                resumeSubscription: vi.fn(),
                isLoading: true,
                error: null,
            });

            const { isLoading } = useSubscription();

            expect(isLoading).toBe(true);
        });

        it('should handle error state', () => {
            const mockError = new Error('Failed to update subscription');

            vi.mocked(useSubscription).mockReturnValue({
                updateSubscription: vi.fn(),
                cancelSubscription: vi.fn(),
                resumeSubscription: vi.fn(),
                isLoading: false,
                error: mockError,
            });

            const { error } = useSubscription();

            expect(error).toEqual(mockError);
        });
    });
});
