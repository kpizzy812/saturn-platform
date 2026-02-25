import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import { UptimeBar } from '../UptimeBar';

function makeDays(count: number, status: 'operational' | 'degraded' | 'outage' | 'no_data' = 'operational') {
    return Array.from({ length: count }, (_, i) => ({
        date: `2026-01-${String(i + 1).padStart(2, '0')}`,
        status,
        uptimePercent: status === 'no_data' ? null : 100,
    }));
}

describe('UptimeBar', () => {
    it('renders 90 bars', () => {
        const days = makeDays(90);
        render(<UptimeBar days={days} />);
        const bars = screen.getAllByTestId(/^uptime-bar-/);
        expect(bars).toHaveLength(90);
    });

    it('renders correct number of bars for fewer days', () => {
        const days = makeDays(30);
        render(<UptimeBar days={days} />);
        const bars = screen.getAllByTestId(/^uptime-bar-/);
        expect(bars).toHaveLength(30);
    });

    it('shows operational bars with emerald color', () => {
        const days = makeDays(5, 'operational');
        render(<UptimeBar days={days} />);
        const bar = screen.getByTestId('uptime-bar-0');
        expect(bar).toHaveAttribute('data-status', 'operational');
        expect(bar.className).toContain('bg-emerald-500');
    });

    it('shows outage bars with red color', () => {
        const days = makeDays(5, 'outage');
        render(<UptimeBar days={days} />);
        const bar = screen.getByTestId('uptime-bar-0');
        expect(bar).toHaveAttribute('data-status', 'outage');
        expect(bar.className).toContain('bg-red-500');
    });

    it('shows degraded bars with yellow color', () => {
        const days = makeDays(5, 'degraded');
        render(<UptimeBar days={days} />);
        const bar = screen.getByTestId('uptime-bar-0');
        expect(bar.className).toContain('bg-yellow-500');
    });

    it('shows no_data bars with gray color', () => {
        const days = makeDays(5, 'no_data');
        render(<UptimeBar days={days} />);
        const bar = screen.getByTestId('uptime-bar-0');
        expect(bar.className).toContain('bg-gray-700');
    });

    it('displays "90 days ago" and "Today" labels', () => {
        const days = makeDays(90);
        render(<UptimeBar days={days} />);
        expect(screen.getByText('90 days ago')).toBeInTheDocument();
        expect(screen.getByText('Today')).toBeInTheDocument();
    });

    it('shows tooltip on hover', () => {
        const days = makeDays(5);
        render(<UptimeBar days={days} />);
        const bar = screen.getByTestId('uptime-bar-0');
        fireEvent.mouseEnter(bar);
        expect(screen.getByTestId('uptime-tooltip')).toBeInTheDocument();
        expect(screen.getByText('100% uptime')).toBeInTheDocument();
    });

    it('hides tooltip on mouse leave', () => {
        const days = makeDays(5);
        render(<UptimeBar days={days} />);
        const bar = screen.getByTestId('uptime-bar-0');
        fireEvent.mouseEnter(bar);
        expect(screen.getByTestId('uptime-tooltip')).toBeInTheDocument();
        fireEvent.mouseLeave(bar);
        expect(screen.queryByTestId('uptime-tooltip')).not.toBeInTheDocument();
    });

    it('shows "No data" for no_data days in tooltip', () => {
        const days = makeDays(5, 'no_data');
        render(<UptimeBar days={days} />);
        const bar = screen.getByTestId('uptime-bar-0');
        fireEvent.mouseEnter(bar);
        expect(screen.getByText('No data')).toBeInTheDocument();
    });
});
