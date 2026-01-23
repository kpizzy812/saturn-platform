import { describe, it, expect } from 'vitest';
import {
    getStatusConfig,
    getStatusVariant,
    getStatusColor,
    getStatusTextColor,
    getStatusLabel,
    isAnimatedStatus,
    getStatusDotClass,
    isPositiveStatus,
    isNegativeStatus,
    isLoadingStatus,
    getAggregateStatus,
    getStatusBadgeConfig,
    type StatusConfig,
    type BadgeVariant,
} from '../statusUtils';

describe('statusUtils', () => {
    describe('getStatusConfig', () => {
        it('returns config for known deployment statuses', () => {
            const config = getStatusConfig('finished');
            expect(config.label).toBe('Finished');
            expect(config.variant).toBe('success');
            expect(config.iconName).toBe('CheckCircle');
        });

        it('returns config for failed status', () => {
            const config = getStatusConfig('failed');
            expect(config.label).toBe('Failed');
            expect(config.variant).toBe('danger');
            expect(config.colorClass).toBe('bg-red-500');
        });

        it('returns config for in_progress status with animation', () => {
            const config = getStatusConfig('in_progress');
            expect(config.label).toBe('In Progress');
            expect(config.variant).toBe('warning');
            expect(config.animate).toBe(true);
        });

        it('returns default config for unknown status', () => {
            const config = getStatusConfig('unknown_status_xyz');
            expect(config.label).toBe('Unknown');
            expect(config.variant).toBe('default');
            expect(config.iconName).toBe('AlertCircle');
        });

        it('handles case-insensitive status', () => {
            const config1 = getStatusConfig('RUNNING');
            const config2 = getStatusConfig('Running');
            const config3 = getStatusConfig('running');

            expect(config1.label).toBe('Running');
            expect(config2.label).toBe('Running');
            expect(config3.label).toBe('Running');
        });

        it('returns config for application statuses', () => {
            expect(getStatusConfig('running').variant).toBe('success');
            expect(getStatusConfig('stopped').variant).toBe('default');
            expect(getStatusConfig('building').variant).toBe('warning');
            expect(getStatusConfig('deploying').variant).toBe('warning');
        });

        it('returns config for health statuses', () => {
            expect(getStatusConfig('healthy').variant).toBe('success');
            expect(getStatusConfig('unhealthy').variant).toBe('danger');
            expect(getStatusConfig('degraded').variant).toBe('warning');
        });

        it('returns config for cron job statuses', () => {
            expect(getStatusConfig('enabled').variant).toBe('success');
            expect(getStatusConfig('disabled').variant).toBe('default');
        });

        it('returns danger config for exited status', () => {
            const config = getStatusConfig('exited');
            expect(config.label).toBe('Exited');
            expect(config.variant).toBe('danger');
            expect(config.colorClass).toBe('bg-red-500');
            expect(config.textColorClass).toBe('text-danger');
            expect(config.dotClass).toBe('status-error');
        });
    });

    describe('getStatusVariant', () => {
        it('returns success for positive statuses', () => {
            expect(getStatusVariant('finished')).toBe('success');
            expect(getStatusVariant('running')).toBe('success');
            expect(getStatusVariant('healthy')).toBe('success');
            expect(getStatusVariant('completed')).toBe('success');
        });

        it('returns danger for negative statuses', () => {
            expect(getStatusVariant('failed')).toBe('danger');
            expect(getStatusVariant('error')).toBe('danger');
            expect(getStatusVariant('unhealthy')).toBe('danger');
            expect(getStatusVariant('expired')).toBe('danger');
            expect(getStatusVariant('exited')).toBe('danger');
        });

        it('returns warning for in-progress statuses', () => {
            expect(getStatusVariant('in_progress')).toBe('warning');
            expect(getStatusVariant('deploying')).toBe('warning');
            expect(getStatusVariant('building')).toBe('warning');
            expect(getStatusVariant('degraded')).toBe('warning');
        });

        it('returns info for pending statuses', () => {
            expect(getStatusVariant('queued')).toBe('info');
            expect(getStatusVariant('pending')).toBe('info');
            expect(getStatusVariant('initializing')).toBe('info');
        });

        it('returns default for neutral statuses', () => {
            expect(getStatusVariant('stopped')).toBe('default');
            expect(getStatusVariant('cancelled')).toBe('default');
            expect(getStatusVariant('disabled')).toBe('default');
        });
    });

    describe('getStatusColor', () => {
        it('returns bg-green-500 for running', () => {
            expect(getStatusColor('running')).toBe('bg-green-500');
        });

        it('returns bg-red-500 for failed', () => {
            expect(getStatusColor('failed')).toBe('bg-red-500');
        });

        it('returns bg-yellow-500 for in_progress', () => {
            expect(getStatusColor('in_progress')).toBe('bg-yellow-500');
        });

        it('returns bg-gray-500 for stopped', () => {
            expect(getStatusColor('stopped')).toBe('bg-gray-500');
        });

        it('returns bg-orange-500 for deleting', () => {
            expect(getStatusColor('deleting')).toBe('bg-orange-500');
        });

        it('returns bg-red-500 for exited', () => {
            expect(getStatusColor('exited')).toBe('bg-red-500');
        });
    });

    describe('getStatusTextColor', () => {
        it('returns text-primary for success statuses', () => {
            expect(getStatusTextColor('running')).toBe('text-primary');
            expect(getStatusTextColor('finished')).toBe('text-primary');
        });

        it('returns text-danger for error statuses', () => {
            expect(getStatusTextColor('failed')).toBe('text-danger');
            expect(getStatusTextColor('error')).toBe('text-danger');
            expect(getStatusTextColor('exited')).toBe('text-danger');
        });

        it('returns text-warning for in-progress statuses', () => {
            expect(getStatusTextColor('in_progress')).toBe('text-warning');
            expect(getStatusTextColor('deploying')).toBe('text-warning');
        });

        it('returns text-foreground-muted for neutral statuses', () => {
            expect(getStatusTextColor('stopped')).toBe('text-foreground-muted');
            expect(getStatusTextColor('cancelled')).toBe('text-foreground-muted');
        });
    });

    describe('getStatusLabel', () => {
        it('returns human-readable label', () => {
            expect(getStatusLabel('in_progress')).toBe('In Progress');
            expect(getStatusLabel('finished')).toBe('Finished');
            expect(getStatusLabel('expiring_soon')).toBe('Expiring Soon');
        });

        it('returns Unknown for unknown status', () => {
            expect(getStatusLabel('xyz_unknown')).toBe('Unknown');
        });
    });

    describe('isAnimatedStatus', () => {
        it('returns true for loading statuses', () => {
            expect(isAnimatedStatus('in_progress')).toBe(true);
            expect(isAnimatedStatus('deploying')).toBe(true);
            expect(isAnimatedStatus('building')).toBe(true);
            expect(isAnimatedStatus('initializing')).toBe(true);
        });

        it('returns false for static statuses', () => {
            expect(isAnimatedStatus('finished')).toBe(false);
            expect(isAnimatedStatus('failed')).toBe(false);
            expect(isAnimatedStatus('running')).toBe(false);
            expect(isAnimatedStatus('stopped')).toBe(false);
        });
    });

    describe('getStatusDotClass', () => {
        it('returns dot class for known statuses', () => {
            expect(getStatusDotClass('running')).toBe('status-online');
            expect(getStatusDotClass('stopped')).toBe('status-stopped');
            expect(getStatusDotClass('deploying')).toBe('status-deploying');
            expect(getStatusDotClass('error')).toBe('status-error');
        });

        it('returns empty string for statuses without dot class', () => {
            expect(getStatusDotClass('enabled')).toBe('');
            expect(getStatusDotClass('unknown')).toBe('');
        });
    });

    describe('utility functions', () => {
        describe('isPositiveStatus', () => {
            it('returns true for positive statuses', () => {
                expect(isPositiveStatus('finished')).toBe(true);
                expect(isPositiveStatus('running')).toBe(true);
                expect(isPositiveStatus('online')).toBe(true);
                expect(isPositiveStatus('active')).toBe(true);
                expect(isPositiveStatus('enabled')).toBe(true);
                expect(isPositiveStatus('completed')).toBe(true);
                expect(isPositiveStatus('success')).toBe(true);
                expect(isPositiveStatus('healthy')).toBe(true);
            });

            it('returns false for non-positive statuses', () => {
                expect(isPositiveStatus('failed')).toBe(false);
                expect(isPositiveStatus('stopped')).toBe(false);
                expect(isPositiveStatus('in_progress')).toBe(false);
            });

            it('is case-insensitive', () => {
                expect(isPositiveStatus('RUNNING')).toBe(true);
                expect(isPositiveStatus('Running')).toBe(true);
            });
        });

        describe('isNegativeStatus', () => {
            it('returns true for negative statuses', () => {
                expect(isNegativeStatus('failed')).toBe(true);
                expect(isNegativeStatus('error')).toBe(true);
                expect(isNegativeStatus('expired')).toBe(true);
                expect(isNegativeStatus('unhealthy')).toBe(true);
                expect(isNegativeStatus('offline')).toBe(true);
                expect(isNegativeStatus('cancelled')).toBe(true);
            });

            it('returns false for non-negative statuses', () => {
                expect(isNegativeStatus('running')).toBe(false);
                expect(isNegativeStatus('stopped')).toBe(false);
                expect(isNegativeStatus('in_progress')).toBe(false);
            });

            it('is case-insensitive', () => {
                expect(isNegativeStatus('FAILED')).toBe(true);
                expect(isNegativeStatus('Failed')).toBe(true);
            });
        });

        describe('isLoadingStatus', () => {
            it('returns true for loading statuses', () => {
                expect(isLoadingStatus('in_progress')).toBe(true);
                expect(isLoadingStatus('deploying')).toBe(true);
                expect(isLoadingStatus('building')).toBe(true);
                expect(isLoadingStatus('creating')).toBe(true);
                expect(isLoadingStatus('deleting')).toBe(true);
                expect(isLoadingStatus('initializing')).toBe(true);
                expect(isLoadingStatus('starting')).toBe(true);
                expect(isLoadingStatus('restarting')).toBe(true);
                expect(isLoadingStatus('verifying')).toBe(true);
                expect(isLoadingStatus('queued')).toBe(true);
                expect(isLoadingStatus('pending')).toBe(true);
            });

            it('returns false for non-loading statuses', () => {
                expect(isLoadingStatus('running')).toBe(false);
                expect(isLoadingStatus('stopped')).toBe(false);
                expect(isLoadingStatus('failed')).toBe(false);
            });
        });

        describe('getAggregateStatus', () => {
            it('returns error if any status is negative', () => {
                expect(getAggregateStatus(['running', 'failed', 'running'])).toBe('error');
                expect(getAggregateStatus(['error'])).toBe('error');
            });

            it('returns deploying if any status is loading', () => {
                expect(getAggregateStatus(['running', 'deploying', 'running'])).toBe('deploying');
                expect(getAggregateStatus(['in_progress'])).toBe('deploying');
            });

            it('returns running if all statuses are positive', () => {
                expect(getAggregateStatus(['running', 'finished', 'healthy'])).toBe('running');
            });

            it('returns stopped for other combinations', () => {
                expect(getAggregateStatus(['stopped', 'stopped'])).toBe('stopped');
                expect(getAggregateStatus([])).toBe('stopped');
            });

            it('prioritizes error over loading', () => {
                expect(getAggregateStatus(['deploying', 'failed'])).toBe('error');
            });
        });
    });

    describe('getStatusBadgeConfig', () => {
        it('returns config compatible with StatusBadge component', () => {
            const config = getStatusBadgeConfig('running');
            expect(config).toHaveProperty('label');
            expect(config).toHaveProperty('variant');
            expect(config).toHaveProperty('dotClass');
            expect(config.label).toBe('Running');
            expect(config.variant).toBe('success');
            expect(config.dotClass).toBe('status-online');
        });

        it('returns status-stopped for statuses without dot class', () => {
            const config = getStatusBadgeConfig('enabled');
            expect(config.dotClass).toBe('status-stopped');
        });
    });
});
