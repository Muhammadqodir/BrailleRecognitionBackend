import { useEffect, useState } from 'react';

/** Fetches `{ data: T }` from `url` whenever it changes; clears state when `url` is null (e.g. dialog closed). */
export function useEntityDetail<T>(url: string | null) {
    const [data, setData] = useState<T | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(false);

    useEffect(() => {
        if (!url) {
            setData(null);
            setError(false);
            return;
        }

        let cancelled = false;
        setLoading(true);
        setError(false);

        fetch(url, { headers: { Accept: 'application/json' } })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Request failed with ${response.status}`);
                }
                return response.json();
            })
            .then((json: { data: T }) => {
                if (!cancelled) {
                    setData(json.data);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError(true);
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [url]);

    return { data, loading, error };
}
