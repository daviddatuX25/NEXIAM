<script>
    import { Settings2 } from 'lucide-svelte';
    let {
        setup_links = null,
        can_manage_catalog = false,
        onNavigate = null,
    } = $props();
    const url = $derived(setup_links?.test_kits || '/setup/exam-materials/test-kits');
    
    function navigate(event) {
        if (!onNavigate || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        
        event.preventDefault();
        onNavigate({ url, label: 'Test Setup' });
    }
    
</script>
{#if can_manage_catalog}
    <a
        href={url}
        class="inline-flex min-h-9 items-center gap-1.5 rounded-md border border-input bg-background px-3 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        onclick={navigate}
    >
        <Settings2 class="size-4" aria-hidden="true" />
        <span>Test Setup</span>
    </a>
{/if}
