import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig(({ command, mode }) => {
    const isProduction = mode === 'production';
    
    return {
        resolve: {
            alias: {
                'vue': 'vue/dist/vue.esm-bundler.js',
            },
        },
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/css/flatpickr.css',
                    'resources/js/app.js',
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
        ],
        build: {
            minify: isProduction ? 'terser' : false,
            terserOptions: {
                compress: {
                    drop_console: isProduction,
                    drop_debugger: isProduction,
                },
            },
            sourcemap: !isProduction,
            rollupOptions: {
                output: {
                    manualChunks: (id) => {
                        // Node modules chunking
                        if (id.includes('node_modules')) {
                            // Vue ecosystem
                            if (id.includes('vue')) return 'vendor-vue';
                            if (id.includes('@vue')) return 'vendor-vue-addons';
                            
                            // Date handling - Separate chunks for each date library
                            if (id.includes('@vuepic/vue-datepicker')) return 'vendor-vuepic';
                            if (id.includes('dayjs')) return 'vendor-dayjs';
                            if (id.includes('moment-timezone')) return 'vendor-moment';
                            if (id.includes('flatpickr')) return 'vendor-flatpickr';
                            
                            // Maps
                            if (id.includes('leaflet')) return 'vendor-leaflet';
                            
                            // Forms and validation
                            if (id.includes('vuelidate') || 
                                id.includes('validator')) {
                                return 'vendor-forms';
                            }
                            
                            // Rich text editor
                            if (id.includes('tiptap') || 
                                id.includes('prosemirror')) {
                                return 'vendor-editor';
                            }
                            
                            // HTTP client
                            if (id.includes('axios')) return 'vendor-http';
                            
                            // Utilities
                            if (id.includes('lodash')) return 'vendor-utils';
                            if (id.includes('sortable')) return 'vendor-utils';
                            
                            return 'vendor-misc';
                        }
                        
                        // Application code splitting
                        if (id.includes('/components/UI/')) return 'ui-components';
                        if (id.includes('/components/Page/')) return 'page-components';
                        if (id.includes('/Auth/')) return 'auth';
                        if (id.includes('/Messaging/')) return 'messaging';
                        if (id.includes('/Creation/')) return 'creation';
                        if (id.includes('/Curated/')) return 'curated';
                    },
                    chunkFileNames: isProduction 
                        ? 'assets/[name]-[hash].js' 
                        : 'assets/[name].js',
                    assetFileNames: isProduction
                        ? 'assets/[name]-[hash][extname]'
                        : 'assets/[name][extname]',
                }
            },
            cssMinify: true,
            assetsInlineLimit: 4096,
            chunkSizeWarningLimit: 500,
        },
        server: {
            hmr: {
                overlay: true,
            },
        },
        optimizeDeps: {
            include: [
                'vue',
                'leaflet',
                '@vuelidate/core',
                '@tiptap/vue-3',
                'axios',
                'dayjs'
            ],
            exclude: ['@vuepic/vue-datepicker'] // Exclude from optimization
        }
    };
});