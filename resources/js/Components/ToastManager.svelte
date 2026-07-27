<script>
    import { toasts, dismiss, success, error, info } from '@/lib/toast';
    import { fly, fade } from 'svelte/transition';
    import { page } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import { CheckCircle, XCircle, Info, X } from 'lucide-svelte';

    // Watch for Laravel flash messages and convert to toasts
    // page is a Svelte store - use .subscribe() to watch it
    onMount(() => {
        const unsubscribe = page.subscribe(p => {
            const flash = p.props?.flash;
            if (!flash) return;

            if (flash.success && !window._toastTriggered) {
                success(flash.success);
                window._toastTriggered = true;
                setTimeout(() => window._toastTriggered = false, 100);
            }
            if (flash.error && !window._toastErrorTriggered) {
                error(flash.error);
                window._toastErrorTriggered = true;
                setTimeout(() => window._toastErrorTriggered = false, 100);
            }
        });
        return unsubscribe;
    });
</script>

<!-- Toast container - fixed position -->
<div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 max-w-sm">
    {#each $toasts as toast (toast.id)}
        <div
            class="flex items-center gap-3 px-4 py-3 rounded-lg border shadow-xl backdrop-blur-sm transition-all duration-200 toast-{toast.type}"
            transition:fly={{ y: -20, duration: 200 }}
            role="alert"
        >
            <!-- Icon -->
            <div class="flex-shrink-0">
                {#if toast.type === 'success'}
                    <CheckCircle class="h-5 w-5 text-success" />
                {:else if toast.type === 'error'}
                    <XCircle class="h-5 w-5 text-destructive" />
                {:else}
                    <Info class="h-5 w-5 text-primary" />
                {/if}
            </div>

            <!-- Message -->
            <p class="flex-1 text-sm font-medium text-foreground">{toast.message}</p>

            <!-- Close button -->
            <button
                class="flex items-center justify-center w-7 h-7 rounded-md hover:bg-accent transition-colors flex-shrink-0"
                onclick={() => dismiss(toast.id)}
                title="Close"
            >
                <X class="h-4 w-4 text-muted-foreground" />
            </button>
        </div>
    {/each}
</div>

<style>
    .toast-success {
        background-color: color-mix(in srgb, var(--success) 10%, transparent);
        border-color: color-mix(in srgb, var(--success) 30%, transparent);
    }

    .toast-error {
        background-color: color-mix(in srgb, var(--destructive) 10%, transparent);
        border-color: color-mix(in srgb, var(--destructive) 30%, transparent);
    }

    .toast-info {
        background-color: color-mix(in srgb, var(--info) 10%, transparent);
        border-color: color-mix(in srgb, var(--info) 30%, transparent);
    }
</style>