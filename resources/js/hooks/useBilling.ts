import * as React from 'react';

// Types
export interface BillingInfo {
    currentPlan: {
        id: string;
        name: string;
        price: number;
        billingCycle: 'monthly' | 'yearly';
        features: string[];
        status: 'active' | 'cancelled' | 'past_due';
    };
    nextBillingDate: string;
    estimatedCost: number;
    usage: UsageMetric[];
    subscription?: {
        id: string;
        status: string;
        current_period_start: string;
        current_period_end: string;
        cancel_at_period_end: boolean;
    };
}

export interface UsageMetric {
    label: string;
    current: number;
    limit: number;
    unit: string;
}

export interface PaymentMethod {
    id: string;
    type: 'card';
    card: {
        brand: string;
        last4: string;
        exp_month: number;
        exp_year: number;
    };
    billing_details: {
        name: string;
        email: string;
    };
    is_default: boolean;
}

export interface Invoice {
    id: string;
    invoice_number: string;
    created: number;
    due_date: number | null;
    amount_due: number;
    amount_paid: number;
    status: 'draft' | 'open' | 'paid' | 'uncollectible' | 'void';
    description: string;
    invoice_pdf: string | null;
    hosted_invoice_url: string | null;
}

export interface UsageDetails {
    period_start: string;
    period_end: string;
    services: ServiceUsage[];
    totals: {
        cpu_hours: number;
        memory_gb: number;
        network_gb: number;
        storage_gb: number;
        total_cost: number;
    };
}

export interface ServiceUsage {
    id: number;
    name: string;
    cpu_hours: number;
    memory_gb: number;
    network_gb: number;
    storage_gb: number;
    cost: number;
}

interface UseBillingInfoReturn {
    billingInfo: BillingInfo | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

interface UsePaymentMethodsReturn {
    paymentMethods: PaymentMethod[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    addPaymentMethod: (paymentMethodId: string) => Promise<void>;
    removePaymentMethod: (paymentMethodId: string) => Promise<void>;
    setDefaultPaymentMethod: (paymentMethodId: string) => Promise<void>;
}

interface UseInvoicesReturn {
    invoices: Invoice[];
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
    downloadInvoice: (invoiceId: string) => Promise<void>;
}

interface UseUsageDetailsReturn {
    usage: UsageDetails | null;
    isLoading: boolean;
    error: Error | null;
    refetch: () => Promise<void>;
}

interface UseSubscriptionReturn {
    updateSubscription: (planId: string, billingCycle: 'monthly' | 'yearly') => Promise<void>;
    cancelSubscription: () => Promise<void>;
    resumeSubscription: () => Promise<void>;
    isLoading: boolean;
    error: Error | null;
}

/**
 * Fetch billing information
 */
export function useBillingInfo(): UseBillingInfoReturn {
    const [billingInfo, setBillingInfo] = React.useState<BillingInfo | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchBillingInfo = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/billing/info', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch billing info: ${response.statusText}`);
            }

            const data = await response.json();
            setBillingInfo(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch billing info'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    React.useEffect(() => {
        fetchBillingInfo();
    }, [fetchBillingInfo]);

    return {
        billingInfo,
        isLoading,
        error,
        refetch: fetchBillingInfo,
    };
}

/**
 * Manage payment methods
 */
export function usePaymentMethods(): UsePaymentMethodsReturn {
    const [paymentMethods, setPaymentMethods] = React.useState<PaymentMethod[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchPaymentMethods = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/billing/payment-methods', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch payment methods: ${response.statusText}`);
            }

            const data = await response.json();
            setPaymentMethods(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch payment methods'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    const addPaymentMethod = React.useCallback(async (paymentMethodId: string) => {
        try {
            const response = await fetch('/api/v1/billing/payment-methods', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ payment_method_id: paymentMethodId }),
            });

            if (!response.ok) {
                throw new Error(`Failed to add payment method: ${response.statusText}`);
            }

            await fetchPaymentMethods();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to add payment method');
        }
    }, [fetchPaymentMethods]);

    const removePaymentMethod = React.useCallback(async (paymentMethodId: string) => {
        try {
            const response = await fetch(`/api/v1/billing/payment-methods/${paymentMethodId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to remove payment method: ${response.statusText}`);
            }

            await fetchPaymentMethods();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to remove payment method');
        }
    }, [fetchPaymentMethods]);

    const setDefaultPaymentMethod = React.useCallback(async (paymentMethodId: string) => {
        try {
            const response = await fetch(`/api/v1/billing/payment-methods/${paymentMethodId}/default`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to set default payment method: ${response.statusText}`);
            }

            await fetchPaymentMethods();
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to set default payment method');
        }
    }, [fetchPaymentMethods]);

    React.useEffect(() => {
        fetchPaymentMethods();
    }, [fetchPaymentMethods]);

    return {
        paymentMethods,
        isLoading,
        error,
        refetch: fetchPaymentMethods,
        addPaymentMethod,
        removePaymentMethod,
        setDefaultPaymentMethod,
    };
}

/**
 * Fetch invoices
 */
export function useInvoices(): UseInvoicesReturn {
    const [invoices, setInvoices] = React.useState<Invoice[]>([]);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchInvoices = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/billing/invoices', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch invoices: ${response.statusText}`);
            }

            const data = await response.json();
            setInvoices(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch invoices'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    const downloadInvoice = React.useCallback(async (invoiceId: string) => {
        try {
            const response = await fetch(`/api/v1/billing/invoices/${invoiceId}/download`, {
                headers: {
                    'Accept': 'application/pdf',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to download invoice: ${response.statusText}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `invoice-${invoiceId}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (err) {
            throw err instanceof Error ? err : new Error('Failed to download invoice');
        }
    }, []);

    React.useEffect(() => {
        fetchInvoices();
    }, [fetchInvoices]);

    return {
        invoices,
        isLoading,
        error,
        refetch: fetchInvoices,
        downloadInvoice,
    };
}

/**
 * Fetch usage details
 */
export function useUsageDetails(): UseUsageDetailsReturn {
    const [usage, setUsage] = React.useState<UsageDetails | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [error, setError] = React.useState<Error | null>(null);

    const fetchUsageDetails = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/billing/usage', {
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch usage details: ${response.statusText}`);
            }

            const data = await response.json();
            setUsage(data);
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to fetch usage details'));
        } finally {
            setIsLoading(false);
        }
    }, []);

    React.useEffect(() => {
        fetchUsageDetails();
    }, [fetchUsageDetails]);

    return {
        usage,
        isLoading,
        error,
        refetch: fetchUsageDetails,
    };
}

/**
 * Manage subscription
 */
export function useSubscription(): UseSubscriptionReturn {
    const [isLoading, setIsLoading] = React.useState(false);
    const [error, setError] = React.useState<Error | null>(null);

    const updateSubscription = React.useCallback(async (planId: string, billingCycle: 'monthly' | 'yearly') => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/billing/subscription', {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ plan_id: planId, billing_cycle: billingCycle }),
            });

            if (!response.ok) {
                throw new Error(`Failed to update subscription: ${response.statusText}`);
            }
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to update subscription'));
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const cancelSubscription = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/billing/subscription/cancel', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to cancel subscription: ${response.statusText}`);
            }
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to cancel subscription'));
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const resumeSubscription = React.useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);

            const response = await fetch('/api/v1/billing/subscription/resume', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(`Failed to resume subscription: ${response.statusText}`);
            }
        } catch (err) {
            setError(err instanceof Error ? err : new Error('Failed to resume subscription'));
            throw err;
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        updateSubscription,
        cancelSubscription,
        resumeSubscription,
        isLoading,
        error,
    };
}
