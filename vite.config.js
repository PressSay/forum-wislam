import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'quill': ['quill', 'quill/assets'],
                    'highlight': ['highlight.js/lib/common'],
                    'quill-extras': ['quill-delta-to-html', 'quill-resize-module'],
                    'thread': ['resources/js/Pages/User/Thread.vue']
                },
            },
        },
    },
    optimizeDeps: {
        include: ['quill', 'highlight.js/lib/common', 'quill-delta-to-html', 'quill-resize-module', 'quill/assets']
    },
    assetsInclude: ['**/*.svg'],
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/js/app.js',
                'resources/js/Pages/User/Thread.vue'],
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
    server: {
        port: "5173",
        host: '192.168.1.6',
        allowedHosts: ['http://192.168.1.6:8000'],
        proxy: {
            '/api': {
                target: 'http://192.168.1.6:8000/api',
                changeOrigin: true,
                rewrite: path => path.replace(/^\/api/, '')
            },
            '/ws': {
                target: 'ws://192.168.1.6',
                ws: true,
                changeOrigin: true,
                rewrite: path => path.replace(/^\/ws/, '')
            }
        }
    }
});
