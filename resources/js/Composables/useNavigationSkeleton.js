import { reactive, readonly } from 'vue';

// Each page renders its own AuthenticatedLayout, so the "a visit is loading"
// flag lives at module level and the router listeners are installed once
// from app.js.
const state = reactive({ visible: false, variant: 'list' });

// Only show the skeleton when loading is noticeable, so fast navigations
// don't flash grey placeholders.
const SHOW_DELAY_MS = 200;

/**
 * Pick the placeholder shape that matches the destination page.
 * @param {string} pathname
 */
export function resolveSkeletonVariant(pathname) {
    const path = pathname.replace(/\/+$/, '') || '/';

    if (path === '/dashboard' || path === '/admin/ai-usage') return 'dashboard';
    if (path === '/tasks/kanban') return 'board';
    if (/\/(create|edit)$/.test(path) || path === '/profile' || path.startsWith('/admin/settings')) return 'form';
    if (/^\/(meetings|tasks)\/\d+$/.test(path)) return 'detail';

    return 'list';
}

/**
 * A full page change — not a partial reload (meeting status polling), not a
 * filter/search/pagination visit that keeps the page's state (the user may be
 * typing in a search box), and not a form submission.
 */
function isPageNavigation(visit) {
    return visit.method === 'get'
        && (!visit.only || visit.only.length === 0)
        && visit.preserveState !== true;
}

export function installNavigationSkeleton(router) {
    let timer = null;

    router.on('start', (event) => {
        const visit = event.detail.visit;
        if (!isPageNavigation(visit)) return;

        clearTimeout(timer);
        timer = setTimeout(() => {
            state.variant = resolveSkeletonVariant(visit.url.pathname);
            state.visible = true;
        }, SHOW_DELAY_MS);
    });

    // Fires for success, error and cancelled visits alike.
    router.on('finish', () => {
        clearTimeout(timer);
        timer = null;
        state.visible = false;
    });
}

export function useNavigationSkeleton() {
    return readonly(state);
}
