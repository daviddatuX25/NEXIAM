<script lang="ts">
    import { getContext } from 'svelte';

    const page = getContext<{ url: string }>('walkthrough-test-page');
    let observedUrl = '';

    $effect(() => {
        const nextUrl = page.url;
        if (observedUrl !== '' && observedUrl !== nextUrl) {
            observedUrl = nextUrl;
            document.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
            return;
        }
        observedUrl = nextUrl;
    });
</script>

<div data-testid="transition-probe">{page.url}</div>
