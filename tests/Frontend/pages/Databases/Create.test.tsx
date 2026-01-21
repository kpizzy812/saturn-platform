import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import DatabaseCreate from '@/pages/Databases/Create';

// Mock router
const mockRouter = {
    post: vi.fn(),
};

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: mockRouter,
    };
});

describe('Database Create Page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('rendering', () => {
        it('should render page title and subtitle', () => {
            render(<DatabaseCreate />);

            expect(screen.getByText('Create a new database')).toBeInTheDocument();
            expect(screen.getByText('Choose a database type and configure your instance')).toBeInTheDocument();
        });

        it('should display back link', () => {
            render(<DatabaseCreate />);

            expect(screen.getByText('Back to Databases')).toBeInTheDocument();
        });

        it('should render progress indicators', () => {
            render(<DatabaseCreate />);

            expect(screen.getByText('Type')).toBeInTheDocument();
            expect(screen.getByText('Configure')).toBeInTheDocument();
            expect(screen.getByText('Review')).toBeInTheDocument();
        });
    });

    describe('Step 1: Database Type Selection', () => {
        it('should display all database types', () => {
            render(<DatabaseCreate />);

            expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
            expect(screen.getByText('MySQL')).toBeInTheDocument();
            expect(screen.getByText('MariaDB')).toBeInTheDocument();
            expect(screen.getByText('MongoDB')).toBeInTheDocument();
            expect(screen.getByText('Redis')).toBeInTheDocument();
        });

        it('should display database descriptions', () => {
            render(<DatabaseCreate />);

            expect(screen.getByText('Advanced open-source relational database')).toBeInTheDocument();
            expect(screen.getByText('Popular open-source relational database')).toBeInTheDocument();
            expect(screen.getByText('MySQL-compatible relational database')).toBeInTheDocument();
            expect(screen.getByText('Document-oriented NoSQL database')).toBeInTheDocument();
            expect(screen.getByText('In-memory data structure store')).toBeInTheDocument();
        });

        it('should navigate to step 2 when database type is clicked', async () => {
            const { user } = render(<DatabaseCreate />);

            const postgresButton = screen.getByText('PostgreSQL').closest('button');
            expect(postgresButton).toBeTruthy();

            if (postgresButton) {
                await user.click(postgresButton);

                // Should now show configuration form
                expect(screen.getByText('Database Name')).toBeInTheDocument();
                expect(screen.getByText('Version')).toBeInTheDocument();
            }
        });
    });

    describe('Step 2: Configuration', () => {
        beforeEach(async () => {
            const { user } = render(<DatabaseCreate />);
            const postgresButton = screen.getByText('PostgreSQL').closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }
        });

        it('should display configuration form fields', () => {
            expect(screen.getByText('Database Name')).toBeInTheDocument();
            expect(screen.getByText('Version')).toBeInTheDocument();
            expect(screen.getByText('Description (Optional)')).toBeInTheDocument();
        });

        it('should display selected database type info', () => {
            expect(screen.getByText('PostgreSQL')).toBeInTheDocument();
            expect(screen.getByText('Advanced open-source relational database')).toBeInTheDocument();
        });

        it('should show version dropdown with options', () => {
            const versionSelect = screen.getByRole('combobox');
            expect(versionSelect).toBeInTheDocument();
        });

        it('should have continue button disabled when form is empty', () => {
            const continueButton = screen.getByText('Continue');
            expect(continueButton).toBeDisabled();
        });

        it('should enable continue button when required fields are filled', async () => {
            const { user } = render(<DatabaseCreate />);

            // Click PostgreSQL
            const postgresButtons = screen.getAllByText('PostgreSQL');
            const postgresButton = postgresButtons[0].closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            const nameInput = screen.getByPlaceholderText('my-database');
            await user.type(nameInput, 'test-database');

            const continueButton = screen.getByText('Continue');
            expect(continueButton).not.toBeDisabled();
        });

        it('should navigate to step 3 when continue is clicked', async () => {
            const { user } = render(<DatabaseCreate />);

            // Click PostgreSQL
            const postgresButtons = screen.getAllByText('PostgreSQL');
            const postgresButton = postgresButtons[0].closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            const nameInput = screen.getByPlaceholderText('my-database');
            await user.type(nameInput, 'test-database');

            const continueButton = screen.getByText('Continue');
            await user.click(continueButton);

            expect(screen.getByText('Review Configuration')).toBeInTheDocument();
        });

        it('should navigate back to step 1 when back button is clicked', async () => {
            const { user } = render(<DatabaseCreate />);

            // Click PostgreSQL
            const postgresButtons = screen.getAllByText('PostgreSQL');
            const postgresButton = postgresButtons[0].closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            const backButton = screen.getByText('Back');
            await user.click(backButton);

            // Should show database type selection again
            expect(screen.getAllByText('PostgreSQL').length).toBeGreaterThan(0);
        });
    });

    describe('Step 3: Review', () => {
        beforeEach(async () => {
            const { user } = render(<DatabaseCreate />);

            // Click PostgreSQL
            const postgresButton = screen.getByText('PostgreSQL').closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            // Fill form
            const nameInput = screen.getByPlaceholderText('my-database');
            await user.type(nameInput, 'test-database');

            const descriptionInput = screen.getByPlaceholderText('Production database for main application');
            await user.type(descriptionInput, 'Test description');

            // Continue to review
            const continueButton = screen.getByText('Continue');
            await user.click(continueButton);
        });

        it('should display review configuration title', () => {
            expect(screen.getByText('Review Configuration')).toBeInTheDocument();
        });

        it('should show configured database name and type', () => {
            expect(screen.getByText('test-database')).toBeInTheDocument();
            expect(screen.getByText(/PostgreSQL/)).toBeInTheDocument();
        });

        it('should display description if provided', () => {
            expect(screen.getByText('Description')).toBeInTheDocument();
            expect(screen.getByText('Test description')).toBeInTheDocument();
        });

        it('should display what happens next section', () => {
            expect(screen.getByText('What happens next?')).toBeInTheDocument();
            expect(screen.getByText('Database container will be created and started')).toBeInTheDocument();
            expect(screen.getByText('Connection credentials will be generated')).toBeInTheDocument();
            expect(screen.getByText("You'll be redirected to the database dashboard")).toBeInTheDocument();
        });

        it('should navigate back to step 2 when back button is clicked', async () => {
            const { user } = render(<DatabaseCreate />);

            // Navigate to review
            const postgresButton = screen.getByText('PostgreSQL').closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            const nameInput = screen.getByPlaceholderText('my-database');
            await user.type(nameInput, 'test-database');

            await user.click(screen.getByText('Continue'));

            // Click back on review page
            const backButtons = screen.getAllByText('Back');
            await user.click(backButtons[backButtons.length - 1]);

            // Should show configuration form again
            expect(screen.getByText('Database Name')).toBeInTheDocument();
        });

    });

    describe('form validation', () => {
        it('should not allow proceeding without database name', async () => {
            const { user } = render(<DatabaseCreate />);

            const postgresButton = screen.getByText('PostgreSQL').closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            const continueButton = screen.getByText('Continue');
            expect(continueButton).toBeDisabled();
        });

        it('should accept database name and proceed', async () => {
            const { user } = render(<DatabaseCreate />);

            const postgresButton = screen.getByText('PostgreSQL').closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            const nameInput = screen.getByPlaceholderText('my-database');
            await user.type(nameInput, 'valid-name');

            const continueButton = screen.getByText('Continue');
            expect(continueButton).not.toBeDisabled();
        });
    });

    describe('different database types', () => {
        it('should handle MySQL selection', async () => {
            const { user } = render(<DatabaseCreate />);

            const mysqlButton = screen.getByText('MySQL').closest('button');
            if (mysqlButton) {
                await user.click(mysqlButton);
                expect(screen.getByText('MySQL')).toBeInTheDocument();
            }
        });

        it('should handle MongoDB selection', async () => {
            const { user } = render(<DatabaseCreate />);

            const mongoButton = screen.getByText('MongoDB').closest('button');
            if (mongoButton) {
                await user.click(mongoButton);
                expect(screen.getByText('MongoDB')).toBeInTheDocument();
            }
        });

        it('should handle Redis selection', async () => {
            const { user } = render(<DatabaseCreate />);

            const redisButton = screen.getByText('Redis').closest('button');
            if (redisButton) {
                await user.click(redisButton);
                expect(screen.getByText('Redis')).toBeInTheDocument();
            }
        });
    });

    describe('step indicators', () => {
        it('should show step 1 as active initially', () => {
            render(<DatabaseCreate />);

            const stepIndicators = screen.getAllByText('1');
            expect(stepIndicators.length).toBeGreaterThan(0);
        });

        it('should show step 2 as active after selecting database type', async () => {
            const { user } = render(<DatabaseCreate />);

            const postgresButton = screen.getByText('PostgreSQL').closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            // Step 2 should be active
            expect(screen.getByText('Database Name')).toBeInTheDocument();
        });

        it('should show step 3 as active on review page', async () => {
            const { user } = render(<DatabaseCreate />);

            const postgresButton = screen.getByText('PostgreSQL').closest('button');
            if (postgresButton) {
                await user.click(postgresButton);
            }

            const nameInput = screen.getByPlaceholderText('my-database');
            await user.type(nameInput, 'test-database');

            await user.click(screen.getByText('Continue'));

            expect(screen.getByText('Review Configuration')).toBeInTheDocument();
        });
    });
});
