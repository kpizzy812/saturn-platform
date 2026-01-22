import * as React from 'react';

interface SidebarContextType {
    isExpanded: boolean;
    toggleSidebar: () => void;
}

const SidebarContext = React.createContext<SidebarContextType | undefined>(undefined);

export function SidebarProvider({ children }: { children: React.ReactNode }) {
    const [isExpanded, setIsExpanded] = React.useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('sidebar-expanded') === 'true';
        }
        return false;
    });

    const toggleSidebar = React.useCallback(() => {
        setIsExpanded(prev => {
            const newValue = !prev;
            if (typeof window !== 'undefined') {
                localStorage.setItem('sidebar-expanded', String(newValue));
            }
            return newValue;
        });
    }, []);

    return (
        <SidebarContext.Provider value={{ isExpanded, toggleSidebar }}>
            {children}
        </SidebarContext.Provider>
    );
}

export function useSidebar() {
    const context = React.useContext(SidebarContext);
    if (context === undefined) {
        throw new Error('useSidebar must be used within a SidebarProvider');
    }
    return context;
}
