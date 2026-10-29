import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

/**
 * Resolves Inertia pages from either modular directories:
 *   Inertia::render('CoreHR/Employees/Index') -> resources/js/Modules/CoreHR/Pages/Employees/Index.tsx
 * or standard starter-kit page directories:
 *   Inertia::render('dashboard')              -> resources/js/pages/dashboard.tsx (or Pages/)
 */
export function resolveModulePage(name: string) {
    const modulePages = import.meta.glob('../Modules/*/Pages/**/*.tsx');
    const lowerStandardPages = import.meta.glob('../pages/**/*.tsx');
    const upperStandardPages = import.meta.glob('../Pages/**/*.tsx');

    const segments = name.split('/');
    if (segments.length >= 2) {
        const [moduleName, ...rest] = segments;
        const moduleCandidate = `../Modules/${moduleName}/Pages/${rest.join('/')}.tsx`;

        if (moduleCandidate in modulePages) {
            return resolvePageComponent(moduleCandidate, modulePages);
        }
    }

    const lowerCandidate = `../pages/${name}.tsx`;
    if (lowerCandidate in lowerStandardPages) {
        return resolvePageComponent(lowerCandidate, lowerStandardPages);
    }

    return resolvePageComponent(`../Pages/${name}.tsx`, upperStandardPages);
}

