import * as React from 'react';
import { cn } from '@/lib/utils';
import { GitBranch, Loader2, ChevronDown, Check, AlertCircle } from 'lucide-react';

interface Branch {
    name: string;
    is_default: boolean;
}

interface BranchSelectorProps {
    value: string;
    onChange: (value: string) => void;
    branches: Branch[];
    isLoading: boolean;
    error: string | null;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
}

export function BranchSelector({
    value,
    onChange,
    branches,
    isLoading,
    error,
    placeholder = 'main',
    disabled = false,
    className,
}: BranchSelectorProps) {
    const [isOpen, setIsOpen] = React.useState(false);
    const [inputValue, setInputValue] = React.useState(value);
    const containerRef = React.useRef<HTMLDivElement>(null);
    const inputRef = React.useRef<HTMLInputElement>(null);

    // Sync input value with external value
    React.useEffect(() => {
        setInputValue(value);
    }, [value]);

    // Close dropdown on outside click
    React.useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Filter branches based on input
    const filteredBranches = React.useMemo(() => {
        if (!inputValue.trim()) return branches;
        const lowerInput = inputValue.toLowerCase();
        return branches.filter(branch =>
            branch.name.toLowerCase().includes(lowerInput)
        );
    }, [branches, inputValue]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newValue = e.target.value;
        setInputValue(newValue);
        onChange(newValue);
        if (!isOpen && branches.length > 0) {
            setIsOpen(true);
        }
    };

    const handleSelectBranch = (branchName: string) => {
        setInputValue(branchName);
        onChange(branchName);
        setIsOpen(false);
        inputRef.current?.focus();
    };

    const handleInputFocus = () => {
        if (branches.length > 0) {
            setIsOpen(true);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Escape') {
            setIsOpen(false);
        } else if (e.key === 'ArrowDown' && !isOpen && branches.length > 0) {
            setIsOpen(true);
        }
    };

    const hasBranches = branches.length > 0;
    const showDropdown = isOpen && hasBranches && !disabled;

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <div className="relative">
                {/* Branch icon */}
                <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    {isLoading ? (
                        <Loader2 className="h-4 w-4 text-foreground-subtle animate-spin" />
                    ) : (
                        <GitBranch className="h-4 w-4 text-foreground-subtle" />
                    )}
                </div>

                {/* Input */}
                <input
                    ref={inputRef}
                    type="text"
                    value={inputValue}
                    onChange={handleInputChange}
                    onFocus={handleInputFocus}
                    onKeyDown={handleKeyDown}
                    placeholder={placeholder}
                    disabled={disabled}
                    className={cn(
                        'flex h-10 w-full rounded-lg px-3 py-2 text-sm pl-10',
                        'bg-background text-foreground',
                        'placeholder:text-foreground-subtle',
                        'border transition-all duration-200',
                        error
                            ? 'border-danger focus:border-danger focus:ring-danger/30'
                            : 'border-white/[0.08] focus:border-primary/50',
                        'focus:outline-none focus:ring-2 focus:ring-primary/20',
                        'focus:bg-background-secondary',
                        'hover:border-white/[0.12]',
                        'disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-background-tertiary',
                        hasBranches && 'pr-10'
                    )}
                />

                {/* Dropdown indicator */}
                {hasBranches && !disabled && (
                    <button
                        type="button"
                        onClick={() => setIsOpen(!isOpen)}
                        className="absolute inset-y-0 right-0 flex items-center pr-3 text-foreground-subtle hover:text-foreground"
                    >
                        <ChevronDown className={cn(
                            'h-4 w-4 transition-transform duration-200',
                            isOpen && 'rotate-180'
                        )} />
                    </button>
                )}
            </div>

            {/* Error message */}
            {error && (
                <p className="mt-1.5 text-sm text-amber-500 flex items-center gap-1.5">
                    <AlertCircle className="h-3.5 w-3.5" />
                    {error}
                    <span className="text-foreground-subtle">- you can type branch name manually</span>
                </p>
            )}

            {/* Hint when loading */}
            {isLoading && (
                <p className="mt-1.5 text-sm text-foreground-subtle">
                    Loading branches...
                </p>
            )}

            {/* Dropdown list */}
            {showDropdown && (
                <div className="absolute z-50 mt-1 w-full rounded-lg border border-white/[0.08] bg-background-secondary shadow-lg">
                    <div className="max-h-60 overflow-auto py-1">
                        {filteredBranches.length > 0 ? (
                            filteredBranches.map((branch) => (
                                <button
                                    key={branch.name}
                                    type="button"
                                    onClick={() => handleSelectBranch(branch.name)}
                                    className={cn(
                                        'flex w-full items-center gap-2 px-3 py-2 text-sm text-left',
                                        'hover:bg-white/[0.05] transition-colors',
                                        value === branch.name && 'bg-primary/10 text-primary'
                                    )}
                                >
                                    <GitBranch className="h-4 w-4 text-foreground-subtle shrink-0" />
                                    <span className="flex-1 truncate">{branch.name}</span>
                                    {branch.is_default && (
                                        <span className="text-xs px-1.5 py-0.5 rounded bg-primary/20 text-primary shrink-0">
                                            default
                                        </span>
                                    )}
                                    {value === branch.name && (
                                        <Check className="h-4 w-4 text-primary shrink-0" />
                                    )}
                                </button>
                            ))
                        ) : (
                            <div className="px-3 py-2 text-sm text-foreground-subtle">
                                No branches match "{inputValue}"
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Success hint when branches loaded */}
            {!isLoading && !error && hasBranches && !isOpen && (
                <p className="mt-1.5 text-sm text-foreground-subtle">
                    {branches.length} branch{branches.length !== 1 ? 'es' : ''} available
                </p>
            )}
        </div>
    );
}
