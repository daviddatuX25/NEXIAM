<script lang="ts">
    import { onDestroy } from 'svelte';
    import OmrDiagnosticEvidencePanel from '@/Components/Omr/Diagnostics/OmrDiagnosticEvidencePanel.svelte';
    type EvidenceCallback = (...args: never[]) => unknown;
    type EvidenceCloseCallback = () => unknown;

    type OmrEvidenceDockPlacement = 'embedded' | 'focus' | 'mobile';

    type EvidenceDockProps = {
        page?: unknown;
        labels?: Record<string, string | undefined>;
        returnUrl?: string | null;
        loading?: boolean;
        error?: unknown;
        onRetry?: EvidenceCallback | null;
        onPageChange?: EvidenceCallback | null;
        onPerPageChange?: EvidenceCallback | null;
        onGroupChange?: EvidenceCallback | null;
        onFilterChange?: EvidenceCallback | null;
        onRowOpen?: EvidenceCallback | null;
        onTabChange?: EvidenceCallback | null;
        onViewChange?: EvidenceCallback | null;
        initialTab?: string;
        mobileRailExpanded?: boolean;
        contextSummary?: Record<string, unknown> | null;
        idPrefix?: string;
        reviewable?: boolean;
        blind?: boolean;
        reviewReasons?: unknown[];
        onDirtyChange?: EvidenceCallback | null;
        onDirtyNavigation?: EvidenceCallback | null;
        onSaveDecision?: EvidenceCallback | null;
        onSaveAndNext?: EvidenceCallback | null;
        onClose?: EvidenceCloseCallback | null;
        placement?: OmrEvidenceDockPlacement;
    };

    let {
        page = null,
        labels = {},
        returnUrl = null,
        loading = false,
        error = null,
        onRetry = null,
        onPageChange = null,
        onPerPageChange = null,
        onGroupChange = null,
        onFilterChange = null,
        onRowOpen = null,
        onTabChange = null,
        onViewChange = null,
        initialTab = 'aligned',
        mobileRailExpanded = $bindable(false),
        contextSummary = null,
        idPrefix = 'omr-evidence',
        reviewable = false,
        blind = false,
        reviewReasons = [],
        onDirtyChange = null,
        onDirtyNavigation = null,
        onSaveDecision = null,
        onSaveAndNext = null,
        onClose = null,
        placement = 'embedded',
    }: EvidenceDockProps = $props();

    let dialogEl = $state<HTMLElement | null>(null);
    let previousFocus: HTMLElement | null = null;

    const dockClasses = $derived.by(() => {
        if (placement === 'focus') {
            return 'fixed inset-0 z-50 flex min-h-0 items-stretch justify-center overflow-y-auto bg-slate-950/65 p-0 backdrop-blur-sm sm:items-center sm:p-4 md:p-6';
        }

        if (placement === 'mobile') {
            return 'sticky bottom-0 z-30 max-h-[52dvh] min-h-0 overflow-y-auto overscroll-contain rounded-t-xl border border-b-0 border-border bg-background p-2 shadow-2xl sm:p-3 lg:static lg:max-h-none lg:overflow-visible lg:rounded-xl lg:border-b lg:shadow-sm';
        }

        return 'min-w-0';
    });

    const panelClasses = $derived.by(() => {
        if (placement === 'focus') {
            return 'min-h-0 w-full max-w-[1600px] rounded-none border border-border bg-background p-2 shadow-2xl sm:max-h-[min(94dvh,1080px)] sm:overflow-y-auto sm:rounded-xl sm:p-4';
        }

        if (placement === 'mobile') {
            return 'min-w-0';
        }

        return 'min-w-0';
    });

    const isDialog = $derived(placement === 'focus');

    function focusableElements(): HTMLElement[] {
        if (!dialogEl) return [];

        return [
            ...dialogEl.querySelectorAll<HTMLElement>(
                'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [contenteditable="true"], [tabindex]:not([tabindex="-1"])',
            ),
        ].filter(
            (element) =>
                element instanceof HTMLElement && element.offsetParent !== null,
        );
    }

    function focusDialog(): void {
        if (!isDialog || !dialogEl) return;
        dialogEl.focus();
    }

    function restoreFocus(): void {
        const opener = previousFocus;
        previousFocus = null;

        if (
            typeof document === 'undefined' ||
            typeof HTMLElement === 'undefined'
        ) {
            return;
        }

        if (opener instanceof HTMLElement && document.contains(opener)) {
            opener.focus();
        }
    }

    function requestClose(): void {
        if (onClose) {
            onClose();
            return;
        }

        const closeButton = document.querySelector<HTMLElement>(
            '[data-omr-evidence-focus-close]',
        );
        closeButton?.click();
    }

    function onKeydown(event: KeyboardEvent): void {
        if (!isDialog || typeof document === 'undefined') return;

        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            requestClose();
            return;
        }

        if (event.key !== 'Tab') return;

        const elements = focusableElements();
        if (elements.length === 0) {
            event.preventDefault();
            dialogEl?.focus();
            return;
        }

        const first = elements[0];
        const last = elements.at(-1);
        const active = document.activeElement;

        if (active === dialogEl || !dialogEl?.contains(active)) {
            event.preventDefault();
            (event.shiftKey ? last : first)?.focus();
            return;
        }

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first?.focus();
        }
    }

    $effect(() => {
        if (!isDialog || typeof document === 'undefined') return;

        previousFocus =
            document.activeElement instanceof HTMLElement &&
            document.activeElement !== document.body
                ? document.activeElement
                : null;

        queueMicrotask(() => {
            if (isDialog) focusDialog();
        });
        window.addEventListener('keydown', onKeydown, true);

        return () => {
            window.removeEventListener('keydown', onKeydown, true);
            restoreFocus();
        };
    });

    onDestroy(restoreFocus);
</script>

<div
    bind:this={dialogEl}
    class={`omr-evidence-dock ${dockClasses}`}
    data-omr-evidence-dock
    data-omr-evidence-placement={placement}
    role={isDialog ? 'dialog' : 'region'}
    aria-modal={isDialog ? 'true' : undefined}
    aria-label={isDialog
        ? 'Focused OMR evidence review'
        : 'OMR evidence review'}
    tabindex="-1"
>
    <div class={panelClasses} data-omr-evidence-dock-panel>
        <OmrDiagnosticEvidencePanel
            {page}
            {labels}
            {returnUrl}
            {loading}
            {error}
            {onRetry}
            {onPageChange}
            {onPerPageChange}
            {onGroupChange}
            {onFilterChange}
            {onRowOpen}
            {onTabChange}
            {onViewChange}
            {initialTab}
            bind:mobileRailExpanded
            {contextSummary}
            {reviewable}
            {blind}
            {reviewReasons}
            {onDirtyChange}
            {onDirtyNavigation}
            {onSaveDecision}
            {onSaveAndNext}
            {idPrefix}
        />
    </div>
</div>

<style>
    @media (max-width: 420px) {
        [data-omr-evidence-dock] {
            gap: 0.5rem;
            padding-inline: 0.5rem;
        }

        [data-omr-evidence-dock-panel] {
            padding: 0.5rem;
        }
    }

    @media (min-width: 1024px) {
        [data-omr-evidence-placement='embedded'] {
            min-width: 0;
        }
    }
</style>
