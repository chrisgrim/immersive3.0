import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { sentryVitePlugin } from '@sentry/vite-plugin';

export default defineConfig(({ command, mode }) => {
    const isProduction = mode === 'production';

    // Only set on the production deploy's build step (see deploy.yml) — vite
    // build's `mode` is 'production' for the staging deploy too, so isProduction
    // can't tell them apart. Its presence is what gates both sourcemap
    // generation below and the upload plugin, so a build without it (staging,
    // or `npm run production` run locally without the secret) behaves exactly
    // like before this change: no sourcemaps, nothing new to leak.
    const sentryAuthToken = process.env.SENTRY_AUTH_TOKEN;

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
            // Uploads sourcemaps by debug ID after the build, then deletes the
            // .map files from public/build so they never reach rsync/the server —
            // Sentry gets real stack traces, the public bundle stays source-free.
            ...(sentryAuthToken
                ? [
                    sentryVitePlugin({
                        org: 'likely-rain',
                        project: 'ei-vue',
                        authToken: sentryAuthToken,
                        telemetry: false,
                        sourcemaps: {
                            filesToDeleteAfterUpload: ['./public/build/**/*.map'],
                        },
                    }),
                ]
                : []),
        ],
        build: {
            minify: isProduction ? 'terser' : false,
            terserOptions: {
                compress: {
                    drop_console: isProduction,
                    drop_debugger: isProduction,
                },
            },
            // 'hidden' emits the .map files for the upload above without adding
            // a //# sourceMappingURL comment to the shipped JS, so browsers never
            // fetch them. Skipped entirely when there's no token to upload with.
            sourcemap: sentryAuthToken ? 'hidden' : false,
        },
        server: {
            host: 'localhost',
            port: 5173,
            strictPort: true,
            cors: true,
            hmr: {
                host: 'localhost',
                overlay: true,
            },
        },
    };
});