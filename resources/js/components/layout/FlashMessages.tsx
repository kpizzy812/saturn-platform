import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';
import { useToast } from '@/components/ui/Toast';

/**
 * Component that listens to Inertia flash messages and displays them as toasts.
 * Must be used within ToastProvider context.
 * Uses a ref to track shown messages and avoid duplicates while still
 * showing new messages even on same-page redirects.
 */
export function FlashMessages() {
    const { flash } = usePage<{ flash: { success?: string; error?: string; warning?: string; info?: string } }>().props;
    const { addToast } = useToast();
    const shownRef = useRef<string | null>(null);

    useEffect(() => {
        // Create a unique key for current flash state
        const flashKey = JSON.stringify(flash);

        // Skip if we've already shown this exact flash combination
        if (shownRef.current === flashKey) {
            return;
        }

        // Show flash messages
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

        // Mark as shown only if there was something to show
        if (flash?.success || flash?.error || flash?.warning || flash?.info) {
            shownRef.current = flashKey;
        }
    }, [flash, addToast]);

    // Reset shown ref when flash becomes empty (page navigation without flash)
    useEffect(() => {
        if (!flash?.success && !flash?.error && !flash?.warning && !flash?.info) {
            shownRef.current = null;
        }
    }, [flash]);

    return null;
}
