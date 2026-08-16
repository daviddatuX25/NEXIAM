import { writable } from 'svelte/store';

/**
 * Shared applicant-QR scan modal (founder 2026-08-15).
 * One scanner for every surface; pages may pass a `preselect` so the scan has
 * a purpose (e.g. consultation → open admission support) and skips the
 * general action chooser.
 */
export const scanModal = writable({ open: false, preselect: null });

export function openScanModal(preselect = null) {
    scanModal.set({ open: true, preselect });
}

export function closeScanModal() {
    scanModal.set({ open: false, preselect: null });
}
