import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import { router } from '@inertiajs/react';

// Import after mock setup
import TagsIndex from '@/pages/Tags/Index';

const mockTags = [
    {
        id: 1,
        name: 'production',
        color: '#ef4444',
        applications_count: 5,
        services_count: 3,
        databases_count: 2,
        updated_at: '2024-02-10T10:00:00Z',
    },
    {
        id: 2,
        name: 'staging',
        color: '#f59e0b',
        applications_count: 3,
        services_count: 2,
        databases_count: 1,
        updated_at: '2024-02-11T15:00:00Z',
    },
    {
        id: 3,
        name: 'development',
        color: '#10b981',
        applications_count: 10,
        services_count: 5,
        databases_count: 0,
        updated_at: '2024-02-12T09:00:00Z',
    },
    {
        id: 4,
        name: 'frontend',
        color: '#6366f1',
        applications_count: 4,
        services_count: 0,
        databases_count: 0,
        updated_at: '2024-02-09T12:00:00Z',
    },
    {
        id: 5,
        name: 'backend',
        color: '#8b5cf6',
        applications_count: 2,
        services_count: 4,
        databases_count: 3,
        updated_at: '2024-02-08T14:00:00Z',
    },
];

describe('Tags Index Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Page Rendering', () => {
        it('renders the page title and description', () => {
            render(<TagsIndex tags={mockTags} />);
            const headings = screen.getAllByText('Tags');
            expect(headings.length).toBeGreaterThan(0);
            expect(
                screen.getByText('Organize and manage your resources with tags')
            ).toBeInTheDocument();
        });

        it('renders new tag button', () => {
            render(<TagsIndex tags={mockTags} />);
            expect(screen.getByText('New Tag')).toBeInTheDocument();
        });

        it('renders with empty tags array', () => {
            render(<TagsIndex tags={[]} />);
            const headings = screen.getAllByText('Tags');
            expect(headings.length).toBeGreaterThan(0);
        });
    });

    describe('Search Functionality', () => {
        it('renders search input', () => {
            render(<TagsIndex tags={mockTags} />);
            expect(screen.getByPlaceholderText('Search tags...')).toBeInTheDocument();
        });

        it('filters tags by name', () => {
            render(<TagsIndex tags={mockTags} />);
            const searchInput = screen.getByPlaceholderText('Search tags...');
            fireEvent.change(searchInput, { target: { value: 'production' } });

            expect(screen.getByText('production')).toBeInTheDocument();
            expect(screen.queryByText('staging')).not.toBeInTheDocument();
            expect(screen.queryByText('development')).not.toBeInTheDocument();
        });

        it('is case insensitive', () => {
            render(<TagsIndex tags={mockTags} />);
            const searchInput = screen.getByPlaceholderText('Search tags...');
            fireEvent.change(searchInput, { target: { value: 'PRODUCTION' } });

            expect(screen.getByText('production')).toBeInTheDocument();
        });

        it('shows no results message when search returns empty', () => {
            render(<TagsIndex tags={mockTags} />);
            const searchInput = screen.getByPlaceholderText('Search tags...');
            fireEvent.change(searchInput, { target: { value: 'nonexistent' } });

            expect(screen.getByText('No tags found')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your search query.')).toBeInTheDocument();
        });
    });

    describe('Tags Grid Display', () => {
        it('displays all tags in grid', () => {
            render(<TagsIndex tags={mockTags} />);
            expect(screen.getByText('production')).toBeInTheDocument();
            expect(screen.getByText('staging')).toBeInTheDocument();
            expect(screen.getByText('development')).toBeInTheDocument();
            expect(screen.getByText('frontend')).toBeInTheDocument();
            expect(screen.getByText('backend')).toBeInTheDocument();
        });

        it('displays resource counts', () => {
            render(<TagsIndex tags={mockTags} />);
            // production: 5 apps + 3 services + 2 databases = 10 resources
            expect(screen.getByText('10 resources')).toBeInTheDocument();
            // staging: 3 apps + 2 services + 1 database = 6 resources
            expect(screen.getByText('6 resources')).toBeInTheDocument();
        });

        it('displays singular "resource" for single resource', () => {
            const singleResourceTag = [
                {
                    id: 1,
                    name: 'test',
                    color: '#000000',
                    applications_count: 1,
                    services_count: 0,
                    databases_count: 0,
                    updated_at: '2024-02-12T00:00:00Z',
                },
            ];
            render(<TagsIndex tags={singleResourceTag} />);
            expect(screen.getByText('1 resource')).toBeInTheDocument();
        });

        it('shows resource breakdown', () => {
            render(<TagsIndex tags={mockTags} />);
            // Resource types only show if count > 0, so we check for multiple instances
            const applications = screen.getAllByText('Applications');
            expect(applications.length).toBeGreaterThan(0);
            const services = screen.getAllByText('Services');
            expect(services.length).toBeGreaterThan(0);
            const databases = screen.getAllByText('Databases');
            expect(databases.length).toBeGreaterThan(0);
        });

        it('displays updated date', () => {
            render(<TagsIndex tags={mockTags} />);
            expect(screen.getAllByText(/Updated \d+\/\d+\/\d+/).length).toBeGreaterThan(0);
        });

        it('renders tag icons with correct colors', () => {
            const { container } = render(<TagsIndex tags={mockTags} />);
            const coloredIcons = container.querySelectorAll('[style*="color"]');
            expect(coloredIcons.length).toBeGreaterThan(0);
        });
    });

    describe('Tag Card Actions', () => {
        it('has dropdown menu for each tag', () => {
            const { container } = render(<TagsIndex tags={mockTags} />);
            // Look for MoreVertical icon (dropdown triggers) - check for the actual SVG or button count
            // Each tag card should have a dropdown button, so we should have at least mockTags.length dropdown triggers
            const allButtons = container.querySelectorAll('button');
            // Filter buttons that are inside tag cards (exclude New Tag button and other UI buttons)
            const cardButtons = Array.from(allButtons).filter(btn => {
                // Check if button is inside a card and contains an SVG (likely the dropdown trigger)
                const card = btn.closest('a > div');
                return card && btn.querySelector('svg');
            });
            expect(cardButtons.length).toBeGreaterThanOrEqual(mockTags.length);
        });

        it('links to tag detail page', () => {
            render(<TagsIndex tags={mockTags} />);
            const tagLinks = screen.getAllByRole('link');
            const productionLink = tagLinks.find(link =>
                link.getAttribute('href')?.includes('/tags/production')
            );
            expect(productionLink).toBeInTheDocument();
        });
    });

    describe('Create Tag Modal', () => {
        it('opens create tag modal', () => {
            render(<TagsIndex tags={mockTags} />);
            const newTagButton = screen.getByText('New Tag');
            fireEvent.click(newTagButton);

            expect(screen.getByText('Create New Tag')).toBeInTheDocument();
        });

        it('renders tag name input', () => {
            render(<TagsIndex tags={mockTags} />);
            const newTagButton = screen.getByText('New Tag');
            fireEvent.click(newTagButton);

            expect(screen.getByText('Tag Name')).toBeInTheDocument();
            expect(
                screen.getByPlaceholderText('e.g. production, staging, frontend')
            ).toBeInTheDocument();
        });

        it('renders color picker', () => {
            render(<TagsIndex tags={mockTags} />);
            const newTagButton = screen.getByText('New Tag');
            fireEvent.click(newTagButton);

            expect(screen.getByText('Color')).toBeInTheDocument();
        });

        it('has color presets', () => {
            const { container } = render(<TagsIndex tags={mockTags} />);
            const newTagButton = screen.getByText('New Tag');
            fireEvent.click(newTagButton);

            // Should have 8 color preset buttons + 1 custom color input
            const colorButtons = container.querySelectorAll('button[type="button"]');
            const colorPresets = Array.from(colorButtons).filter(btn =>
                btn.style.backgroundColor
            );
            expect(colorPresets.length).toBeGreaterThan(0);
        });

        it('selects a color preset', () => {
            const { container } = render(<TagsIndex tags={mockTags} />);
            const newTagButton = screen.getByText('New Tag');
            fireEvent.click(newTagButton);

            const colorButtons = container.querySelectorAll('button[type="button"]');
            const firstColorPreset = Array.from(colorButtons).find(btn =>
                btn.style.backgroundColor
            );

            if (firstColorPreset) {
                fireEvent.click(firstColorPreset);
                // Color should be selected (border changes)
                expect(firstColorPreset.className).toContain('scale-110');
            }
        });

        it('submits the form', () => {
            render(<TagsIndex tags={mockTags} />);
            const newTagButton = screen.getByText('New Tag');
            fireEvent.click(newTagButton);

            const nameInput = screen.getByPlaceholderText('e.g. production, staging, frontend');
            fireEvent.change(nameInput, { target: { value: 'new-tag' } });

            const createButton = screen.getByRole('button', { name: /create tag/i });
            fireEvent.click(createButton);

            expect(router.post).toHaveBeenCalledWith(
                '/tags',
                expect.objectContaining({
                    name: 'new-tag',
                }),
                expect.any(Object)
            );
        });

        it('closes modal on cancel', () => {
            render(<TagsIndex tags={mockTags} />);
            const newTagButton = screen.getByText('New Tag');
            fireEvent.click(newTagButton);

            const cancelButton = screen.getByRole('button', { name: /cancel/i });
            fireEvent.click(cancelButton);

            expect(screen.queryByText('Create New Tag')).not.toBeInTheDocument();
        });

        it('closes modal on background click', () => {
            const { container } = render(<TagsIndex tags={mockTags} />);
            const newTagButton = screen.getByText('New Tag');
            fireEvent.click(newTagButton);

            const backdrop = container.querySelector('.fixed.inset-0');
            if (backdrop) {
                fireEvent.click(backdrop);
                expect(screen.queryByText('Create New Tag')).not.toBeInTheDocument();
            }
        });
    });

    describe('Empty State', () => {
        it('displays empty state when no tags', () => {
            render(<TagsIndex tags={[]} />);
            expect(screen.getByText('No tags yet')).toBeInTheDocument();
            expect(
                screen.getByText('Create tags to organize your applications, services, and databases.')
            ).toBeInTheDocument();
        });

        it('has create button in empty state', () => {
            render(<TagsIndex tags={[]} />);
            expect(screen.getByText('Create Tag')).toBeInTheDocument();
        });

        it('clicking create button in empty state opens modal', () => {
            render(<TagsIndex tags={[]} />);
            const createButton = screen.getByText('Create Tag');
            fireEvent.click(createButton);

            expect(screen.getByText('Create New Tag')).toBeInTheDocument();
        });
    });

    describe('Resource Breakdown', () => {
        it('only shows non-zero resource types', () => {
            const tagWithOnlyApps = [
                {
                    id: 1,
                    name: 'apps-only',
                    color: '#000000',
                    applications_count: 5,
                    services_count: 0,
                    databases_count: 0,
                    updated_at: '2024-02-12T00:00:00Z',
                },
            ];
            render(<TagsIndex tags={tagWithOnlyApps} />);

            expect(screen.getByText('Applications')).toBeInTheDocument();
            // Services and Databases should not be shown since count is 0
            expect(screen.queryByText('Services')).not.toBeInTheDocument();
        });

        it('displays all resource types when all have counts', () => {
            const fullTag = [mockTags[0]]; // production has all types
            render(<TagsIndex tags={fullTag} />);

            expect(screen.getByText('Applications')).toBeInTheDocument();
            expect(screen.getByText('Services')).toBeInTheDocument();
            expect(screen.getByText('Databases')).toBeInTheDocument();
        });
    });

    describe('Grid Layout', () => {
        it('renders tags in grid layout', () => {
            const { container } = render(<TagsIndex tags={mockTags} />);
            const grid = container.querySelector('.grid');
            expect(grid).toBeInTheDocument();
            expect(grid?.className).toContain('md:grid-cols-2');
            expect(grid?.className).toContain('lg:grid-cols-3');
            expect(grid?.className).toContain('xl:grid-cols-4');
        });
    });

    describe('Hover Effects', () => {
        it('applies hover styles to tag cards', () => {
            const { container } = render(<TagsIndex tags={mockTags} />);
            const cards = container.querySelectorAll('a > div');
            const firstCard = cards[0];
            expect(firstCard?.className).toContain('hover:border-primary/50');
            expect(firstCard?.className).toContain('hover:shadow-lg');
        });
    });
});
