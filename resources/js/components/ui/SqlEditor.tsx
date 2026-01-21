import { useState, useRef, useEffect, KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';

interface SqlEditorProps {
    value: string;
    onChange: (value: string) => void;
    onExecute?: () => void;
    placeholder?: string;
    rows?: number;
    readOnly?: boolean;
    className?: string;
}

export function SqlEditor({
    value,
    onChange,
    onExecute,
    placeholder = 'SELECT * FROM users LIMIT 10;',
    rows = 10,
    readOnly = false,
    className,
}: SqlEditorProps) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const [lineCount, setLineCount] = useState(1);

    useEffect(() => {
        if (value) {
            const lines = value.split('\n').length;
            setLineCount(lines);
        } else {
            setLineCount(1);
        }
    }, [value]);

    const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        // Execute on Cmd+Enter or Ctrl+Enter
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && onExecute) {
            e.preventDefault();
            onExecute();
        }

        // Handle tab key for indentation
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = e.currentTarget.selectionStart;
            const end = e.currentTarget.selectionEnd;
            const newValue = value.substring(0, start) + '  ' + value.substring(end);
            onChange(newValue);

            // Set cursor position after the inserted spaces
            setTimeout(() => {
                if (textareaRef.current) {
                    textareaRef.current.selectionStart = textareaRef.current.selectionEnd = start + 2;
                }
            }, 0);
        }
    };

    const lineNumbers = Array.from({ length: Math.max(lineCount, rows) }, (_, i) => i + 1);

    return (
        <div className={cn('relative overflow-hidden rounded-lg border border-border bg-[#0d1117]', className)}>
            <div className="flex">
                {/* Line numbers */}
                <div className="select-none bg-[#161b22] px-3 py-3 text-right">
                    {lineNumbers.map((num) => (
                        <div
                            key={num}
                            className="font-mono text-xs leading-6 text-[#6e7681]"
                            style={{ minWidth: '2ch' }}
                        >
                            {num}
                        </div>
                    ))}
                </div>

                {/* Editor */}
                <div className="flex-1">
                    <textarea
                        ref={textareaRef}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder={placeholder}
                        readOnly={readOnly}
                        rows={rows}
                        className={cn(
                            'w-full resize-none bg-transparent px-3 py-3 font-mono text-sm leading-6 text-[#c9d1d9] placeholder:text-[#6e7681] focus:outline-none',
                            readOnly && 'cursor-not-allowed opacity-70'
                        )}
                        spellCheck={false}
                        autoComplete="off"
                        autoCorrect="off"
                        autoCapitalize="off"
                    />
                </div>
            </div>

            {/* Shortcut hint */}
            {onExecute && !readOnly && (
                <div className="border-t border-border/50 bg-[#161b22] px-3 py-2">
                    <div className="flex items-center justify-between text-xs text-[#6e7681]">
                        <span>Press ⌘+Enter or Ctrl+Enter to execute</span>
                        <div className="flex gap-2">
                            <kbd className="rounded border border-[#30363d] bg-[#0d1117] px-1.5 py-0.5 font-mono">
                                Tab
                            </kbd>
                            <span>for indent</span>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// SQL Keywords for syntax highlighting (basic version)
export const SQL_KEYWORDS = [
    'SELECT',
    'FROM',
    'WHERE',
    'INSERT',
    'UPDATE',
    'DELETE',
    'CREATE',
    'DROP',
    'ALTER',
    'TABLE',
    'INDEX',
    'VIEW',
    'JOIN',
    'LEFT',
    'RIGHT',
    'INNER',
    'OUTER',
    'ON',
    'AS',
    'ORDER',
    'BY',
    'GROUP',
    'HAVING',
    'LIMIT',
    'OFFSET',
    'AND',
    'OR',
    'NOT',
    'NULL',
    'IS',
    'IN',
    'LIKE',
    'BETWEEN',
    'DISTINCT',
    'COUNT',
    'SUM',
    'AVG',
    'MIN',
    'MAX',
];
