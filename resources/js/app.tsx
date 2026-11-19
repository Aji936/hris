import '../css/app.css';

import { App as CapacitorApp } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { initializeTheme } from './hooks/use-appearance';
import { resolveModulePage } from './lib/resolveModulePage';

declare global {
    const route: typeof routeFn;
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const ROOT_PATHS = new Set<string>(['/', '/dashboard', '/login']);
let lastBackPressTimestamp = 0;

function showBackExitToast(message: string): void {
    const existing = document.getElementById('hris-android-back-toast');
    if (existing) {
        existing.remove();
    }

    const toast = document.createElement('div');
    toast.id = 'hris-android-back-toast';
    toast.textContent = message;
    toast.className =
        'fixed bottom-20 left-1/2 z-[100] -translate-x-1/2 rounded-full bg-slate-900/90 px-4 py-2 text-xs font-medium text-white shadow-lg backdrop-blur-sm transition-opacity';
    document.body.appendChild(toast);

    window.setTimeout(() => {
        toast.remove();
    }, 2000);
}

function registerInertiaCsrfAndSessionHandlers(): void {
    // Keep <meta name="csrf-token"> synchronized across Inertia page navigations
    router.on('navigate', (event) => {
        const nextToken = event.detail.page.props?.csrf_token;
        if (typeof nextToken === 'string' && nextToken.length > 0) {
            const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
            if (meta) {
                meta.content = nextToken;
            }
        }
    });

    // Prevent 419 Page Expired modal popup when session/CSRF token expires; redirect cleanly to /login
    router.on('invalid', (event) => {
        if (event.detail.response?.status === 419) {
            event.preventDefault();
            window.location.href = '/login';
        }
    });
}

function registerAndroidBackButtonHandler(): void {
    if (!Capacitor.isNativePlatform()) {
        return;
    }

    void CapacitorApp.addListener('backButton', ({ canGoBack }) => {
        // 1. If a modal, dialog, or mobile drawer is currently open, close it first
        const openDialog = document.querySelector<HTMLElement>(
            '[role="dialog"][data-state="open"], [role="alertdialog"][data-state="open"]',
        );
        if (openDialog) {
            const escapeEvent = new KeyboardEvent('keydown', {
                key: 'Escape',
                code: 'Escape',
                keyCode: 27,
                which: 27,
                bubbles: true,
                cancelable: true,
            });
            openDialog.dispatchEvent(escapeEvent);
            document.dispatchEvent(escapeEvent);
            return;
        }

        const currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
        const isRootPage = ROOT_PATHS.has(currentPath);

        // 2. If not on a root page (/dashboard or /login), navigate back in history
        if (!isRootPage) {
            if (canGoBack || window.history.length > 1) {
                window.history.back();
            } else {
                router.visit('/dashboard');
            }
            return;
        }

        // 3. On root pages (/dashboard or /login), require double-back within 2 seconds to exit app
        const now = Date.now();
        if (now - lastBackPressTimestamp < 2000) {
            void CapacitorApp.exitApp();
            return;
        }

        lastBackPressTimestamp = now;
        showBackExitToast('Tekan tombol Kembali sekali lagi untuk keluar aplikasi');
    });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolveModulePage(name),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
registerInertiaCsrfAndSessionHandlers();
registerAndroidBackButtonHandler();
