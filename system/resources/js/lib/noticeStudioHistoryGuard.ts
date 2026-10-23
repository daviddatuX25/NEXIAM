export interface NoticeHistoryGuard {
    dispose(): void;
}
export interface NoticeHistoryGuardOptions {
    hasUnsavedWork: () => boolean;
    onNavigation: (leave: () => void) => void;
    browser?: Window;
    onRestoring?: (restoring: boolean) => void;
    entryIndex?: () => number | null;
    onCurrentEntry?: () => void;
}
let listeningBrowser: Window | null = null;
let activeGuard: ((event: PopStateEvent) => void) | null = null;
let dispatcher: ((event: PopStateEvent) => void) | null = null;

/** Install before Inertia initializes its popstate listener; idle dispatch is a no-op. */
export function initializeNoticeStudioHistoryGuard(browser: Window = window): void {
    if (listeningBrowser === browser) return;
    if (listeningBrowser && dispatcher) listeningBrowser.removeEventListener('popstate', dispatcher, true);
    dispatcher = (event) => activeGuard?.(event);
    listeningBrowser = browser;
    browser.addEventListener('popstate', dispatcher, true);
}


function currentEntryIndex(browser: Window): number | null {
    const navigation: unknown = Reflect.get(browser, 'navigation');
    if (!navigation || typeof navigation !== 'object' || !('currentEntry' in navigation)) return null;
    const entry = navigation.currentEntry;
    if (!entry || typeof entry !== 'object' || !('index' in entry) || typeof entry.index !== 'number' || entry.index < 0) return null;
    return entry.index;
}

/**
 * Popstate cannot be cancelled. Capture it before Inertia, restore the exact current
 * history entry, then offer a real Stay/Leave decision while the editor stays alive.
 * Navigation API indexes preserve both Back and Forward history in current browsers.
 * Older browsers without indexes retain the workspace via a restored history entry.
 */
export function createNoticeStudioHistoryGuard(options: NoticeHistoryGuardOptions): NoticeHistoryGuard {
    const browser = options.browser ?? window;
    initializeNoticeStudioHistoryGuard(browser);
    const index = options.entryIndex ?? (() => currentEntryIndex(browser));
    let origin = { index: index(), url: browser.location.href, state: browser.history.state };
    let restoring = false;
    let bypass = false;
    let disposed = false;
    let destination: { index: number | null; url: string; state: unknown } | null = null;

    function offerNavigation() {
        const target = destination;
        if (!target || disposed) return;
        options.onNavigation(() => {
            if (disposed) return;
            bypass = true;
            if (origin.index !== null && target.index !== null && target.index !== origin.index) {
                browser.history.go(target.index - origin.index);
            } else {
                // The old-browser retention entry is consumed only after explicit Leave.
                browser.history.replaceState(target.state, '', target.url);
                browser.dispatchEvent(new PopStateEvent('popstate', { state: target.state }));
            }
        });
    }
    function onPopstate(event: PopStateEvent) {
        if (disposed || bypass) return;
        const at = index();
        if (restoring) {
            event.stopImmediatePropagation();
            if (origin.index !== null && at !== null && at !== origin.index) {
                destination = { index: at, url: browser.location.href, state: event.state };
                browser.history.go(origin.index - at);
                return;
            }
            restoring = false;
            options.onRestoring?.(false);
            offerNavigation();
            return;
        }
        if (!options.hasUnsavedWork()) {
            const sameEditor = browser.location.href === origin.url;
            origin = { index: at, url: browser.location.href, state: event.state };
            if (sameEditor && options.onCurrentEntry) {
                event.stopImmediatePropagation();
                options.onCurrentEntry();
            }
            return;
        }
        event.stopImmediatePropagation();
        destination = { index: at, url: browser.location.href, state: event.state };
        if (origin.index !== null && at !== null && at !== origin.index) {
            restoring = true;
            options.onRestoring?.(true);
            browser.history.go(origin.index - at);
        } else {
            browser.history.pushState(origin.state, '', origin.url);
            offerNavigation();
        }
    }
    const registration = onPopstate;
    activeGuard = registration;
    return {
        dispose() {
            disposed = true;
            destination = null;
            if (activeGuard === registration) activeGuard = null;
        },
    };
}
