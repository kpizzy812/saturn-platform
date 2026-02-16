import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    plugins: [react()],
    test: {
        environment: 'jsdom',
        environmentOptions: {
            jsdom: {
                url: 'http://localhost',
            },
        },
        globals: true,
        setupFiles: ['./tests/Frontend/setup.ts'],
        include: [
            './tests/Frontend/**/*.{test,spec}.{ts,tsx}',
            './resources/js/**/__tests__/**/*.{test,spec}.{ts,tsx}',
        ],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'json', 'html'],
            include: ['resources/js/**/*.{ts,tsx}'],
            exclude: [
                'resources/js/**/*.d.ts',
                'resources/js/types/**',
                'resources/js/**/EXAMPLES.tsx',
            ],
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
