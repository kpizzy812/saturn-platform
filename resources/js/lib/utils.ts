import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatDate(date: string | Date): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(date));
}

export function formatRelativeTime(date: string | Date): string {
    const now = new Date();
    const target = new Date(date);
    const diff = now.getTime() - target.getTime();

    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (days > 0) return `${days}d ago`;
    if (hours > 0) return `${hours}h ago`;
    if (minutes > 0) return `${minutes}m ago`;
    return 'just now';
}

/**
 * Formats bytes into a human-readable string (e.g., "1.5 GB", "256 MB")
 */
export function formatBytes(bytes: number, decimals = 1): string {
    if (bytes === 0) return '0 B';
    if (!bytes || isNaN(bytes)) return '-';

    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const i = Math.floor(Math.log(Math.abs(bytes)) / Math.log(k));

    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(decimals))} ${sizes[i]}`;
}

/**
 * Validates that a URL uses a safe protocol (http: or https:).
 * Prevents XSS attacks via javascript:, data:, vbscript: and other dangerous protocols.
 *
 * @param url - The URL to validate
 * @returns true if the URL is safe to open, false otherwise
 */
export function isSafeUrl(url: string | null | undefined): boolean {
    if (!url) return false;

    const trimmed = url.trim();
    if (!trimmed) return false;

    // Block protocol-relative URLs (//evil.com) - these can be used for open redirects
    if (trimmed.startsWith('//')) return false;

    // Allow relative paths starting with /
    if (trimmed.startsWith('/')) return true;

    try {
        const parsed = new URL(trimmed);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        // If URL parsing fails, it's not a valid URL
        return false;
    }
}

/**
 * Safely opens a URL in a new tab, only if it uses http: or https: protocol.
 * Returns false if the URL is unsafe and was not opened.
 *
 * @param url - The URL to open
 * @returns true if the URL was opened, false if blocked
 */
export function safeOpenUrl(url: string | null | undefined): boolean {
    if (!isSafeUrl(url)) {
        return false;
    }
    window.open(url!, '_blank', 'noopener,noreferrer');
    return true;
}
