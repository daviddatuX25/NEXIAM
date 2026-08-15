<script>
  /**
   * Shared Nexi dock adapter — mounts locked wrap-island chrome from server NexiSessionView only.
   * Client URL/capability arrays are never authority (OpenSpec ai-assistant-foundation).
   */
  import { usePage } from '@inertiajs/svelte';
  import NexiAssistPanel from '@/Components/kit/Nexi/NexiAssistPanel.svelte';
  import {
    nexiShouldMount,
    resolveNexiSession,
    NEXI_CONTRACT_VERSION,
  } from '@/types/nexi';

  const page = usePage();

  const session = $derived(resolveNexiSession(page.props.nexi));
  const mount = $derived(nexiShouldMount(session));

  const panelMode = $derived.by(() => {
    if (!session) {
      return 'disabled';
    }

    if (session.contract_version !== NEXI_CONTRACT_VERSION) {
      return 'degraded';
    }

    return 'degraded';
  });

  const interactionModes = $derived(
    (session?.modes ?? ['inquiry']).map((key) => ({
      key,
      label: key === 'inquiry' ? 'Inquiry' : 'Assistant',
    })),
  );

  const usage = $derived({
    used: session?.usage?.used ?? 0,
    limit: session?.usage?.limit ?? 0,
    unit: session?.usage?.unit ?? 'asks',
    period: 'policy',
  });
</script>

{#if mount && session}
  <NexiAssistPanel
    enabled={true}
    mode={panelMode}
    disclosure={session.disclosure?.text}
    defaultInteraction={session.active_mode ?? 'inquiry'}
    interactionModes={interactionModes}
    usage={usage}
    session={session}
  />
{/if}
