import * as React from 'react';
import { Modal, ModalFooter } from './Modal';
import { Button } from './Button';
import { AlertTriangle, Trash2, RefreshCw, Info } from 'lucide-react';
import { cn } from '@/lib/utils';

type ConfirmationVariant = 'danger' | 'warning' | 'info' | 'default';

interface ConfirmationModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void | Promise<void>;
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: ConfirmationVariant;
    isLoading?: boolean;
    icon?: React.ReactNode;
}

const variantConfig = {
    danger: {
        icon: Trash2,
        iconClassName: 'text-danger',
        bgClassName: 'bg-danger/10',
        buttonVariant: 'danger' as const,
    },
    warning: {
        icon: AlertTriangle,
        iconClassName: 'text-warning',
        bgClassName: 'bg-warning/10',
        buttonVariant: 'warning' as const,
    },
    info: {
        icon: Info,
        iconClassName: 'text-primary',
        bgClassName: 'bg-primary/10',
        buttonVariant: 'default' as const,
    },
    default: {
        icon: RefreshCw,
        iconClassName: 'text-foreground-muted',
        bgClassName: 'bg-background-tertiary',
        buttonVariant: 'default' as const,
    },
};

export function ConfirmationModal({
    isOpen,
    onClose,
    onConfirm,
    title,
    description,
    confirmText = 'Confirm',
    cancelText = 'Cancel',
    variant = 'default',
    isLoading = false,
    icon,
}: ConfirmationModalProps) {
    const config = variantConfig[variant];
    const IconComponent = config.icon;

    const handleConfirm = async () => {
        await onConfirm();
        if (!isLoading) {
            onClose();
        }
    };

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="sm">
            <div className="flex flex-col items-center text-center">
                {/* Icon */}
                <div className={cn('mb-4 flex h-12 w-12 items-center justify-center rounded-full', config.bgClassName)}>
                    {icon || <IconComponent className={cn('h-6 w-6', config.iconClassName)} />}
                </div>

                {/* Title */}
                <h3 className="text-lg font-semibold text-foreground">{title}</h3>

                {/* Description */}
                {description && (
                    <p className="mt-2 text-sm text-foreground-muted">{description}</p>
                )}
            </div>

            <ModalFooter className="mt-6 justify-center">
                <Button variant="secondary" onClick={onClose} disabled={isLoading}>
                    {cancelText}
                </Button>
                <Button
                    variant={config.buttonVariant}
                    onClick={handleConfirm}
                    loading={isLoading}
                >
                    {confirmText}
                </Button>
            </ModalFooter>
        </Modal>
    );
}

// Hook for easy confirmation modal usage
interface UseConfirmationOptions {
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: ConfirmationVariant;
    onConfirm: () => void | Promise<void>;
}

interface UseConfirmationReturn {
    isOpen: boolean;
    open: () => void;
    close: () => void;
    ConfirmationDialog: React.FC;
}

export function useConfirmation(options: UseConfirmationOptions): UseConfirmationReturn {
    const [isOpen, setIsOpen] = React.useState(false);
    const [isLoading, setIsLoading] = React.useState(false);

    const open = React.useCallback(() => setIsOpen(true), []);
    const close = React.useCallback(() => {
        if (!isLoading) {
            setIsOpen(false);
        }
    }, [isLoading]);

    const handleConfirm = React.useCallback(async () => {
        setIsLoading(true);
        try {
            await options.onConfirm();
            setIsOpen(false);
        } finally {
            setIsLoading(false);
        }
    }, [options.onConfirm]);

    const ConfirmationDialog = React.useCallback(
        () => (
            <ConfirmationModal
                isOpen={isOpen}
                onClose={close}
                onConfirm={handleConfirm}
                title={options.title}
                description={options.description}
                confirmText={options.confirmText}
                cancelText={options.cancelText}
                variant={options.variant}
                isLoading={isLoading}
            />
        ),
        [isOpen, isLoading, close, handleConfirm, options]
    );

    return {
        isOpen,
        open,
        close,
        ConfirmationDialog,
    };
}

// Simplified confirm function that returns a Promise (for inline usage)
interface ConfirmOptions {
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: ConfirmationVariant;
}

// Context for global confirmation modal
interface ConfirmationContextValue {
    confirm: (options: ConfirmOptions) => Promise<boolean>;
}

const ConfirmationContext = React.createContext<ConfirmationContextValue | null>(null);

export function ConfirmationProvider({ children }: { children: React.ReactNode }) {
    const [state, setState] = React.useState<{
        isOpen: boolean;
        options: ConfirmOptions | null;
        resolve: ((value: boolean) => void) | null;
    }>({
        isOpen: false,
        options: null,
        resolve: null,
    });

    const confirm = React.useCallback((options: ConfirmOptions): Promise<boolean> => {
        return new Promise((resolve) => {
            setState({
                isOpen: true,
                options,
                resolve,
            });
        });
    }, []);

    const handleConfirm = React.useCallback(() => {
        state.resolve?.(true);
        setState({ isOpen: false, options: null, resolve: null });
    }, [state.resolve]);

    const handleClose = React.useCallback(() => {
        state.resolve?.(false);
        setState({ isOpen: false, options: null, resolve: null });
    }, [state.resolve]);

    return (
        <ConfirmationContext.Provider value={{ confirm }}>
            {children}
            {state.options && (
                <ConfirmationModal
                    isOpen={state.isOpen}
                    onClose={handleClose}
                    onConfirm={handleConfirm}
                    title={state.options.title}
                    description={state.options.description}
                    confirmText={state.options.confirmText}
                    cancelText={state.options.cancelText}
                    variant={state.options.variant}
                />
            )}
        </ConfirmationContext.Provider>
    );
}

export function useConfirm() {
    const context = React.useContext(ConfirmationContext);
    if (!context) {
        throw new Error('useConfirm must be used within a ConfirmationProvider');
    }
    return context.confirm;
}
