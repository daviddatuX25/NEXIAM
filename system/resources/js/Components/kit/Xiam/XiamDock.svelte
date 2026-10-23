<script>
    /**
     * Shared Xiam dock adapter — mounts locked wrap-island chrome from server XiamSessionView only.
     * Client URL/capability arrays are never authority (OpenSpec ai-assistant-foundation).
     */
    import { usePage } from '@inertiajs/svelte';
    import XiamAssistPanel from '@/Components/kit/Xiam/XiamAssistPanel.svelte';
    import {
        xiamShouldMount,
        resolveXiamSession,
        XIAM_CONTRACT_VERSION,
    } from '@/types/xiam';

    const page = usePage();

    const session = $derived(resolveXiamSession(page.props.xiam));
    const mount = $derived(xiamShouldMount(session));
    const principalId = $derived(page.props.auth?.user?.id ?? null);

    const panelMode = $derived.by(() => {
        if (!session) {
            return 'disabled';
        }

        if (session.contract_version !== XIAM_CONTRACT_VERSION) {
            return 'degraded';
        }

        return session.availability;
    });


    const usage = $derived(session?.usage ?? null);
    const interactionModes = $derived(session?.interaction_modes ?? []);
</script>

{#if mount && session}
    {#key principalId}
    <XiamAssistPanel
        enabled={true}
        mode={panelMode}
        disclosure={session.disclosure?.text}
        defaultInteraction={session.active_mode ?? 'inquiry'}
        {interactionModes}
        {usage}
        {session}
    />
    {/key}
{/if}
