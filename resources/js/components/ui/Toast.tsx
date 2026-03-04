import * as React from 'react';
import { Transition } from '@headlessui/react';
import { cn } from '@/lib/utils';

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastAction {
    label: string;
    onClick: () => void;
}

interface Toast {
    id: string;
    type: ToastType;
    title: string;
    message?: string;
    duration?: number;
    persistent?: boolean;
    action?: ToastAction;
}

interface ToastAddOptions {
    duration?: number;
    persistent?: boolean;
    action?: ToastAction;
}

interface ToastOptions {
    title: string;
    description?: string;
    variant?: ToastType;
    duration?: number;
    persistent?: boolean;
    action?: ToastAction;
}

interface ToastContextType {
    toasts: Toast[];
    addToast: (type: ToastType, title: string, message?: string, options?: ToastAddOptions) => void;
    removeToast: (id: string) => void;
    toast: (options: ToastOptions) => void;
}

const ToastContext = React.createContext<ToastContextType | undefined>(undefined);

// Default durations per type (ms). 0 = persistent.
const DEFAULT_DURATIONS: Record<ToastType, number> = {
    success: 5000,
    error: 0,
    warning: 7000,
    info: 5000,
};

export function ToastProvider({ children }: { children: React.ReactNode }) {
    const [toasts, setToasts] = React.useState<Toast[]>([]);
    const timersRef = React.useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());

    const removeToast = React.useCallback((id: string) => {
        const timer = timersRef.current.get(id);
        if (timer) {
            clearTimeout(timer);
            timersRef.current.delete(id);
        }
        setToasts((prev) => prev.filter((t) => t.id !== id));
    }, []);

    const scheduleRemoval = React.useCallback((id: string, duration: number) => {
        if (duration <= 0) return;
        const timer = setTimeout(() => {
            timersRef.current.delete(id);
            setToasts((prev) => prev.filter((t) => t.id !== id));
        }, duration);
        timersRef.current.set(id, timer);
    }, []);

    const addToast = React.useCallback((type: ToastType, title: string, message?: string, options?: ToastAddOptions) => {
        const id = Math.random().toString(36).substring(7);
        const isPersistent = options?.persistent ?? false;
        const duration = isPersistent ? 0 : (options?.duration ?? DEFAULT_DURATIONS[type]);

        setToasts((prev) => [...prev, {
            id,
            type,
            title,
            message,
            duration,
            persistent: duration === 0,
            action: options?.action,
        }]);

        scheduleRemoval(id, duration);
    }, [scheduleRemoval]);

    const toast = React.useCallback((options: ToastOptions) => {
        addToast(
            options.variant || 'info',
            options.title,
            options.description,
            {
                duration: options.duration,
                persistent: options.persistent,
                action: options.action,
            },
        );
    }, [addToast]);

    // Cleanup timers on unmount
    React.useEffect(() => {
        const timers = timersRef.current;
        return () => {
            timers.forEach((timer) => clearTimeout(timer));
            timers.clear();
        };
    }, []);

    return (
        <ToastContext.Provider value={{ toasts, addToast, removeToast, toast }}>
            {children}
            <ToastContainer toasts={toasts} removeToast={removeToast} />
        </ToastContext.Provider>
    );
}

export function useToast() {
    const context = React.useContext(ToastContext);
    if (!context) {
        throw new Error('useToast must be used within a ToastProvider');
    }
    return context;
}

const typeStyles: Record<ToastType, string> = {
    success: 'border-primary bg-primary/10 text-primary',
    error: 'border-danger bg-danger/10 text-danger',
    warning: 'border-warning bg-warning/10 text-warning',
    info: 'border-info bg-info/10 text-info',
};

function ToastContainer({ toasts, removeToast }: { toasts: Toast[]; removeToast: (id: string) => void }) {
    return (
        <div
            className="fixed bottom-20 right-4 z-[60] flex flex-col gap-2"
            aria-live="polite"
            role="region"
            aria-label="Notifications"
        >
            {toasts.map((toast) => (
                <Transition
                    key={toast.id}
                    show={true}
                    appear={true}
                    enter="transform ease-out duration-300 transition"
                    enterFrom="translate-y-2 opacity-0"
                    enterTo="translate-y-0 opacity-100"
                    leave="transition ease-in duration-100"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div
                        className={cn(
                            'w-80 rounded-lg border p-4 shadow-lg',
                            'bg-background-secondary/80 backdrop-blur-md',
                            typeStyles[toast.type]
                        )}
                        role={toast.type === 'error' ? 'alert' : 'status'}
                    >
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <p className="font-medium">{toast.title}</p>
                                {toast.message && (
                                    <p className="mt-1 text-sm opacity-80">{toast.message}</p>
                                )}
                                {toast.action && (
                                    <button
                                        onClick={() => {
                                            toast.action!.onClick();
                                            removeToast(toast.id);
                                        }}
                                        className="mt-2 text-sm font-medium underline underline-offset-2 hover:no-underline"
                                    >
                                        {toast.action.label}
                                    </button>
                                )}
                            </div>
                            <button
                                onClick={() => removeToast(toast.id)}
                                className="ml-4 text-foreground-muted hover:text-foreground"
                                aria-label="Dismiss notification"
                            >
                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </Transition>
            ))}
        </div>
    );
}
