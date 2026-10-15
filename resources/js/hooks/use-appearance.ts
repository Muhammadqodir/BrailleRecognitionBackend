import { useCallback, useEffect, useState } from 'react';

import logo from '../../images/logo.png';

export type Appearance = 'light' | 'dark' | 'system';

const prefersDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;

const resolveIsDark = (appearance: Appearance) => appearance === 'dark' || (appearance === 'system' && prefersDark());

const setFavicon = () => {
    let link = document.querySelector<HTMLLinkElement>("link[rel='icon']");

    if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
    }

    link.type = 'image/png';
    link.href = logo;
};

const applyTheme = (appearance: Appearance) => {
    const isDark = resolveIsDark(appearance);

    document.documentElement.classList.toggle('dark', isDark);
    setFavicon();
};

const storedAppearance = (): Appearance => (localStorage.getItem('appearance') as Appearance) || 'system';

/** Reapplies the theme when the OS preference changes while `appearance` is "system". */
const handleSystemChange = () => applyTheme(storedAppearance());

/** Runs once at boot, matching the inline script in app.blade.php that avoids a flash. */
export function initializeAppearance() {
    applyTheme(storedAppearance());
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', handleSystemChange);
}

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>(storedAppearance);
    const [isDark, setIsDark] = useState<boolean>(() => resolveIsDark(storedAppearance()));

    const updateAppearance = useCallback((mode: Appearance) => {
        setAppearance(mode);
        localStorage.setItem('appearance', mode);
        applyTheme(mode);
        setIsDark(resolveIsDark(mode));
    }, []);

    useEffect(() => {
        applyTheme(appearance);
        setIsDark(resolveIsDark(appearance));

        if (appearance !== 'system') {
            return;
        }

        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = () => setIsDark(prefersDark());

        mediaQuery.addEventListener('change', handleChange);
        return () => mediaQuery.removeEventListener('change', handleChange);
    }, [appearance]);

    return { appearance, updateAppearance, isDark } as const;
}
