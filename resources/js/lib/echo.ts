import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Laravel Echo
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'pusher'> | null;
    }
}

window.Pusher = Pusher;

/**
 * Initialize Laravel Echo for WebSocket connections
 *
 * Configuration:
 * - Uses Pusher protocol (compatible with Soketi)
 * - Connects to private team and user channels
 * - Supports development and production environments
 * - Gracefully handles test environments where WebSocket may not be available
 */
export function initializeEcho(): Echo<'pusher'> | null {
    // Skip initialization in test environments or when window is not available
    if (typeof window === 'undefined') {
        console.debug('Echo initialization skipped: window not available');
        return null;
    }

    // Skip if already initialized
    if (window.Echo) {
        return window.Echo;
    }

    try {
        // Check if WebSocket is explicitly disabled
        const wsEnabled = import.meta.env.VITE_PUSHER_ENABLED !== 'false';
        if (!wsEnabled) {
            console.debug('Echo initialization skipped: WebSocket disabled via VITE_PUSHER_ENABLED');
            window.Echo = null;
            return null;
        }

        // Get WebSocket configuration from environment
        // Auto-detect hostname if not set or if set to internal Docker hostname
        const configuredHost = import.meta.env.VITE_PUSHER_HOST;
        const isInternalDockerHost = configuredHost === 'saturn-realtime' ||
                                     configuredHost?.endsWith('-realtime') ||
                                     configuredHost?.includes('localhost') && window.location.hostname !== 'localhost';

        // Use current domain if auto-detecting or if configured host is internal Docker name
        const wsHost = (!configuredHost || isInternalDockerHost) ? window.location.hostname : configuredHost;
        const isAutoDetected = !configuredHost || isInternalDockerHost;

        if (isInternalDockerHost) {
            console.debug('Echo: VITE_PUSHER_HOST is internal Docker hostname, using auto-detection instead:', wsHost);
        }

        const wsScheme = import.meta.env.VITE_PUSHER_SCHEME || (window.location.protocol === 'https:' ? 'wss' : 'ws');
        const forceTLS = wsScheme === 'wss' || wsScheme === 'https';
        // In production with auto-detection, use the same port as the page (nginx proxies WebSocket)
        // For HTTPS: 443, for HTTP: current port or 80
        const currentPort = window.location.port ? parseInt(window.location.port, 10) : (forceTLS ? 443 : 80);
        const wsPort = import.meta.env.VITE_PUSHER_PORT || (isAutoDetected ? currentPort : 6001);
        const wssPort = import.meta.env.VITE_PUSHER_WSS_PORT || wsPort;
        const wsKey = import.meta.env.VITE_PUSHER_APP_KEY || 'saturn';

        // Get CSRF token - if not available, we're probably in a test environment
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!csrfToken) {
            console.debug('Echo initialization skipped: CSRF token not found (likely test environment)');
            window.Echo = null;
            return null;
        }

        const wsCluster = import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1';

        const echo = new Echo({
            broadcaster: 'pusher',
            key: wsKey,
            cluster: wsCluster,
            wsHost: wsHost,
            wsPort: wsPort,
            wssPort: wssPort,
            forceTLS: forceTLS,
            encrypted: true,
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            // CSRF token for authentication
            auth: {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
            },
            // Authorization endpoint for private channels
            authEndpoint: '/broadcasting/auth',
        });

        window.Echo = echo as Echo<'pusher'>;
        console.debug('Laravel Echo initialized successfully');
        return echo as Echo<'pusher'>;
    } catch (error) {
        console.error('Failed to initialize Laravel Echo:', error);
        window.Echo = null;
        return null;
    }
}

/**
 * Get or initialize the Echo instance
 */
export function getEcho(): Echo<'pusher'> | null {
    if (window.Echo) {
        return window.Echo;
    }
    return initializeEcho();
}

/**
 * Check if Echo is connected and ready
 */
export function isEchoConnected(): boolean {
    return window.Echo !== null && window.Echo !== undefined;
}

/**
 * Disconnect Echo and clean up
 */
export function disconnectEcho(): void {
    if (window.Echo) {
        window.Echo.disconnect();
        window.Echo = null;
    }
}
