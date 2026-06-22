import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const publicUrl = env.APP_PUBLIC_URL || env.APP_URL || 'http://localhost:8000';
    const vitePort = Number(env.VITE_PORT || 5173);

    let hmrHost = env.VITE_HMR_HOST || 'localhost';
    let hmrProtocol = env.VITE_HMR_PROTOCOL || 'ws';

    try {
        const parsedUrl = new URL(publicUrl);
        hmrHost = env.VITE_HMR_HOST || parsedUrl.hostname;
        hmrProtocol = env.VITE_HMR_PROTOCOL || (parsedUrl.protocol === 'https:' ? 'wss' : 'ws');
    } catch {
        // Keep fallback values when the public URL is not parseable.
    }

    return {
        server: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: true,
            hmr: {
                host: hmrHost,
                protocol: hmrProtocol,
                clientPort: Number(env.VITE_HMR_CLIENT_PORT || vitePort),
            },
        },
        plugins: [
            laravel({
                input: 'resources/js/app.jsx',
                refresh: true,
            }),
            react(),
        ],
    };
});
