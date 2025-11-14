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
                    'resources/css/datepicker.css',
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
        },
        server: {
            host: 'localhost',
            port: 5173,
            hmr: {
                host: 'localhost',
                overlay: true,
            },
        },
    };
});