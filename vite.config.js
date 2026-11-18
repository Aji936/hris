import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
        {
            name: 'capacitor-fallback-index',
            closeBundle() {
                const outDir = path.resolve(process.cwd(), 'public/build');
                if (fs.existsSync(outDir)) {
                    const indexPath = path.join(outDir, 'index.html');
                    if (!fs.existsSync(indexPath)) {
                        fs.writeFileSync(
                            indexPath,
                            '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HRIS Portal</title></head><body><div id="app">Connecting to HRIS Server...</div></body></html>',
                            'utf8',
                        );
                    }
                }
            },
        },
    ],
    esbuild: {
        jsx: 'automatic',
    },
});
