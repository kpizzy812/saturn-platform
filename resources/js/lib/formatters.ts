/**
 * Format a date as a relative time string with minute-level precision.
 * Examples: "Just now", "3 min ago", "2 hours ago", "5 days ago"
 */
export function formatTimeAgo(date?: string | Date | null): string {
    if (!date) return 'Never';

    const now = new Date();
    const then = date instanceof Date ? date : new Date(date);

    if (isNaN(then.getTime())) return 'Never';

    const diffMs = now.getTime() - then.getTime();
    if (diffMs < 0) return 'Just now';

    const minutes = Math.floor(diffMs / (1000 * 60));
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes} min${minutes > 1 ? 's' : ''} ago`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;

    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;

    const weeks = Math.floor(days / 7);
    if (weeks < 5) return `${weeks} week${weeks > 1 ? 's' : ''} ago`;

    const months = Math.floor(days / 30);
    if (months < 12) return `${months} month${months > 1 ? 's' : ''} ago`;

    const years = Math.floor(days / 365);
    return `${years} year${years > 1 ? 's' : ''} ago`;
}

/**
 * Format a snake_case or lowercase status string to Title Case.
 * Examples: "in_progress" → "In Progress", "running" → "Running"
 */
export function formatStatus(status: string): string {
    if (!status) return '';
    return status
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}
