import { useState, useEffect, useRef } from 'react';

export interface SearchResult {
    type: string;
    uuid: string;
    name: string;
    description: string | null;
    href: string;
    project_name?: string | null;
    environment_name?: string | null;
}

const DEBOUNCE_MS = 300;
const MIN_QUERY_LENGTH = 2;

export function useSearch(query: string) {
    const [results, setResults] = useState<SearchResult[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        // Cancel any in-flight request
        abortRef.current?.abort();

        if (query.length < MIN_QUERY_LENGTH) {
            setResults([]);
            setIsLoading(false);
            return;
        }

        setIsLoading(true);

        const timer = setTimeout(() => {
            const controller = new AbortController();
            abortRef.current = controller;

            fetch(`/web-api/search?q=${encodeURIComponent(query)}`, {
                signal: controller.signal,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((res) => {
                    if (!res.ok) throw new Error('Search failed');
                    return res.json();
                })
                .then((data: { results: SearchResult[] }) => {
                    setResults(data.results);
                    setIsLoading(false);
                })
                .catch((err) => {
                    if (err.name !== 'AbortError') {
                        setResults([]);
                        setIsLoading(false);
                    }
                });
        }, DEBOUNCE_MS);

        return () => {
            clearTimeout(timer);
        };
    }, [query]);

    return { results, isLoading };
}
