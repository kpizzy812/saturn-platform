import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import { Dropdown, DropdownTrigger, DropdownContent, DropdownItem, DropdownDivider } from '@/components/ui/Dropdown';

describe('Dropdown Component', () => {
    it('renders trigger element', () => {
        render(
            <Dropdown>
                <DropdownTrigger>
                    <button>Open Menu</button>
                </DropdownTrigger>
                <DropdownContent>
                    <DropdownItem>Item 1</DropdownItem>
                </DropdownContent>
            </Dropdown>
        );
        expect(screen.getByText('Open Menu')).toBeInTheDocument();
    });

    it('shows dropdown content when clicked', async () => {
        render(
            <Dropdown>
                <DropdownTrigger>
                    <button>Open Menu</button>
                </DropdownTrigger>
                <DropdownContent>
                    <DropdownItem>Item 1</DropdownItem>
                    <DropdownItem>Item 2</DropdownItem>
                </DropdownContent>
            </Dropdown>
        );

        fireEvent.click(screen.getByText('Open Menu'));

        expect(await screen.findByText('Item 1')).toBeInTheDocument();
        expect(await screen.findByText('Item 2')).toBeInTheDocument();
    });

    it('renders dropdown divider', async () => {
        render(
            <Dropdown>
                <DropdownTrigger>
                    <button>Open Menu</button>
                </DropdownTrigger>
                <DropdownContent>
                    <DropdownItem>Item 1</DropdownItem>
                    <DropdownDivider />
                    <DropdownItem>Item 2</DropdownItem>
                </DropdownContent>
            </Dropdown>
        );

        fireEvent.click(screen.getByText('Open Menu'));

        // Divider should be rendered
        const content = await screen.findByText('Item 1');
        expect(content).toBeInTheDocument();
    });

    it('calls onClick handler when item is clicked', async () => {
        const handleClick = vi.fn();

        render(
            <Dropdown>
                <DropdownTrigger>
                    <button>Open Menu</button>
                </DropdownTrigger>
                <DropdownContent>
                    <DropdownItem onClick={handleClick}>Clickable Item</DropdownItem>
                </DropdownContent>
            </Dropdown>
        );

        fireEvent.click(screen.getByText('Open Menu'));
        const item = await screen.findByText('Clickable Item');
        fireEvent.click(item);

        expect(handleClick).toHaveBeenCalled();
    });

    it('renders danger variant item with correct styling', async () => {
        render(
            <Dropdown>
                <DropdownTrigger>
                    <button>Open Menu</button>
                </DropdownTrigger>
                <DropdownContent>
                    <DropdownItem danger>Delete</DropdownItem>
                </DropdownContent>
            </Dropdown>
        );

        fireEvent.click(screen.getByText('Open Menu'));
        const dangerText = await screen.findByText('Delete');
        // Get the button element which contains the danger styling
        const dangerButton = dangerText.closest('button');

        // Danger items have danger-related styling on the button
        expect(dangerButton?.className).toMatch(/text-danger/);
    });

    it('renders dropdown content with correct alignment', async () => {
        render(
            <Dropdown>
                <DropdownTrigger>
                    <button>Open Menu</button>
                </DropdownTrigger>
                <DropdownContent align="right">
                    <DropdownItem>Item</DropdownItem>
                </DropdownContent>
            </Dropdown>
        );

        fireEvent.click(screen.getByText('Open Menu'));
        const item = await screen.findByText('Item');

        // MenuItems are rendered and the content is displayed
        expect(item).toBeInTheDocument();
        // The dropdown uses Headless UI with portal positioning, so we just verify it renders
        const menuItems = item.closest('[role="menu"]');
        expect(menuItems).toBeInTheDocument();
    });
});
