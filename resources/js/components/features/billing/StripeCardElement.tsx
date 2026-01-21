import * as React from 'react';
import { Alert } from '@/components/ui';
import { CreditCard, Lock } from 'lucide-react';

/**
 * StripeCardElement Component
 *
 * A styled card input component that integrates with Stripe Elements.
 * This is a simplified version that works without @stripe/react-stripe-js.
 *
 * For full Stripe integration, install: npm install @stripe/react-stripe-js @stripe/stripe-js
 * Then replace this component with the actual Stripe CardElement.
 */

interface StripeCardElementProps {
    onReady?: () => void;
    onChange?: (event: { complete: boolean; error?: { message: string } }) => void;
    className?: string;
}

export function StripeCardElement({ onReady, onChange, className = '' }: StripeCardElementProps) {
    const [cardNumber, setCardNumber] = React.useState('');
    const [expiryDate, setExpiryDate] = React.useState('');
    const [cvc, setCvc] = React.useState('');
    const [focused, setFocused] = React.useState<'number' | 'expiry' | 'cvc' | null>(null);
    const [error, setError] = React.useState<string | null>(null);

    // Format card number with spaces
    const formatCardNumber = (value: string) => {
        const cleaned = value.replace(/\s/g, '');
        const formatted = cleaned.match(/.{1,4}/g)?.join(' ') || cleaned;
        return formatted;
    };

    // Format expiry date as MM/YY
    const formatExpiryDate = (value: string) => {
        const cleaned = value.replace(/\D/g, '');
        if (cleaned.length >= 2) {
            return `${cleaned.slice(0, 2)}/${cleaned.slice(2, 4)}`;
        }
        return cleaned;
    };

    // Validate card number (basic Luhn algorithm)
    const validateCardNumber = (number: string): boolean => {
        const cleaned = number.replace(/\s/g, '');
        if (!/^\d+$/.test(cleaned) || cleaned.length < 13 || cleaned.length > 19) {
            return false;
        }

        let sum = 0;
        let isEven = false;

        for (let i = cleaned.length - 1; i >= 0; i--) {
            let digit = parseInt(cleaned.charAt(i), 10);

            if (isEven) {
                digit *= 2;
                if (digit > 9) {
                    digit -= 9;
                }
            }

            sum += digit;
            isEven = !isEven;
        }

        return sum % 10 === 0;
    };

    // Validate expiry date
    const validateExpiryDate = (date: string): boolean => {
        const [month, year] = date.split('/');
        if (!month || !year || month.length !== 2 || year.length !== 2) {
            return false;
        }

        const monthNum = parseInt(month, 10);
        if (monthNum < 1 || monthNum > 12) {
            return false;
        }

        const currentYear = new Date().getFullYear() % 100;
        const currentMonth = new Date().getMonth() + 1;
        const yearNum = parseInt(year, 10);

        if (yearNum < currentYear || (yearNum === currentYear && monthNum < currentMonth)) {
            return false;
        }

        return true;
    };

    // Validate CVC
    const validateCVC = (cvc: string): boolean => {
        return /^\d{3,4}$/.test(cvc);
    };

    // Check if the form is complete
    const isComplete = React.useMemo(() => {
        const cardValid = validateCardNumber(cardNumber);
        const expiryValid = validateExpiryDate(expiryDate);
        const cvcValid = validateCVC(cvc);
        return cardValid && expiryValid && cvcValid;
    }, [cardNumber, expiryDate, cvc]);

    // Notify parent component of changes
    React.useEffect(() => {
        if (onChange) {
            onChange({
                complete: isComplete,
                error: error ? { message: error } : undefined,
            });
        }
    }, [isComplete, error, onChange]);

    // Notify parent when ready
    React.useEffect(() => {
        if (onReady) {
            onReady();
        }
    }, [onReady]);

    const handleCardNumberChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value.replace(/\s/g, '');
        if (value.length <= 19) {
            setCardNumber(formatCardNumber(value));
            if (value.length > 0 && !validateCardNumber(value) && value.length >= 13) {
                setError('Invalid card number');
            } else {
                setError(null);
            }
        }
    };

    const handleExpiryDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value.replace(/\D/g, '');
        if (value.length <= 4) {
            setExpiryDate(formatExpiryDate(value));
            if (value.length === 4 && !validateExpiryDate(formatExpiryDate(value))) {
                setError('Invalid or expired date');
            } else {
                setError(null);
            }
        }
    };

    const handleCVCChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value.replace(/\D/g, '');
        if (value.length <= 4) {
            setCvc(value);
            if (value.length > 0 && !validateCVC(value) && value.length >= 3) {
                setError('Invalid CVC');
            } else {
                setError(null);
            }
        }
    };

    return (
        <div className={`space-y-4 ${className}`}>
            {/* Card Number */}
            <div>
                <label className="mb-2 block text-sm font-medium text-foreground">
                    Card Number
                </label>
                <div className={`relative rounded-lg border ${
                    focused === 'number' ? 'border-primary ring-1 ring-primary' : 'border-border'
                } bg-background transition-colors`}>
                    <div className="absolute left-3 top-1/2 -translate-y-1/2">
                        <CreditCard className="h-5 w-5 text-foreground-muted" />
                    </div>
                    <input
                        type="text"
                        value={cardNumber}
                        onChange={handleCardNumberChange}
                        onFocus={() => setFocused('number')}
                        onBlur={() => setFocused(null)}
                        placeholder="1234 5678 9012 3456"
                        className="w-full rounded-lg border-0 bg-transparent py-3 pl-11 pr-4 text-foreground placeholder-foreground-subtle focus:outline-none"
                    />
                </div>
            </div>

            {/* Expiry Date and CVC */}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="mb-2 block text-sm font-medium text-foreground">
                        Expiry Date
                    </label>
                    <input
                        type="text"
                        value={expiryDate}
                        onChange={handleExpiryDateChange}
                        onFocus={() => setFocused('expiry')}
                        onBlur={() => setFocused(null)}
                        placeholder="MM/YY"
                        className={`w-full rounded-lg border ${
                            focused === 'expiry' ? 'border-primary ring-1 ring-primary' : 'border-border'
                        } bg-background px-4 py-3 text-foreground placeholder-foreground-subtle transition-colors focus:outline-none`}
                    />
                </div>

                <div>
                    <label className="mb-2 block text-sm font-medium text-foreground">
                        CVC
                    </label>
                    <input
                        type="text"
                        value={cvc}
                        onChange={handleCVCChange}
                        onFocus={() => setFocused('cvc')}
                        onBlur={() => setFocused(null)}
                        placeholder="123"
                        className={`w-full rounded-lg border ${
                            focused === 'cvc' ? 'border-primary ring-1 ring-primary' : 'border-border'
                        } bg-background px-4 py-3 text-foreground placeholder-foreground-subtle transition-colors focus:outline-none`}
                    />
                </div>
            </div>

            {/* Error Message */}
            {error && (
                <Alert variant="danger" className="mt-2">
                    {error}
                </Alert>
            )}

            {/* Security Notice */}
            <div className="rounded-lg border border-border bg-background-secondary p-3">
                <div className="flex items-start gap-3">
                    <Lock className="mt-0.5 h-4 w-4 flex-shrink-0 text-foreground-muted" />
                    <p className="text-xs text-foreground-muted">
                        Your payment information is encrypted and secure. We use Stripe to process payments.
                        Your card details are never stored on our servers.
                    </p>
                </div>
            </div>

            {/* Integration Note */}
            <div className="rounded-lg border border-warning/20 bg-warning/5 p-3">
                <p className="text-xs text-foreground-muted">
                    <strong>Note:</strong> This is a demonstration component. For production use, install{' '}
                    <code className="rounded bg-background px-1 py-0.5 font-mono text-xs">
                        @stripe/react-stripe-js
                    </code>{' '}
                    and replace this with the official Stripe CardElement component.
                </p>
            </div>
        </div>
    );
}

/**
 * Get card brand from number
 */
export function getCardBrand(number: string): string {
    const cleaned = number.replace(/\s/g, '');

    if (/^4/.test(cleaned)) return 'Visa';
    if (/^5[1-5]/.test(cleaned)) return 'Mastercard';
    if (/^3[47]/.test(cleaned)) return 'American Express';
    if (/^6(?:011|5)/.test(cleaned)) return 'Discover';

    return 'Unknown';
}

/**
 * Example usage with real Stripe Elements:
 *
 * import { Elements } from '@stripe/react-stripe-js';
 * import { loadStripe } from '@stripe/stripe-js';
 *
 * const stripePromise = loadStripe('your_publishable_key');
 *
 * function PaymentForm() {
 *   return (
 *     <Elements stripe={stripePromise}>
 *       <StripeCardElement />
 *     </Elements>
 *   );
 * }
 */
