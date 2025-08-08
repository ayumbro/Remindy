import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";
import path from 'path';
import fs from 'fs';

// Default configuration
const defaultConfig = {
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
            detectTls: false,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    esbuild: {
        jsx: 'automatic',
    },
    server: {
        host: '127.0.0.1',
        port: 5173,
    },
};

// Check for local configuration override
const localConfigPath = path.resolve(__dirname, 'vite.config.local.js');
let localConfig = {};

if (fs.existsSync(localConfigPath)) {
    try {
        const localConfigModule = await import(localConfigPath);
        localConfig = localConfigModule.default || localConfigModule;
        console.log('✓ Using local Vite configuration overrides');
    } catch (error) {
        console.warn('⚠ Failed to load local Vite configuration:', error.message);
    }
}

// Merge configurations with local overrides taking precedence
const mergedConfig = {
    ...defaultConfig,
    ...localConfig,
    server: {
        ...defaultConfig.server,
        ...(localConfig.server || {}),
    },
    resolve: {
        ...defaultConfig.resolve,
        ...(localConfig.resolve || {}),
        alias: {
            ...defaultConfig.resolve.alias,
            ...(localConfig.resolve?.alias || {}),
        },
    },
};

export default defineConfig(mergedConfig);