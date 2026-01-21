import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge } from '@/components/ui';
import { Download, FileText, CreditCard, ChevronLeft, ChevronRight } from 'lucide-react';

interface Invoice {
    id: number;
    invoiceNumber: string;
    date: string;
    dueDate: string;
    amount: number;
    status: 'paid' | 'pending' | 'failed' | 'refunded';
    paymentMethod: string;
    description: string;
    downloadUrl?: string;
}

const mockInvoices: Invoice[] = [
    {
        id: 1,
        invoiceNumber: 'INV-2024-03-001',
        date: '2024-03-01',
        dueDate: '2024-03-15',
        amount: 102.00,
        status: 'paid',
        paymentMethod: 'Visa •••• 4242',
        description: 'Pro Plan + Usage',
        downloadUrl: '#',
    },
    {
        id: 2,
        invoiceNumber: 'INV-2024-02-001',
        date: '2024-02-01',
        dueDate: '2024-02-15',
        amount: 98.50,
        status: 'paid',
        paymentMethod: 'Visa •••• 4242',
        description: 'Pro Plan + Usage',
        downloadUrl: '#',
    },
    {
        id: 3,
        invoiceNumber: 'INV-2024-01-001',
        date: '2024-01-01',
        dueDate: '2024-01-15',
        amount: 105.20,
        status: 'paid',
        paymentMethod: 'Visa •••• 4242',
        description: 'Pro Plan + Usage',
        downloadUrl: '#',
    },
    {
        id: 4,
        invoiceNumber: 'INV-2023-12-001',
        date: '2023-12-01',
        dueDate: '2023-12-15',
        amount: 94.80,
        status: 'paid',
        paymentMethod: 'Visa •••• 4242',
        description: 'Pro Plan + Usage',
        downloadUrl: '#',
    },
    {
        id: 5,
        invoiceNumber: 'INV-2023-11-001',
        date: '2023-11-01',
        dueDate: '2023-11-15',
        amount: 89.30,
        status: 'paid',
        paymentMethod: 'Visa •••• 4242',
        description: 'Pro Plan + Usage',
        downloadUrl: '#',
    },
    {
        id: 6,
        invoiceNumber: 'INV-2023-10-001',
        date: '2023-10-01',
        dueDate: '2023-10-15',
        amount: 101.50,
        status: 'paid',
        paymentMethod: 'Visa •••• 4242',
        description: 'Pro Plan + Usage',
        downloadUrl: '#',
    },
    {
        id: 7,
        invoiceNumber: 'INV-2023-09-001',
        date: '2023-09-01',
        dueDate: '2023-09-15',
        amount: 20.00,
        status: 'refunded',
        paymentMethod: 'Visa •••• 4242',
        description: 'Pro Plan (Refunded)',
        downloadUrl: '#',
    },
    {
        id: 8,
        invoiceNumber: 'INV-2023-08-001',
        date: '2023-08-01',
        dueDate: '2023-08-15',
        amount: 97.60,
        status: 'paid',
        paymentMethod: 'Visa •••• 4242',
        description: 'Pro Plan + Usage',
        downloadUrl: '#',
    },
];

export default function BillingInvoices() {
    const [currentPage, setCurrentPage] = React.useState(1);
    const itemsPerPage = 10;
    const totalPages = Math.ceil(mockInvoices.length / itemsPerPage);

    const paginatedInvoices = mockInvoices.slice(
        (currentPage - 1) * itemsPerPage,
        currentPage * itemsPerPage
    );

    const getStatusBadgeVariant = (status: string): 'success' | 'warning' | 'danger' | 'default' => {
        switch (status) {
            case 'paid':
                return 'success';
            case 'pending':
                return 'warning';
            case 'failed':
                return 'danger';
            case 'refunded':
                return 'default';
            default:
                return 'default';
        }
    };

    const handleDownload = (invoice: Invoice) => {
        console.log('Downloading invoice:', invoice.invoiceNumber);
        // In a real app, this would trigger a download
    };

    const totalPaid = mockInvoices
        .filter((inv) => inv.status === 'paid')
        .reduce((sum, inv) => sum + inv.amount, 0);

    return (
        <SettingsLayout activeSection="billing">
            <div className="space-y-6">
                {/* Header with Stats */}
                <div className="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10">
                                    <FileText className="h-5 w-5 text-success" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Total Invoices</p>
                                    <p className="text-2xl font-bold text-foreground">{mockInvoices.length}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                                    <CreditCard className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Total Paid</p>
                                    <p className="text-2xl font-bold text-foreground">
                                        ${totalPaid.toFixed(2)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10">
                                    <FileText className="h-5 w-5 text-warning" />
                                </div>
                                <div>
                                    <p className="text-sm text-foreground-muted">Pending</p>
                                    <p className="text-2xl font-bold text-foreground">
                                        {mockInvoices.filter((inv) => inv.status === 'pending').length}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Invoices Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice History</CardTitle>
                        <CardDescription>
                            View and download all your invoices
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="border-b border-border">
                                    <tr className="text-left text-sm text-foreground-muted">
                                        <th className="pb-3 font-medium">Invoice</th>
                                        <th className="pb-3 font-medium">Date</th>
                                        <th className="pb-3 font-medium">Due Date</th>
                                        <th className="pb-3 font-medium">Description</th>
                                        <th className="pb-3 font-medium">Payment Method</th>
                                        <th className="pb-3 font-medium">Amount</th>
                                        <th className="pb-3 font-medium">Status</th>
                                        <th className="pb-3 font-medium text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {paginatedInvoices.map((invoice) => (
                                        <tr key={invoice.id} className="text-sm">
                                            <td className="py-4">
                                                <div className="flex items-center gap-2">
                                                    <FileText className="h-4 w-4 text-foreground-muted" />
                                                    <span className="font-mono font-medium text-foreground">
                                                        {invoice.invoiceNumber}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="py-4 text-foreground-muted">
                                                {new Date(invoice.date).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                })}
                                            </td>
                                            <td className="py-4 text-foreground-muted">
                                                {new Date(invoice.dueDate).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                })}
                                            </td>
                                            <td className="py-4 text-foreground-muted">{invoice.description}</td>
                                            <td className="py-4 text-foreground-muted">
                                                {invoice.paymentMethod}
                                            </td>
                                            <td className="py-4 font-medium text-foreground">
                                                ${invoice.amount.toFixed(2)}
                                            </td>
                                            <td className="py-4">
                                                <Badge
                                                    variant={getStatusBadgeVariant(invoice.status)}
                                                    className="capitalize"
                                                >
                                                    {invoice.status}
                                                </Badge>
                                            </td>
                                            <td className="py-4 text-right">
                                                {invoice.downloadUrl && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDownload(invoice)}
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="mt-6 flex items-center justify-between">
                                <p className="text-sm text-foreground-muted">
                                    Showing {(currentPage - 1) * itemsPerPage + 1} to{' '}
                                    {Math.min(currentPage * itemsPerPage, mockInvoices.length)} of{' '}
                                    {mockInvoices.length} invoices
                                </p>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                        disabled={currentPage === 1}
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>
                                    <div className="flex items-center gap-1">
                                        {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                                            <button
                                                key={page}
                                                onClick={() => setCurrentPage(page)}
                                                className={`h-8 w-8 rounded-md text-sm font-medium transition-colors ${
                                                    currentPage === page
                                                        ? 'bg-primary text-white'
                                                        : 'text-foreground-muted hover:bg-background-tertiary hover:text-foreground'
                                                }`}
                                            >
                                                {page}
                                            </button>
                                        ))}
                                    </div>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                        disabled={currentPage === totalPages}
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
