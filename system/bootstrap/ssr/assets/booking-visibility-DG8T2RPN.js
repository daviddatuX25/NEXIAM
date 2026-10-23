import { w as writable, p as info, q as success } from "../app.js";
const STORAGE_KEY = "nexiam_booking_mode_unlocked";
function getInitialState() {
  if (typeof window === "undefined") return false;
  try {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has("booking") || urlParams.has("booking_mode")) {
      localStorage.setItem(STORAGE_KEY, "1");
      return true;
    }
    return localStorage.getItem(STORAGE_KEY) === "1";
  } catch {
    return false;
  }
}
const bookingUnlocked = writable(getInitialState());
function unlockBookingMode() {
  if (typeof window !== "undefined") {
    try {
      localStorage.setItem(STORAGE_KEY, "1");
    } catch {
    }
  }
  bookingUnlocked.set(true);
  success("Advanced booking controls revealed.");
}
function lockBookingMode() {
  if (typeof window !== "undefined") {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
    }
  }
  bookingUnlocked.set(false);
  info("Advanced booking controls hidden.");
}
function toggleBookingMode() {
  let isCurrentlyUnlocked = false;
  bookingUnlocked.subscribe((val) => isCurrentlyUnlocked = val)();
  if (isCurrentlyUnlocked) {
    lockBookingMode();
  } else {
    unlockBookingMode();
  }
}
if (typeof window !== "undefined") {
  window.addEventListener("keydown", (e) => {
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === "b" || e.key === "B")) {
      e.preventDefault();
      toggleBookingMode();
    }
  });
}
export {
  bookingUnlocked as b
};
