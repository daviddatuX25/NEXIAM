const APPLICANT_CODE = /^[A-Za-z0-9_-]+$/;
const APPLICANT_PATH = /^\/scan\/applicant\/[A-Za-z0-9_-]+$/;
const ACTIVATION_FRAGMENT = /^#v=[A-Z2-7]{10}&t=[a-f0-9]{32}$/;

export function classifyLoginQr(decoded, currentHref) {
    const value = String(decoded).trim();
    const current = new URL(currentHref);
    const loopback =
        current.protocol === 'http:' &&
        ['localhost', '127.0.0.1', '[::1]'].includes(current.hostname);
    if (current.protocol !== 'https:' && !loopback) {
        throw new Error(
            'QR scanning is supported only over HTTPS or loopback development.',
        );
    }

    const applicantCode = APPLICANT_CODE.test(value) ? value : null;
    const target = applicantCode
        ? new URL(`/scan/applicant/${applicantCode}`, current.origin)
        : new URL(value);
    if (
        target.origin !== current.origin ||
        target.protocol !== current.protocol ||
        target.username !== '' ||
        target.password !== '' ||
        target.search !== ''
    ) {
        throw new Error(
            'This QR must be a supported NEXIAM entry on this same site.',
        );
    }

    if (
        target.pathname === '/staff/activate' &&
        ACTIVATION_FRAGMENT.test(target.hash)
    ) {
        return { kind: 'activation', href: target.href };
    }
    if (APPLICANT_PATH.test(target.pathname) && target.hash === '') {
        return { kind: 'applicant', href: target.href };
    }

    throw new Error(
        'This QR is not a supported staff activation voucher or applicant pass.',
    );
}
