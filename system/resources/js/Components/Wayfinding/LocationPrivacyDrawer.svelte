<script lang="ts">
    import { fade, fly } from 'svelte/transition';

    interface Props {
        open?: boolean;
        onAccept: () => void;
        onDecline: () => void;
        targetVenueName: string;
        policyHref?: string;
    }

    let {
        open = $bindable(false),
        onAccept,
        onDecline,
        targetVenueName,
        policyHref = '/policies',
    }: Props = $props();

    function saveChoice(choice: 'granted' | 'manual'): void {
        try {
            window.localStorage.setItem('nexiam_wayfinding_consent_v1', choice);
        } catch {
            // The choice still applies for this visit when browser storage is unavailable.
        }
    }

    function acceptCompass(): void {
        saveChoice('granted');
        open = false;
        onAccept();
    }

    function browseManually(): void {
        saveChoice('manual');
        open = false;
        onDecline();
    }

    function closeDrawer(): void {
        open = false;
    }

    function handleWindowKeydown(event: KeyboardEvent): void {
        if (open && event.key === 'Escape') {
            closeDrawer();
        }
    }
</script>

<svelte:window onkeydown={handleWindowKeydown} />

{#if open}
    <div
        class="fixed inset-0 z-[100] flex items-end justify-center bg-slate-950/45 p-0 backdrop-blur-sm sm:items-center sm:p-6"
        transition:fade={{ duration: 180 }}
    >
        <button
            type="button"
            class="absolute inset-0 size-full cursor-default"
            aria-label="Close privacy choices"
            onclick={closeDrawer}
        ></button>
        <div
            class="relative z-10 w-full max-w-lg overflow-hidden rounded-t-3xl border border-white/70 bg-white shadow-2xl shadow-slate-950/25 sm:rounded-3xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="location-privacy-title"
            aria-describedby="location-privacy-description"
            tabindex="-1"
            transition:fly={{ y: 18, duration: 220 }}
            
        >
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-cyan-500 via-blue-600 to-indigo-600"></div>

            <div class="flex items-start justify-between gap-5 px-6 pb-5 pt-8 sm:px-8">
                <div class="space-y-2">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Campus Compass</p>
                    <h2 id="location-privacy-title" class="text-2xl font-semibold leading-tight tracking-tight text-slate-950 sm:text-[1.75rem]">
                        Find {targetVenueName} with Campus Compass
                    </h2>
                    <p id="location-privacy-description" class="text-sm leading-6 text-slate-600">
                        We use your phone sensors in local browser RAM to point the arrow toward your venue.
                    </p>
                </div>
                <button
                    type="button"
                    class="-mr-2 -mt-2 inline-flex size-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2"
                    aria-label="Close privacy choices"
                    onclick={closeDrawer}
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true">
                        <path d="m6 6 12 12M18 6 6 18" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <div class="mx-6 rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 sm:mx-8 sm:p-5">
                <div class="flex items-center gap-2 text-xs font-extrabold tracking-[0.12em] text-emerald-900">
                    <span aria-hidden="true">🛡️</span>
                    <span>ZERO SERVER TRACKING</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-emerald-950/85">
                    Your GPS coordinates and heading never leave this device. Nothing is logged, stored, or monitored on institutional servers.
                </p>
            </div>

            <div class="px-6 pb-7 pt-5 sm:px-8 sm:pb-8">
                <a
                    href={policyHref}
                    class="inline-flex min-h-10 items-center text-sm font-semibold leading-5 text-blue-700 underline decoration-blue-300 underline-offset-4 transition hover:text-blue-900 hover:decoration-blue-700 focus-visible:rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-600"
                >
                    Read official Campus Navigation &amp; Geolocation Privacy Notice
                    <span class="sr-only"> (opens policy page)</span>
                </a>

                <div class="mt-6 grid gap-3">
                    <button
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-700 focus-visible:ring-offset-2 active:scale-[0.99]"
                        onclick={acceptCompass}
                    >
                        Enable Compass &amp; GPS
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2 active:scale-[0.99]"
                        onclick={browseManually}
                    >
                        Browse Manually (Photo &amp; Map Only)
                    </button>
                </div>
                <p class="mt-4 text-center text-xs leading-5 text-slate-500">
                    Compass access is optional. You can browse the venue photo, map, and directions without enabling GPS.
                </p>
            </div>
        </div>
    </div>
{/if}
