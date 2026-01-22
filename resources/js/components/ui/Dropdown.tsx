import * as React from 'react';
import { Menu, MenuButton, MenuItem, MenuItems, Transition } from '@headlessui/react';
import { cn } from '@/lib/utils';

// Context for compound components
interface DropdownContextValue {
    isOpen: boolean;
}

const DropdownContext = React.createContext<DropdownContextValue>({ isOpen: false });

// Main Dropdown component
interface DropdownProps {
    children: React.ReactNode;
}

export function Dropdown({ children }: DropdownProps) {
    return (
        <Menu as="div" className="relative inline-block text-left">
            {({ open }) => (
                <DropdownContext.Provider value={{ isOpen: open }}>
                    {children}
                </DropdownContext.Provider>
            )}
        </Menu>
    );
}

// Hook to access dropdown context
export function useDropdown() {
    return React.useContext(DropdownContext);
}

// Dropdown Trigger
interface DropdownTriggerProps {
    children: React.ReactNode;
}

export function DropdownTrigger({ children }: DropdownTriggerProps) {
    return <MenuButton as={React.Fragment}>{children}</MenuButton>;
}

// Dropdown Content
interface DropdownContentProps {
    children: React.ReactNode;
    align?: 'left' | 'right' | 'center';
    sideOffset?: number;
    className?: string;
    width?: 'auto' | 'sm' | 'md' | 'lg' | 'xl';
}

const widthClasses = {
    auto: 'min-w-[12rem]',
    sm: 'w-48',
    md: 'w-64',
    lg: 'w-80',
    xl: 'w-96',
};

export function DropdownContent({
    children,
    align = 'right',
    sideOffset = 8,
    width = 'md',
    className,
}: DropdownContentProps) {
    return (
        <Transition
            enter="transition ease-out duration-200"
            enterFrom="transform opacity-0 scale-95 -translate-y-2"
            enterTo="transform opacity-100 scale-100 translate-y-0"
            leave="transition ease-in duration-150"
            leaveFrom="transform opacity-100 scale-100 translate-y-0"
            leaveTo="transform opacity-0 scale-95 -translate-y-2"
        >
            <MenuItems
                style={{ marginTop: sideOffset }}
                className={cn(
                    // Base styles
                    'absolute z-50 origin-top rounded-xl p-1.5',
                    // Width
                    widthClasses[width],
                    // Glassmorphism background
                    'bg-background-tertiary/95 backdrop-blur-xl',
                    // Border with subtle glow
                    'border border-white/[0.08]',
                    'ring-1 ring-white/[0.05]',
                    // Shadow for depth
                    'shadow-2xl shadow-black/50',
                    // Focus
                    'focus:outline-none',
                    // Alignment
                    align === 'right' && 'right-0',
                    align === 'left' && 'left-0',
                    align === 'center' && 'left-1/2 -translate-x-1/2',
                    className
                )}
            >
                {children}
            </MenuItems>
        </Transition>
    );
}

// Dropdown Label (for grouped items)
interface DropdownLabelProps {
    children: React.ReactNode;
    className?: string;
}

export function DropdownLabel({ children, className }: DropdownLabelProps) {
    return (
        <div
            className={cn(
                'px-3 py-2 text-xs font-semibold uppercase tracking-wider text-foreground-subtle',
                className
            )}
        >
            {children}
        </div>
    );
}

// Dropdown Item
interface DropdownItemProps {
    children: React.ReactNode;
    onClick?: (e: React.MouseEvent) => void;
    onSelect?: () => void;
    danger?: boolean;
    disabled?: boolean;
    icon?: React.ReactNode;
    shortcut?: string;
    description?: string;
}

export function DropdownItem({
    children,
    onClick,
    onSelect,
    danger,
    disabled,
    icon,
    shortcut,
    description,
}: DropdownItemProps) {
    const handleClick = (e: React.MouseEvent) => {
        onClick?.(e);
        onSelect?.();
    };

    return (
        <MenuItem disabled={disabled}>
            {({ active, disabled: isDisabled }) => (
                <button
                    onClick={handleClick}
                    className={cn(
                        // Base styles
                        'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left',
                        'text-sm font-medium whitespace-nowrap',
                        // Transitions
                        'transition-all duration-150',
                        // Active/hover states
                        active && !isDisabled && [
                            'bg-white/[0.08]',
                            danger ? 'text-danger' : 'text-foreground',
                        ],
                        // Default state
                        !active && [
                            danger ? 'text-danger/80' : 'text-foreground-muted',
                        ],
                        // Disabled
                        isDisabled && 'cursor-not-allowed opacity-40',
                    )}
                    disabled={isDisabled}
                >
                    {icon && (
                        <span className={cn(
                            'flex-shrink-0',
                            active ? 'opacity-100' : 'opacity-60'
                        )}>
                            {icon}
                        </span>
                    )}
                    <div className="flex-1 min-w-0 flex items-center gap-2">
                        <span className="truncate">{children}</span>
                        {description && (
                            <div className="text-xs text-foreground-subtle truncate mt-0.5">
                                {description}
                            </div>
                        )}
                    </div>
                    {shortcut && (
                        <kbd className={cn(
                            'ml-auto flex-shrink-0 text-xs font-mono',
                            'px-1.5 py-0.5 rounded',
                            'bg-white/[0.05] text-foreground-subtle',
                            'border border-white/[0.06]'
                        )}>
                            {shortcut}
                        </kbd>
                    )}
                </button>
            )}
        </MenuItem>
    );
}

// Dropdown Divider
interface DropdownDividerProps {
    className?: string;
}

export function DropdownDivider({ className }: DropdownDividerProps) {
    return (
        <div
            className={cn(
                'my-1.5 h-px bg-white/[0.06]',
                className
            )}
        />
    );
}

// Dropdown Group (for visually grouping items)
interface DropdownGroupProps {
    children: React.ReactNode;
    className?: string;
}

export function DropdownGroup({ children, className }: DropdownGroupProps) {
    return (
        <div className={cn('py-1', className)}>
            {children}
        </div>
    );
}
