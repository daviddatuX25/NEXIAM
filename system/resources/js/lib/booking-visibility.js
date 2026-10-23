import { writable } from 'svelte/store';
import { success, info } from './toast.js';

const STORAGE_KEY = 'nexiam_booking_mode_unlocked';

function getInitialState() {
    if (typeof window === 'undefined') return false;
    try {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('booking') || urlParams.has('booking_mode')) {
            localStorage.setItem(STORAGE_KEY, '1');
            return true;
        }
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

export const bookingUnlocked = writable(getInitialState());

export function unlockBookingMode() {
    if (typeof window !== 'undefined') {
        try {
            localStorage.setItem(STORAGE_KEY, '1');
        } catch {}
    }
    bookingUnlocked.set(true);
    success('Advanced booking controls revealed.');
}

export function lockBookingMode() {
    if (typeof window !== 'undefined') {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {}
    }
    bookingUnlocked.set(false);
    info('Advanced booking controls hidden.');
}

export function toggleBookingMode() {
    let isCurrentlyUnlocked = false;
    bookingUnlocked.subscribe((val) => (isCurrentlyUnlocked = val))();
    if (isCurrentlyUnlocked) {
        lockBookingMode();
    } else {
        unlockBookingMode();
    }
}

// Multi-click counter for Easter Egg trigger (e.g. 5 clicks on session title)
let clickCount = 0;
let lastClickTime = 0;

export function handleEasterEggClick() {
    const now = Date.now();
    if (now - lastClickTime > 1500) {
        clickCount = 0;
    }
    lastClickTime = now;
    clickCount += 1;
    if (clickCount >= 5) {
        clickCount = 0;
        toggleBookingMode();
    }
}

// Window keydown listener for Ctrl+Shift+B or Cmd+Shift+B
if (typeof window !== 'undefined') {
    window.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'b' || e.key === 'B')) {
            e.preventDefault();
            toggleBookingMode();
        }
    });
}
