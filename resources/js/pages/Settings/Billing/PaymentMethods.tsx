import * as React from 'react';
import { SettingsLayout } from '../Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge, Input, Modal, ModalFooter } from '@/components/ui';
import { CreditCard, Plus, Trash2, Check, Lock } from 'lucide-react';

interface PaymentMethod {
    id: number;
    type: 'Visa' | 'Mastercard' | 'Amex' | 'Discover';
    last4: string;
    expiryMonth: string;
    expiryYear: string;
    isDefault: boolean;
    billingName: string;
    billingEmail: string;
}

const mockPaymentMethods: PaymentMethod[] = [
    {
        id: 1,
        type: 'Visa',
        last4: '4242',
        expiryMonth: '12',
        expiryYear: '2025',
        isDefault: true,
        billingName: 'John Doe',
        billingEmail: 'john@example.com',
    },
    {
        id: 2,
        type: 'Mastercard',
        last4: '5555',
        expiryMonth: '08',
        expiryYear: '2026',
        isDefault: false,
        billingName: 'John Doe',
        billingEmail: 'john@example.com',
    },
];

export default function PaymentMethods() {
    const [paymentMethods, setPaymentMethods] = React.useState<PaymentMethod[]>(mockPaymentMethods);
    const [showAddModal, setShowAddModal] = React.useState(false);
    const [showDeleteModal, setShowDeleteModal] = React.useState(false);
    const [selectedMethodId, setSelectedMethodId] = React.useState<number | null>(null);
    const [isProcessing, setIsProcessing] = React.useState(false);

    // Form state
    const [cardNumber, setCardNumber] = React.useState('');
    const [cardName, setCardName] = React.useState('');
    const [expiryDate, setExpiryDate] = React.useState('');
    const [cvc, setCvc] = React.useState('');
    const [billingEmail, setBillingEmail] = React.useState('');

    const handleSetDefault = (id: number) => {
        setPaymentMethods((prev) =>
            prev.map((method) => ({
                ...method,
                isDefault: method.id === id,
            }))
        );
    };

    const handleDeleteMethod = () => {
        if (selectedMethodId) {
            setPaymentMethods((prev) => prev.filter((method) => method.id !== selectedMethodId));
            setShowDeleteModal(false);
            setSelectedMethodId(null);
        }
    };

    const handleAddCard = (e: React.FormEvent) => {
        e.preventDefault();
        setIsProcessing(true);

        // Simulate API call
        setTimeout(() => {
            const [month, year] = expiryDate.split('/');
            const newMethod: PaymentMethod = {
                id: paymentMethods.length + 1,
                type: 'Visa', // Determine from card number in real implementation
                last4: cardNumber.slice(-4),
                expiryMonth: month,
                expiryYear: `20${year}`,
                isDefault: paymentMethods.length === 0,
                billingName: cardName,
                billingEmail: billingEmail,
            };

            setPaymentMethods((prev) => [...prev, newMethod]);
            setShowAddModal(false);
            setIsProcessing(false);

            // Reset form
            setCardNumber('');
            setCardName('');
            setExpiryDate('');
            setCvc('');
            setBillingEmail('');
        }, 1500);
    };

    const formatCardNumber = (value: string) => {
        const cleaned = value.replace(/\s/g, '');
        const formatted = cleaned.match(/.{1,4}/g)?.join(' ') || cleaned;
        return formatted;
    };

    const formatExpiryDate = (value: string) => {
        const cleaned = value.replace(/\D/g, '');
        if (cleaned.length >= 2) {
            return `${cleaned.slice(0, 2)}/${cleaned.slice(2, 4)}`;
        }
        return cleaned;
    };

    const getCardIcon = (type: string) => {
        // In a real app, use actual card brand icons
        return <CreditCard className="h-6 w-6" />;
    };

    return (
        <SettingsLayout activeSection="billing">
            <div className="space-y-6">
                {/* Header */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Payment Methods</CardTitle>
                                <CardDescription>
                                    Manage your payment methods and billing information
                                </CardDescription>
                            </div>
                            <Button onClick={() => setShowAddModal(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Card
                            </Button>
                        </div>
                    </CardHeader>
                </Card>

                {/* Payment Methods List */}
                {paymentMethods.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-background-tertiary">
                                <CreditCard className="h-8 w-8 text-foreground-muted" />
                            </div>
                            <h3 className="mt-4 text-lg font-medium text-foreground">No payment methods</h3>
                            <p className="mt-2 text-sm text-foreground-muted">
                                Add a payment method to start using paid features
                            </p>
                            <Button className="mt-6" onClick={() => setShowAddModal(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Your First Card
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {paymentMethods.map((method) => (
                            <Card key={method.id}>
                                <CardContent className="py-6">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-14 w-14 items-center justify-center rounded-lg bg-background-tertiary text-foreground-muted">
                                                {getCardIcon(method.type)}
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">
                                                        {method.type} •••• {method.last4}
                                                    </p>
                                                    {method.isDefault && (
                                                        <Badge variant="success" className="text-xs">
                                                            <Check className="mr-1 h-3 w-3" />
                                                            Default
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="mt-1 text-sm text-foreground-muted">
                                                    Expires {method.expiryMonth}/{method.expiryYear}
                                                </p>
                                                <p className="mt-1 text-xs text-foreground-subtle">
                                                    {method.billingName} • {method.billingEmail}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {!method.isDefault && (
                                                <Button
                                                    variant="secondary"
                                                    size="sm"
                                                    onClick={() => handleSetDefault(method.id)}
                                                >
                                                    Set as Default
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    setSelectedMethodId(method.id);
                                                    setShowDeleteModal(true);
                                                }}
                                                disabled={method.isDefault && paymentMethods.length === 1}
                                            >
                                                <Trash2 className="h-4 w-4 text-danger" />
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Security Notice */}
                <Card className="border-primary/20 bg-primary/5">
                    <CardContent className="py-4">
                        <div className="flex items-center gap-3">
                            <Lock className="h-5 w-5 text-primary" />
                            <div>
                                <p className="text-sm font-medium text-foreground">
                                    Your payment information is secure
                                </p>
                                <p className="text-xs text-foreground-muted">
                                    We use Stripe to process payments. Your card details are encrypted and never stored on our servers.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Add Card Modal */}
            <Modal
                isOpen={showAddModal}
                onClose={() => setShowAddModal(false)}
                title="Add Payment Method"
                description="Enter your card details to add a new payment method"
            >
                <form onSubmit={handleAddCard} className="space-y-4">
                    <Input
                        label="Card Number"
                        value={cardNumber}
                        onChange={(e) => setCardNumber(formatCardNumber(e.target.value.replace(/\s/g, '')))}
                        placeholder="1234 5678 9012 3456"
                        maxLength={19}
                        required
                    />
                    <Input
                        label="Cardholder Name"
                        value={cardName}
                        onChange={(e) => setCardName(e.target.value)}
                        placeholder="John Doe"
                        required
                    />
                    <div className="grid grid-cols-2 gap-4">
                        <Input
                            label="Expiry Date"
                            value={expiryDate}
                            onChange={(e) => setExpiryDate(formatExpiryDate(e.target.value))}
                            placeholder="MM/YY"
                            maxLength={5}
                            required
                        />
                        <Input
                            label="CVC"
                            value={cvc}
                            onChange={(e) => setCvc(e.target.value.replace(/\D/g, ''))}
                            placeholder="123"
                            maxLength={4}
                            required
                        />
                    </div>
                    <Input
                        label="Billing Email"
                        type="email"
                        value={billingEmail}
                        onChange={(e) => setBillingEmail(e.target.value)}
                        placeholder="billing@example.com"
                        required
                    />

                    <div className="rounded-lg border border-border bg-background-secondary p-4">
                        <div className="flex items-start gap-3">
                            <Lock className="mt-0.5 h-4 w-4 text-foreground-muted" />
                            <p className="text-xs text-foreground-muted">
                                Your payment information is encrypted and secure. We never store your full card details.
                            </p>
                        </div>
                    </div>

                    <ModalFooter>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setShowAddModal(false)}
                            disabled={isProcessing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" loading={isProcessing}>
                            Add Card
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>

            {/* Delete Confirmation Modal */}
            <Modal
                isOpen={showDeleteModal}
                onClose={() => setShowDeleteModal(false)}
                title="Remove Payment Method"
                description="Are you sure you want to remove this payment method? This action cannot be undone."
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowDeleteModal(false)}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleDeleteMethod}>
                        Remove Card
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
