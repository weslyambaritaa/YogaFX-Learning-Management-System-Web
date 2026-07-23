import { useEffect, useRef } from "react";
import { router } from "@inertiajs/react";

function readStorage(key) {
    try {
        const raw = window.sessionStorage.getItem(key);
        return raw !== null ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeStorage(key, value) {
    try {
        window.sessionStorage.setItem(key, JSON.stringify(value));
    } catch {
        // sessionStorage unavailable (private browsing, etc). Fail silently,
        // pagination memory just won't persist for this session.
    }
}

/**
 * For admin index tables that paginate entirely on the client (plain
 * useState, no server round-trip). Remembers the last page number visited
 * under `storageKey` so that navigating away (e.g. to edit a row) and back
 * restores the same page instead of always resetting to page 1.
 *
 * Usage:
 *   const { getInitialPage, persistPage } = usePersistedPage('admin-lessons-page');
 *   const [currentPage, setCurrentPageState] = useState(getInitialPage);
 *   const setCurrentPage = (page) => { setCurrentPageState(page); persistPage(page); };
 */
export function usePersistedPage(storageKey, defaultPage = 1) {
    const getInitialPage = () => {
        const stored = readStorage(storageKey);
        return typeof stored === "number" && stored > 0 ? stored : defaultPage;
    };

    const persistPage = (page) => writeStorage(storageKey, page);

    return { getInitialPage, persistPage };
}

/**
 * For admin index tables paginated on the server (search/filters/page sent
 * as query params via router.get). Persists the current filters+page under
 * `storageKey` whenever they change, and — if the page is opened "bare"
 * (no query string at all, e.g. after clicking "Back" from an Edit page or
 * a sidebar link) — automatically restores the last-used filters/page once
 * on mount.
 *
 * Usage:
 *   useRestoreIndexFilters('admin-admins-index', { search, scope, per_page: perPage, page: admins.current_page });
 */
export function useRestoreIndexFilters(storageKey, currentFilters) {
    const hasCheckedRestore = useRef(false);

    useEffect(() => {
        if (hasCheckedRestore.current) return;
        hasCheckedRestore.current = true;

        const hasQueryInUrl = window.location.search.length > 0;
        const stored = readStorage(storageKey);

        if (!hasQueryInUrl && stored && Object.keys(stored).length > 0) {
            router.get(window.location.pathname, stored, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            });
        }
        // Only ever run this restore check once, right after mount.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        writeStorage(storageKey, currentFilters);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [JSON.stringify(currentFilters)]);
}
