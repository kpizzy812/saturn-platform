import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';

export default tseslint.config(
    js.configs.recommended,
    ...tseslint.configs.recommended,
    {
        files: ['resources/js/**/*.{js,ts,tsx}'],
        plugins: {
            react,
            'react-hooks': reactHooks,
        },
        languageOptions: {
            parserOptions: {
                ecmaFeatures: {
                    jsx: true,
                },
            },
            globals: {
                // Browser globals
                window: 'readonly',
                document: 'readonly',
                navigator: 'readonly',
                console: 'readonly',
                setTimeout: 'readonly',
                clearTimeout: 'readonly',
                setInterval: 'readonly',
                clearInterval: 'readonly',
                fetch: 'readonly',
                WebSocket: 'readonly',
                CustomEvent: 'readonly',
                EventSource: 'readonly',
                localStorage: 'readonly',
                sessionStorage: 'readonly',
                URL: 'readonly',
                URLSearchParams: 'readonly',
                FormData: 'readonly',
                Blob: 'readonly',
                File: 'readonly',
                FileReader: 'readonly',
                AbortController: 'readonly',
                Request: 'readonly',
                Response: 'readonly',
                Headers: 'readonly',
                MutationObserver: 'readonly',
                ResizeObserver: 'readonly',
                IntersectionObserver: 'readonly',
                requestAnimationFrame: 'readonly',
                cancelAnimationFrame: 'readonly',
                MouseEvent: 'readonly',
                KeyboardEvent: 'readonly',
                Event: 'readonly',
                HTMLElement: 'readonly',
                HTMLInputElement: 'readonly',
                HTMLTextAreaElement: 'readonly',
                HTMLDivElement: 'readonly',
                Element: 'readonly',
                Node: 'readonly',
                NodeList: 'readonly',
                DOMRect: 'readonly',
                getComputedStyle: 'readonly',
                performance: 'readonly',
                location: 'readonly',
                history: 'readonly',
                atob: 'readonly',
                btoa: 'readonly',
            },
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
        rules: {
            // React
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',

            // React Hooks
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',

            // TypeScript
            '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
            '@typescript-eslint/no-explicit-any': 'warn',
            '@typescript-eslint/no-empty-object-type': 'off',

            // General
            'no-console': ['warn', { allow: ['warn', 'error'] }],
        },
    },
    {
        // Ignore unused vars in .d.ts files
        files: ['**/*.d.ts'],
        rules: {
            '@typescript-eslint/no-unused-vars': 'off',
        },
    },
    {
        ignores: [
            'node_modules/**',
            'public/**',
            'vendor/**',
            'bootstrap/**',
            '*.config.js',
            '*.config.ts',
        ],
    }
);
