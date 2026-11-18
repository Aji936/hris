import type { CapacitorConfig } from '@capacitor/cli';

const serverUrl = process.env.CAPACITOR_SERVER_URL ?? 'http://192.168.4.9:8000';

const config: CapacitorConfig = {
    appId: 'com.company.hris',
    appName: 'HRIS Portal',
    webDir: 'public/build',
    server: {
        url: serverUrl,
        androidScheme: 'https',
        cleartext: true,
        allowNavigation: [
            '192.168.*.*',
            '10.*.*.*',
            'localhost',
            '127.0.0.1',
        ],
    },
    android: {
        allowMixedContent: true,
    },
};

export default config;

