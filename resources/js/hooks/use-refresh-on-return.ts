import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';

/**
 * For a page that sends you to another tab to change something (Tune opens
 * the labeling page): call the returned function when that tab opens, and
 * the page reloads `only` these props the next time you come back to it.
 */
export function useRefreshOnReturn(only: string[]): () => void {
    const pending = useRef(false);
    const props = useRef(only);
    props.current = only;

    useEffect(() => {
        const refresh = () => {
            if (pending.current && document.visibilityState === 'visible') {
                pending.current = false;
                router.reload({ only: props.current });
            }
        };

        window.addEventListener('focus', refresh);
        document.addEventListener('visibilitychange', refresh);

        return () => {
            window.removeEventListener('focus', refresh);
            document.removeEventListener('visibilitychange', refresh);
        };
    }, []);

    return useCallback(() => {
        pending.current = true;
    }, []);
}
