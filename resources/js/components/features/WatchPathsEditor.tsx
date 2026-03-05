import { useState, useCallback } from 'react';
import { Plus, X, Eye, Info } from 'lucide-react';
import { Badge } from '@/components/ui';

interface WatchPathsEditorProps {
    paths: string;
    onChange: (paths: string) => void;
    basePath?: string;
    disabled?: boolean;
}

const SUGGESTED_PATTERNS = [
    { pattern: 'src/**', label: 'Source files' },
    { pattern: 'lib/**', label: 'Library files' },
    { pattern: '*.json', label: 'JSON configs' },
    { pattern: 'Dockerfile*', label: 'Dockerfiles' },
    { pattern: '!tests/**', label: 'Exclude tests' },
    { pattern: '!docs/**', label: 'Exclude docs' },
    { pattern: '!*.md', label: 'Exclude markdown' },
];

export function WatchPathsEditor({ paths, onChange, basePath, disabled }: WatchPathsEditorProps) {
    const [newPath, setNewPath] = useState('');
    const [showSuggestions, setShowSuggestions] = useState(false);

    const pathList = paths
        .split('\n')
        .map(p => p.trim())
        .filter(Boolean);

    const addPath = useCallback((pattern: string) => {
        const trimmed = pattern.trim();
        if (!trimmed || pathList.includes(trimmed)) return;

        const updated = [...pathList, trimmed].join('\n');
        onChange(updated);
        setNewPath('');
    }, [pathList, onChange]);

    const removePath = useCallback((index: number) => {
        const updated = pathList.filter((_, i) => i !== index).join('\n');
        onChange(updated);
    }, [pathList, onChange]);

    const isNegation = (p: string) => p.startsWith('!');

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Eye className="h-4 w-4 text-muted-foreground" />
                    <h4 className="text-sm font-medium">Watch Paths</h4>
                </div>
                {pathList.length === 0 && (
                    <span className="text-xs text-muted-foreground">
                        Rebuilds on any file change
                    </span>
                )}
            </div>

            {basePath && basePath !== '/' && (
                <p className="text-xs text-muted-foreground">
                    Relative to base directory: <code className="px-1 py-0.5 bg-muted rounded text-xs">{basePath}</code>
                </p>
            )}

            {/* Current paths */}
            {pathList.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {pathList.map((path, index) => (
                        <Badge
                            key={index}
                            variant={isNegation(path) ? 'destructive' : 'secondary'}
                            className="gap-1 px-2 py-1 text-xs font-mono"
                        >
                            {path}
                            {!disabled && (
                                <button
                                    type="button"
                                    onClick={() => removePath(index)}
                                    className="ml-1 hover:text-foreground"
                                >
                                    <X className="h-3 w-3" />
                                </button>
                            )}
                        </Badge>
                    ))}
                </div>
            )}

            {/* Add new path */}
            {!disabled && (
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={newPath}
                        onChange={e => setNewPath(e.target.value)}
                        onKeyDown={e => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                addPath(newPath);
                            }
                        }}
                        placeholder="e.g. src/** or !tests/**"
                        className="flex-1 h-8 px-2 text-sm border rounded bg-background text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                    />
                    <button
                        type="button"
                        onClick={() => addPath(newPath)}
                        disabled={!newPath.trim()}
                        className="h-8 px-2 text-sm border rounded hover:bg-accent disabled:opacity-50"
                    >
                        <Plus className="h-4 w-4" />
                    </button>
                </div>
            )}

            {/* Quick suggestions */}
            {!disabled && (
                <div>
                    <button
                        type="button"
                        onClick={() => setShowSuggestions(!showSuggestions)}
                        className="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1"
                    >
                        <Info className="h-3 w-3" />
                        {showSuggestions ? 'Hide suggestions' : 'Show common patterns'}
                    </button>

                    {showSuggestions && (
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {SUGGESTED_PATTERNS.filter(s => !pathList.includes(s.pattern)).map(suggestion => (
                                <button
                                    key={suggestion.pattern}
                                    type="button"
                                    onClick={() => addPath(suggestion.pattern)}
                                    className="text-xs px-2 py-1 border border-dashed rounded hover:bg-accent"
                                >
                                    <span className="font-mono">{suggestion.pattern}</span>
                                    <span className="ml-1 text-muted-foreground">({suggestion.label})</span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {/* Info */}
            <p className="text-xs text-muted-foreground">
                Glob patterns supported. Prefix with <code className="px-1 py-0.5 bg-muted rounded">!</code> to exclude.
                Leave empty to rebuild on every push.
            </p>
        </div>
    );
}
