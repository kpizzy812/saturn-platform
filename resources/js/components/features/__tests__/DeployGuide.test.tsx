import { describe, it, expect } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { DeployGuide } from '../DeployGuide';

describe('DeployGuide', () => {
    describe('compact variant', () => {
        it('renders toggle link', () => {
            render(<DeployGuide variant="compact" />);
            expect(screen.getByText(/What can Saturn detect/)).toBeDefined();
        });

        it('is collapsed by default', () => {
            render(<DeployGuide variant="compact" />);
            expect(screen.queryByText('Deploy Guide')).toBeNull();
        });

        it('expands on click', () => {
            render(<DeployGuide variant="compact" />);
            fireEvent.click(screen.getByText(/What can Saturn detect/));
            expect(screen.getByText('What gets auto-detected?')).toBeDefined();
            expect(screen.getByText('Supported frameworks')).toBeDefined();
            expect(screen.getByText('Database auto-detection')).toBeDefined();
            expect(screen.getByText('Monorepo support')).toBeDefined();
            expect(screen.getByText('Prepare your repo (checklist)')).toBeDefined();
            expect(screen.getByText('CLI smart deploy (.saturn.yml)')).toBeDefined();
        });

        it('collapses on second click', () => {
            render(<DeployGuide variant="compact" />);
            const toggle = screen.getByText(/What can Saturn detect/);
            fireEvent.click(toggle);
            expect(screen.getByText('What gets auto-detected?')).toBeDefined();

            fireEvent.click(screen.getByText(/Hide deploy guide/));
            expect(screen.queryByText('What gets auto-detected?')).toBeNull();
        });
    });

    describe('full variant', () => {
        it('renders expanded with header', () => {
            render(<DeployGuide variant="full" />);
            expect(screen.getByText('Deploy Guide')).toBeDefined();
            expect(screen.getByText('What gets auto-detected?')).toBeDefined();
        });

        it('opens default section when specified', () => {
            render(<DeployGuide variant="full" defaultOpen="repo-checklist" />);
            expect(screen.getByText('Dependency file at root (or per workspace)')).toBeDefined();
        });

        it('does not show other sections content by default', () => {
            render(<DeployGuide variant="full" defaultOpen="repo-checklist" />);
            // Frameworks section should be collapsed
            expect(screen.queryByText('Next.js')).toBeNull();
        });
    });

    describe('section toggling', () => {
        it('opens a section on click', () => {
            render(<DeployGuide variant="full" />);
            fireEvent.click(screen.getByText('Supported frameworks'));
            expect(screen.getByText('Next.js')).toBeDefined();
            expect(screen.getByText('Django')).toBeDefined();
            expect(screen.getByText('Laravel')).toBeDefined();
        });

        it('opens database section', () => {
            render(<DeployGuide variant="full" />);
            fireEvent.click(screen.getByText('Database auto-detection'));
            expect(screen.getByText('PostgreSQL')).toBeDefined();
            expect(screen.getByText('MongoDB')).toBeDefined();
            expect(screen.getByText('Redis')).toBeDefined();
        });

        it('opens monorepo section', () => {
            render(<DeployGuide variant="full" />);
            fireEvent.click(screen.getByText('Monorepo support'));
            expect(screen.getByText('Turborepo')).toBeDefined();
            expect(screen.getByText('Nx')).toBeDefined();
            expect(screen.getByText('pnpm workspaces')).toBeDefined();
        });

        it('opens CLI section with yaml example', () => {
            render(<DeployGuide variant="full" />);
            fireEvent.click(screen.getByText('CLI smart deploy (.saturn.yml)'));
            expect(screen.getAllByText(/saturn deploy smart/).length).toBeGreaterThanOrEqual(1);
            expect(screen.getByText(/base_branch: main/)).toBeDefined();
        });

        it('closes previous section when opening another', () => {
            render(<DeployGuide variant="full" />);
            fireEvent.click(screen.getByText('Supported frameworks'));
            expect(screen.getByText('Next.js')).toBeDefined();

            fireEvent.click(screen.getByText('Database auto-detection'));
            // Frameworks content should be gone
            expect(screen.queryByText('Next.js')).toBeNull();
            // Database content should be visible
            expect(screen.getByText('PostgreSQL')).toBeDefined();
        });

        it('closes section when clicked again', () => {
            render(<DeployGuide variant="full" />);
            fireEvent.click(screen.getByText('Supported frameworks'));
            expect(screen.getByText('Next.js')).toBeDefined();

            fireEvent.click(screen.getByText('Supported frameworks'));
            expect(screen.queryByText('Next.js')).toBeNull();
        });
    });

    describe('auto-detection section content', () => {
        it('lists all detection items', () => {
            render(<DeployGuide variant="full" defaultOpen="what-detected" />);
            expect(screen.getByText(/Framework & language/)).toBeDefined();
            expect(screen.getByText(/Databases/)).toBeDefined();
            expect(screen.getByText(/Monorepo structure/)).toBeDefined();
            expect(screen.getByText(/Dockerfile/)).toBeDefined();
            expect(screen.getByText(/docker-compose.yml/)).toBeDefined();
            expect(screen.getByText(/\.env\.example/)).toBeDefined();
            expect(screen.getByText(/CI\/CD config/)).toBeDefined();
            expect(screen.getByText(/Health checks/)).toBeDefined();
        });
    });

    describe('checklist section content', () => {
        it('shows all checklist items', () => {
            render(<DeployGuide variant="full" defaultOpen="repo-checklist" />);
            expect(screen.getByText('Dependency file at root (or per workspace)')).toBeDefined();
            expect(screen.getByText('Start command defined')).toBeDefined();
            expect(screen.getByText('.env.example for required variables')).toBeDefined();
            expect(screen.getByText('Dockerfile (optional, takes priority)')).toBeDefined();
            expect(screen.getByText('Health check endpoint (recommended)')).toBeDefined();
        });
    });

    describe('className prop', () => {
        it('applies custom className', () => {
            const { container } = render(<DeployGuide variant="full" className="mt-8" />);
            expect(container.firstElementChild?.classList.contains('mt-8')).toBe(true);
        });
    });
});
