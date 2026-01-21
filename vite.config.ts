import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        server: {
            watch: {
                ignored: [
                    "**/dev_*_data/**",
                    "**/storage/**",
                ],
            },
            host: "0.0.0.0",
            hmr: {
                host: env.VITE_HOST || '0.0.0.0'
            },
        },
        build: {
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        if (id.includes('node_modules')) {
                            // React core - keep react, react-dom and scheduler together
                            if (id.includes('react-dom') || id.includes('scheduler') || id.includes('/react/') || id.includes('react/jsx')) {
                                return 'vendor-react';
                            }
                            // Split other heavy libraries into separate chunks
                            if (id.includes('@xyflow')) {
                                return 'vendor-reactflow';
                            }
                            if (id.includes('@xterm') || id.includes('xterm')) {
                                return 'vendor-xterm';
                            }
                            if (id.includes('@headlessui')) {
                                return 'vendor-headlessui';
                            }
                            if (id.includes('lucide-react')) {
                                return 'vendor-lucide';
                            }
                            if (id.includes('@inertiajs')) {
                                return 'vendor-inertia';
                            }
                            if (id.includes('d3-') || id.includes('/d3/')) {
                                return 'vendor-d3';
                            }
                            // Group remaining large modules
                            if (id.includes('monaco') || id.includes('codemirror')) {
                                return 'vendor-editor';
                            }
                        }
                    },
                },
            },
        },
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/app.tsx',
                    'resources/js/project-map/index.jsx',
                ],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            react(),
        ],
        resolve: {
            alias: {
                vue: 'vue/dist/vue.esm-bundler.js',
                '@': path.resolve(__dirname, 'resources/js'),
            },
        },
    };
});
