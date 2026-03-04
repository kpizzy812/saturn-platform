import { describe, it, expect, vi } from 'vitest';
import { render, screen, act, fireEvent } from '@testing-library/react';
import {
    DataTable,
    DataTableFilters,
    DataTableSearch,
    DataTableFilter,
    DataTableContent,
    DataTableListContent,
    DataTablePagination,
} from '../DataTable';

interface TestItem {
    id: number;
    name: string;
    email: string;
}

const testItems: TestItem[] = [
    { id: 1, name: 'Alice', email: 'alice@test.com' },
    { id: 2, name: 'Bob', email: 'bob@test.com' },
    { id: 3, name: 'Charlie', email: 'charlie@test.com' },
];

describe('DataTable', () => {
    describe('DataTable Root', () => {
        it('renders children within context', () => {
            render(
                <DataTable data={testItems}>
                    <div data-testid="child">Child content</div>
                </DataTable>,
            );
            expect(screen.getByTestId('child')).toBeInTheDocument();
        });

        it('works without columns prop', () => {
            render(
                <DataTable data={testItems}>
                    <div data-testid="child">No columns needed</div>
                </DataTable>,
            );
            expect(screen.getByTestId('child')).toBeInTheDocument();
        });
    });

    describe('DataTableListContent', () => {
        it('renders items via renderItem callback', () => {
            render(
                <DataTable data={testItems}>
                    <DataTableListContent<TestItem>
                        renderItem={(item) => (
                            <div data-testid={`item-${item.id}`}>{item.name}</div>
                        )}
                    />
                </DataTable>,
            );

            expect(screen.getByTestId('item-1')).toHaveTextContent('Alice');
            expect(screen.getByTestId('item-2')).toHaveTextContent('Bob');
            expect(screen.getByTestId('item-3')).toHaveTextContent('Charlie');
        });

        it('shows empty state when data is empty', () => {
            render(
                <DataTable data={[]}>
                    <DataTableListContent<TestItem>
                        renderItem={(item) => <div>{item.name}</div>}
                        emptyTitle="No users found"
                        emptyDescription="Try a different search"
                    />
                </DataTable>,
            );

            expect(screen.getByText('No users found')).toBeInTheDocument();
            expect(screen.getByText('Try a different search')).toBeInTheDocument();
        });

        it('shows default empty state text', () => {
            render(
                <DataTable data={[]}>
                    <DataTableListContent<TestItem>
                        renderItem={(item) => <div>{item.name}</div>}
                    />
                </DataTable>,
            );

            expect(screen.getByText('No results found')).toBeInTheDocument();
            expect(screen.getByText('Try adjusting your search or filters.')).toBeInTheDocument();
        });

        it('shows empty icon when provided', () => {
            render(
                <DataTable data={[]}>
                    <DataTableListContent<TestItem>
                        renderItem={(item) => <div>{item.name}</div>}
                        emptyIcon={<span data-testid="empty-icon">icon</span>}
                    />
                </DataTable>,
            );

            expect(screen.getByTestId('empty-icon')).toBeInTheDocument();
        });

        it('shows skeleton loading state', () => {
            render(
                <DataTable data={[]} loading={true}>
                    <DataTableListContent<TestItem>
                        renderItem={(item) => <div>{item.name}</div>}
                        skeletonRows={3}
                    />
                </DataTable>,
            );

            const skeletons = document.querySelectorAll('.animate-pulse');
            expect(skeletons.length).toBeGreaterThan(0);
        });

        it('uses keyExtractor for React keys', () => {
            const renderItem = vi.fn((item: TestItem) => (
                <div data-testid={`item-${item.id}`}>{item.name}</div>
            ));

            render(
                <DataTable data={testItems}>
                    <DataTableListContent<TestItem>
                        renderItem={renderItem}
                        keyExtractor={(item) => item.id}
                    />
                </DataTable>,
            );

            expect(renderItem).toHaveBeenCalledTimes(3);
            expect(screen.getByTestId('item-1')).toBeInTheDocument();
        });

        it('passes index to renderItem', () => {
            const renderItem = vi.fn((item: TestItem, index: number) => (
                <div data-testid={`item-${index}`}>{item.name}</div>
            ));

            render(
                <DataTable data={testItems}>
                    <DataTableListContent<TestItem>
                        renderItem={renderItem}
                        keyExtractor={(item) => item.id}
                    />
                </DataTable>,
            );

            expect(renderItem).toHaveBeenCalledWith(testItems[0], 0);
            expect(renderItem).toHaveBeenCalledWith(testItems[1], 1);
            expect(renderItem).toHaveBeenCalledWith(testItems[2], 2);
        });
    });

    describe('DataTableSearch', () => {
        it('renders with placeholder', () => {
            render(
                <DataTableSearch
                    value=""
                    onChange={() => {}}
                    placeholder="Search users..."
                />,
            );

            expect(screen.getByPlaceholderText('Search users...')).toBeInTheDocument();
        });

        it('debounces onChange calls', () => {
            vi.useFakeTimers();
            const onChange = vi.fn();

            render(
                <DataTableSearch
                    value=""
                    onChange={onChange}
                    debounceMs={300}
                />,
            );

            const input = screen.getByRole('textbox');
            fireEvent.change(input, { target: { value: 'test' } });

            // Should not have been called yet
            expect(onChange).not.toHaveBeenCalled();

            // Advance past debounce
            act(() => {
                vi.advanceTimersByTime(300);
            });

            expect(onChange).toHaveBeenCalledWith('test');
            vi.useRealTimers();
        });
    });

    describe('DataTableFilter', () => {
        it('renders options', () => {
            render(
                <DataTableFilter
                    value=""
                    onChange={() => {}}
                    options={[
                        { value: '', label: 'All' },
                        { value: 'active', label: 'Active' },
                        { value: 'inactive', label: 'Inactive' },
                    ]}
                />,
            );

            const select = screen.getByRole('combobox');
            expect(select).toBeInTheDocument();
        });

        it('calls onChange when selection changes', () => {
            const onChange = vi.fn();

            render(
                <DataTableFilter
                    value=""
                    onChange={onChange}
                    options={[
                        { value: '', label: 'All' },
                        { value: 'active', label: 'Active' },
                    ]}
                />,
            );

            const select = screen.getByRole('combobox');
            fireEvent.change(select, { target: { value: 'active' } });

            expect(onChange).toHaveBeenCalledWith('active');
        });
    });

    describe('DataTablePagination', () => {
        it('hides when totalPages <= 1', () => {
            const { container } = render(
                <DataTablePagination
                    currentPage={1}
                    totalPages={1}
                    onPageChange={() => {}}
                />,
            );

            expect(container.innerHTML).toBe('');
        });

        it('renders page info with totalItems and pageSize', () => {
            render(
                <DataTablePagination
                    currentPage={2}
                    totalPages={5}
                    onPageChange={() => {}}
                    totalItems={50}
                    pageSize={10}
                />,
            );

            expect(screen.getByText(/11–20 of 50/)).toBeInTheDocument();
        });

        it('renders page info without totalItems', () => {
            render(
                <DataTablePagination
                    currentPage={2}
                    totalPages={5}
                    onPageChange={() => {}}
                />,
            );

            expect(screen.getByText('Page 2 of 5')).toBeInTheDocument();
        });

        it('disables previous button on first page', () => {
            render(
                <DataTablePagination
                    currentPage={1}
                    totalPages={5}
                    onPageChange={() => {}}
                />,
            );

            const prevButton = screen.getByRole('button', { name: /previous/i });
            expect(prevButton).toBeDisabled();
        });

        it('disables next button on last page', () => {
            render(
                <DataTablePagination
                    currentPage={5}
                    totalPages={5}
                    onPageChange={() => {}}
                />,
            );

            const nextButton = screen.getByRole('button', { name: /next/i });
            expect(nextButton).toBeDisabled();
        });

        it('calls onPageChange when clicking next', () => {
            const onPageChange = vi.fn();

            render(
                <DataTablePagination
                    currentPage={2}
                    totalPages={5}
                    onPageChange={onPageChange}
                />,
            );

            const buttons = screen.getAllByRole('button');
            const nextButton = buttons[buttons.length - 1];
            fireEvent.click(nextButton);

            expect(onPageChange).toHaveBeenCalledWith(3);
        });

        it('calls onPageChange when clicking previous', () => {
            const onPageChange = vi.fn();

            render(
                <DataTablePagination
                    currentPage={3}
                    totalPages={5}
                    onPageChange={onPageChange}
                />,
            );

            const buttons = screen.getAllByRole('button');
            const prevButton = buttons[0];
            fireEvent.click(prevButton);

            expect(onPageChange).toHaveBeenCalledWith(2);
        });
    });

    describe('DataTableFilters', () => {
        it('renders children in flex layout', () => {
            render(
                <DataTableFilters>
                    <div data-testid="filter-1">Filter 1</div>
                    <div data-testid="filter-2">Filter 2</div>
                </DataTableFilters>,
            );

            expect(screen.getByTestId('filter-1')).toBeInTheDocument();
            expect(screen.getByTestId('filter-2')).toBeInTheDocument();
        });
    });

    describe('DataTableContent (table mode)', () => {
        const columns = [
            {
                key: 'name',
                header: 'Name',
                render: (item: TestItem) => item.name,
            },
            {
                key: 'email',
                header: 'Email',
                render: (item: TestItem) => item.email,
            },
        ];

        it('renders table with data', () => {
            render(
                <DataTable data={testItems} columns={columns}>
                    <DataTableContent />
                </DataTable>,
            );

            expect(screen.getByText('Name')).toBeInTheDocument();
            expect(screen.getByText('Email')).toBeInTheDocument();
            expect(screen.getByText('Alice')).toBeInTheDocument();
            expect(screen.getByText('bob@test.com')).toBeInTheDocument();
        });

        it('shows empty state when data is empty', () => {
            render(
                <DataTable data={[]} columns={columns}>
                    <DataTableContent
                        emptyTitle="No data"
                        emptyDescription="Nothing to show"
                    />
                </DataTable>,
            );

            expect(screen.getByText('No data')).toBeInTheDocument();
            expect(screen.getByText('Nothing to show')).toBeInTheDocument();
        });
    });
});
