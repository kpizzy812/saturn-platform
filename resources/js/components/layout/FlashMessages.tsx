import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { useToast } from '@/components/ui/Toast';

/**
 * Component that listens to Inertia flash messages and displays them as toasts.
 * Must be used within ToastProvider context.
 */
export function FlashMessages() {
    const { flash } = usePage().props;
    const { addToast } = useToast();

    useEffect(() => {
        if (flash?.success) {
            addToast('success', flash.success);
        }
        if (flash?.error) {
            addToast('error', flash.error);
        }
        if (flash?.warning) {
            addToast('warning', flash.warning);
        }
        if (flash?.info) {
            addToast('info', flash.info);
        }
    }, [flash, addToast]);

    return null;
}
