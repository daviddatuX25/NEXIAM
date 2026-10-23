export const APPLICANT_SUBMITTED_COOKIE = 'nexiam_applicant_submitted';
export const APPLICANT_SUBMITTED_STORAGE = 'nexiam.applicant_submitted';
export const APPLICANT_ACCESS_KEY_STORAGE = 'nexiam.applicant_access_key';

/**
 * Returns true if the current browser session has evidence of a submitted application:
 * 1. Server-shared page prop (`has_submitted_application`)
 * 2. Unencrypted browser cookie (`nexiam_applicant_submitted=1`)
 * 3. LocalStorage flag (`nexiam.applicant_submitted=1`)
 * 4. LocalStorage saved access key (`nexiam.applicant_access_key`)
 *
 * @param {Record<string, any>|null} pageProps
 * @returns {boolean}
 */
export function isApplicantSubmitted(pageProps = null) {
    if (pageProps?.has_submitted_application) {
        return true;
    }

    if (typeof document === 'undefined') {
        return false;
    }

    try {
        const cookies = document.cookie ? document.cookie.split('; ') : [];
        const hasCookie = cookies.some((c) => {
            const [name, val] = c.split('=');
            return (
                name === APPLICANT_SUBMITTED_COOKIE &&
                (val === '1' || val === 'true')
            );
        });

        if (hasCookie) {
            return true;
        }

        if (window.localStorage?.getItem(APPLICANT_SUBMITTED_STORAGE) === '1') {
            return true;
        }

        if (Boolean(window.localStorage?.getItem(APPLICANT_ACCESS_KEY_STORAGE))) {
            return true;
        }
    } catch {
        // Storage access might be restricted in private browsing.
    }

    return false;
}

/**
 * Records that the applicant has submitted an application on this browser.
 * Sets both the persistent cookie (1 year) and the localStorage key.
 */
export function markApplicantSubmitted() {
    if (typeof document === 'undefined') {
        return;
    }

    try {
        document.cookie = `${APPLICANT_SUBMITTED_COOKIE}=1; path=/; max-age=31536000; SameSite=Lax`;
        window.localStorage?.setItem(APPLICANT_SUBMITTED_STORAGE, '1');
    } catch {
        // Storage access might be restricted in private browsing.
    }
}
