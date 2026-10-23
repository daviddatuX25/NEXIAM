const MAX_XIAM_NAVIGATION_TARGETS = 12;

/**
 * @param {unknown} value
 * @param {string | undefined} currentOrigin
 * @returns {{ id: string, label: string, href: string, version: 1 }[]}
 */
export function normalizeXiamNavigation(value, currentOrigin = globalThis.location?.origin) {
    if (!Array.isArray(value) || typeof currentOrigin !== 'string' || !currentOrigin) {
        return [];
    }

    const navigation = [];
    const seenIds = new Set();

    for (const rawTarget of value) {
        if (navigation.length >= MAX_XIAM_NAVIGATION_TARGETS) break;
        if (!rawTarget || typeof rawTarget !== 'object' || Array.isArray(rawTarget)) continue;

        const { id, label, href, version } = rawTarget;
        if (
            version !== 1 ||
            typeof id !== 'string' || !id.trim() ||
            typeof label !== 'string' || !label.trim() ||
            typeof href !== 'string' || !href.startsWith('/') || href.startsWith('//') ||
            href.includes('\\') || /[\u0000-\u001f\u007f]/.test(href) ||
            seenIds.has(id.trim())
        ) {
            continue;
        }

        try {
            const parsedHref = new URL(href, currentOrigin);
            const parsedOrigin = new URL(currentOrigin);
            if (
                parsedHref.origin !== parsedOrigin.origin ||
                !['http:', 'https:'].includes(parsedHref.protocol) ||
                parsedHref.username || parsedHref.password
            ) {
                continue;
            }
        } catch {
            continue;
        }

        const normalizedId = id.trim();
        seenIds.add(normalizedId);
        navigation.push({
            id: normalizedId,
            label: label.trim(),
            href,
            version: 1,
        });
    }

    return navigation;
}
