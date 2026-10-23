<script lang="ts">
    import { setContext, type Component } from 'svelte';
    import ApplicationRoot from '../../resources/js/Components/ApplicationRoot.svelte';

    let { Page }: { Page: Component } = $props();
    const page = $state({
        component: 'Walkthrough/TransitionProbe',
        url: '/walkthrough-transition-probe',
        props: {
            auth: { user: null, staff_inactivity_generation: null },
            csrf_token: 'csrf-before-transition',
            walkthroughProgress: null,
            practice_workspace: null,
        },
    });
    setContext('walkthrough-test-page', page);

    function navigateToNextPage(): void {
        page.component = 'Walkthrough/NextTransitionProbe';
        page.url = '/walkthrough-next-transition-probe';
    }
</script>

<button type="button" onclick={navigateToNextPage}>Navigate to next page</button>
<ApplicationRoot App={Page} appProps={{}} />
