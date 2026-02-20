import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import { PreflightCheckPanel } from '../PreflightCheckPanel';

describe('PreflightCheckPanel', () => {
    it('renders nothing when data is null', () => {
        const { container } = render(<PreflightCheckPanel data={null} />);
        expect(container.innerHTML).toBe('');
    });

    it('shows loading state', () => {
        render(<PreflightCheckPanel data={null} loading={true} />);
        expect(screen.getByText('Running pre-flight checks...')).toBeInTheDocument();
    });

    it('shows compatibility checks', () => {
        render(
            <PreflightCheckPanel
                data={{
                    mode: 'promote',
                    summary: {
                        action: 'update_existing',
                        resource_name: 'my-api',
                        resource_type: 'Application',
                    },
                }}
            />
        );

        expect(screen.getByText('Mode: promote')).toBeInTheDocument();
        expect(screen.getByText(/my-api/)).toBeInTheDocument();
        expect(screen.getByText(/Update existing resource/)).toBeInTheDocument();
    });

    it('shows attribute changes', () => {
        render(
            <PreflightCheckPanel
                data={{
                    mode: 'promote',
                    summary: { action: 'update_existing', resource_name: 'app', resource_type: 'Application' },
                    attribute_diff: {
                        ports_exposes: { from: '3000', to: '8080' },
                    },
                }}
            />
        );

        expect(screen.getByText('ports_exposes')).toBeInTheDocument();
        expect(screen.getByText('3000')).toBeInTheDocument();
        expect(screen.getByText('8080')).toBeInTheDocument();
    });

    it('shows env var diff badges', () => {
        render(
            <PreflightCheckPanel
                data={{
                    mode: 'promote',
                    summary: { action: 'update_existing', resource_name: 'app', resource_type: 'Application' },
                    env_var_diff: {
                        added: ['NEW_KEY'],
                        removed: ['OLD_KEY'],
                        changed: ['DB_HOST'],
                    },
                }}
            />
        );

        expect(screen.getByText('+1 new')).toBeInTheDocument();
        expect(screen.getByText('-1 removed')).toBeInTheDocument();
        expect(screen.getByText('~1 changed')).toBeInTheDocument();
    });

    it('shows rewire preview', () => {
        render(
            <PreflightCheckPanel
                data={{
                    mode: 'promote',
                    summary: { action: 'update_existing', resource_name: 'app', resource_type: 'Application' },
                    rewire_preview: [
                        { key: 'DATABASE_URL', current_value_masked: 'postgres://****@db', will_rewire: true },
                    ],
                }}
            />
        );

        expect(screen.getByText('DATABASE_URL')).toBeInTheDocument();
        expect(screen.getByText(/1 connection.*will be rewired/)).toBeInTheDocument();
    });

    it('shows "in sync" when no changes', () => {
        render(
            <PreflightCheckPanel
                data={{
                    mode: 'promote',
                    summary: { action: 'update_existing', resource_name: 'app', resource_type: 'Application' },
                }}
            />
        );

        expect(screen.getByText(/No differences detected/)).toBeInTheDocument();
    });
});
