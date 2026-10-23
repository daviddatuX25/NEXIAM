<script>
    import { onMount } from 'svelte';
    import { HelpCircle } from 'lucide-svelte';
    import PublicFaqInquiryModal from '@/Components/public/PublicFaqInquiryModal.svelte';
    import { sound } from '@/lib/sound.js';
    import {
        calculateLauncherCoordinates,
        createLauncherDragTracker,
        getPersistedPosition,
    } from '@/lib/xiam-launcher-drag';

    const STORAGE_KEY = 'nexiam.public.inquiry-launcher';

    let launcherEl = $state(null);
    let launcherPosition = $state({ side: 'right', yRatio: 1.0 });
    let launcherLeft = $state(0);
    let launcherTop = $state(0);
    let isDragging = $state(false);
    let mounted = $state(false);
    let faqInquiryModalOpen = $state(false);

    const launcherDrag = createLauncherDragTracker({
        getElement: () => launcherEl,
        storageKey: STORAGE_KEY,
        onDraggingChange: (dragging) => {
            isDragging = dragging;
        },
        onMove: (coordinates) => {
            launcherLeft = coordinates.left;
            launcherTop = coordinates.top;
        },
        onSnap: (position, coordinates) => {
            launcherPosition = position;
            launcherLeft = coordinates.left;
            launcherTop = coordinates.top;
        },
    });

    function updateLauncherPosition() {
        const coordinates = calculateLauncherCoordinates(launcherPosition);
        launcherLeft = coordinates.left;
        launcherTop = coordinates.top;
    }

    onMount(() => {
        launcherPosition = getPersistedPosition(STORAGE_KEY);
        updateLauncherPosition();
        mounted = true;

        function handleResize() {
            updateLauncherPosition();
        }

        function handleOpenModal(event) {
            faqInquiryModalOpen = true;
        }

        window.addEventListener('resize', handleResize);
        window.addEventListener('orientationchange', handleResize);
        window.addEventListener('nexiam:open-faq-inquiry-modal', handleOpenModal);

        return () => {
            window.removeEventListener('resize', handleResize);
            window.removeEventListener('orientationchange', handleResize);
            window.removeEventListener('nexiam:open-faq-inquiry-modal', handleOpenModal);
        };
    });
</script>

<button
    bind:this={launcherEl}
    type="button"
    data-public-moving-island
    aria-label="FAQs and Official Inquiry Help"
    title="FAQs & Ask an Office"
    class="border-primary/30 bg-card hover:border-primary/50 focus:ring-primary/40 fixed z-40 flex size-12 cursor-pointer items-center justify-center rounded-full border shadow-xl transition-colors focus:ring-2 focus:outline-none"
    style={mounted
        ? `position: fixed; left: ${launcherLeft}px; top: ${launcherTop}px; right: auto; bottom: auto; touch-action: none; user-select: none; transition: ${isDragging ? 'none' : 'left 0.22s cubic-bezier(0.16, 1, 0.3, 1), top 0.22s cubic-bezier(0.16, 1, 0.3, 1)'};`
        : 'position: fixed; right: 20px; bottom: 20px; left: auto; top: auto; touch-action: none; user-select: none;'}
    onpointerdown={launcherDrag.pointerdown}
    onpointermove={launcherDrag.pointermove}
    onpointerup={launcherDrag.pointerup}
    onpointercancel={launcherDrag.pointercancel}
    onclick={(event) => {
        if (launcherDrag.clickInterceptor(event)) return;
        faqInquiryModalOpen = true;
        sound.pop();
    }}
>
    <span
        class="bg-primary/10 text-primary relative flex size-9 items-center justify-center rounded-full"
    >
        <HelpCircle class="size-4.5" />
        <span
            class="ring-card bg-primary absolute -top-0.5 -right-0.5 size-2 rounded-full ring-2"
        ></span>
    </span>
</button>

<PublicFaqInquiryModal bind:open={faqInquiryModalOpen} />
