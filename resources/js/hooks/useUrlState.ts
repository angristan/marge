import { router, usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

/**
 * Hook to sync state with URL query parameters.
 * Uses Inertia's router to update URL without full page reload.
 */
export function useUrlState<T extends string>(
    key: string,
    defaultValue: T,
): [T, (value: T) => void] {
    const { url } = usePage();

    const value = useMemo(() => {
        const urlObj = new URL(url, window.location.origin);
        const param = urlObj.searchParams.get(key);
        return (param as T) || defaultValue;
    }, [url, key, defaultValue]);

    const setValue = useCallback(
        (newValue: T) => {
            const urlObj = new URL(window.location.href);

            if (newValue === defaultValue) {
                urlObj.searchParams.delete(key);
            } else {
                urlObj.searchParams.set(key, newValue);
            }

            router.visit(urlObj.pathname + urlObj.search, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [key, defaultValue],
    );

    return [value, setValue];
}
