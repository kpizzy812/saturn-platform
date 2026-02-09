import * as React from 'react';
import { Tab, TabGroup, TabList, TabPanel, TabPanels } from '@headlessui/react';
import { cn } from '@/lib/utils';

// Legacy API - tabs as an array
interface TabItem {
    label: string;
    content: React.ReactNode;
    disabled?: boolean;
}

interface LegacyTabsProps {
    tabs: TabItem[];
    defaultIndex?: number;
    onChange?: (index: number) => void;
}

export function Tabs({ tabs, defaultIndex = 0, onChange }: LegacyTabsProps) {
    const [selectedIndex, setSelectedIndex] = React.useState(defaultIndex);
    const tabRefs = React.useRef<(HTMLButtonElement | null)[]>([]);
    const [indicatorStyle, setIndicatorStyle] = React.useState<{ left: number; width: number }>({ left: 0, width: 0 });

    const handleChange = (index: number) => {
        setSelectedIndex(index);
        onChange?.(index);
    };

    React.useEffect(() => {
        const el = tabRefs.current[selectedIndex];
        if (el) {
            setIndicatorStyle({ left: el.offsetLeft, width: el.offsetWidth });
        }
    }, [selectedIndex]);

    return (
        <TabGroup defaultIndex={defaultIndex} onChange={handleChange}>
            <TabList className="relative flex gap-1 border-b border-border">
                {tabs.map((tab, index) => (
                    <Tab
                        key={index}
                        ref={(el: HTMLButtonElement | null) => { tabRefs.current[index] = el; }}
                        disabled={tab.disabled}
                        className={({ selected }) =>
                            cn(
                                'relative px-4 py-2 text-sm font-medium outline-none transition-colors',
                                '-mb-px',
                                selected
                                    ? 'text-foreground'
                                    : 'text-foreground-muted hover:text-foreground',
                                tab.disabled && 'cursor-not-allowed opacity-50'
                            )
                        }
                    >
                        {tab.label}
                    </Tab>
                ))}
                {/* Animated indicator */}
                <div
                    className="absolute bottom-0 h-0.5 bg-primary transition-all duration-200 ease-in-out"
                    style={{ left: indicatorStyle.left, width: indicatorStyle.width }}
                />
            </TabList>
            <TabPanels className="mt-4">
                {tabs.map((tab, index) => (
                    <TabPanel key={index} className="outline-none">
                        {tab.content}
                    </TabPanel>
                ))}
            </TabPanels>
        </TabGroup>
    );
}

// Composable API - individual components
interface TabsRootProps {
    children: React.ReactNode;
    defaultIndex?: number;
    onChange?: (index: number) => void;
    className?: string;
}

export function TabsRoot({ children, defaultIndex = 0, onChange, className }: TabsRootProps) {
    return (
        <TabGroup defaultIndex={defaultIndex} onChange={onChange} className={className}>
            {children}
        </TabGroup>
    );
}

interface TabsListProps {
    children: React.ReactNode;
    className?: string;
}

export function TabsList({ children, className }: TabsListProps) {
    return (
        <TabList className={cn('relative flex gap-1 border-b border-border', className)}>
            {children}
        </TabList>
    );
}

interface TabsTriggerProps {
    children: React.ReactNode;
    disabled?: boolean;
    className?: string;
}

export function TabsTrigger({ children, disabled, className }: TabsTriggerProps) {
    return (
        <Tab
            disabled={disabled}
            className={({ selected }) =>
                cn(
                    'relative px-4 py-2 text-sm font-medium outline-none transition-colors',
                    'border-b-2 -mb-px',
                    selected
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-foreground-muted hover:text-foreground',
                    disabled && 'cursor-not-allowed opacity-50',
                    className
                )
            }
        >
            {children}
        </Tab>
    );
}

interface TabsContentProps {
    children: React.ReactNode;
    className?: string;
}

export function TabsContent({ children, className }: TabsContentProps) {
    return <TabPanel className={cn('outline-none', className)}>{children}</TabPanel>;
}

// Also export TabsPanels for wrapping multiple TabsContent components
interface TabsPanelsProps {
    children: React.ReactNode;
    className?: string;
}

export function TabsPanels({ children, className }: TabsPanelsProps) {
    return <TabPanels className={cn('mt-4', className)}>{children}</TabPanels>;
}
