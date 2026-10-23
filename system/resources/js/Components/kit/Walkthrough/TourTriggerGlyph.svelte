<script lang="ts">
  import { onMount } from 'svelte';
  import HelpCircle from 'lucide-svelte/icons/help-circle';
  import { getPageGuideCoverage } from '@/lib/walkthrough/page-guides';
  import { usePage } from '@inertiajs/svelte';
  import { getTour } from '@/lib/walkthrough/tours';
  import {
    anonymousWalkthroughScope,
    isWalkthroughExplored,
    readWalkthroughProgress,
    type WalkthroughProgressRecord,
    type WalkthroughScope,
  } from '@/lib/walkthrough/preferences';
  import {
    createWalkthroughDialogId,
    walkthroughCoordinator,
    walkthroughDialogActive,
    type WalkthroughProgressEnvelope,
  } from '@/lib/walkthrough/coordinator';

  let {
    tourKey,
    onclick,
    label = 'Xiam Guide & Tour',
    revision: requestedRevision = undefined,
  }: {
    tourKey: string;
    onclick: () => void;
    label?: string;
    revision?: number;
  } = $props();
  let button = $state<HTMLButtonElement | null>(null);
  let page: any = undefined;
  try {
    page = usePage();
  } catch {
    // The glyph can still open its local tour outside an Inertia-mounted page.
  }
  const triggerId = createWalkthroughDialogId();
  const progressEnvelope = $derived(
    page?.props?.walkthroughProgress as WalkthroughProgressEnvelope | null | undefined,
  );
  const scope: WalkthroughScope = $derived(
    progressEnvelope?.scope ?? anonymousWalkthroughScope(),
  );
  const serverRecords: WalkthroughProgressRecord[] = $derived(
    progressEnvelope?.records ?? [],
  );
  const pageCoverage = $derived(
    page ? getPageGuideCoverage(page.component, page.url) : undefined,
  );
  const progressPageKey = $derived(
    page && pageCoverage?.tourKey === tourKey
      ? `page:${pageCoverage.componentName}`
      : `tour:${tourKey}`,
  );
  let discoveredRevision = $state(requestedRevision ?? 1);
  let coordinatorRevisionVersion = $state(0);
  onMount(() => {
    const unsubscribe = walkthroughCoordinator.subscribe(() => {
      coordinatorRevisionVersion += 1;
    });
    if (requestedRevision === undefined) {
      try {
        discoveredRevision = getTour(tourKey)?.revision ?? 1;
      } catch {
        discoveredRevision = 1;
      }
    }
    return unsubscribe;
  });
  const currentRevision = $derived.by(() => {
    coordinatorRevisionVersion;
    return walkthroughCoordinator.currentRevision(
      progressPageKey,
      requestedRevision ?? discoveredRevision,
    );
  });
  const progress = $derived.by(() => {
    coordinatorRevisionVersion;
    return readWalkthroughProgress(scope, progressPageKey, currentRevision)
      ?? serverRecords.find((record) => (
        record.pageKey === progressPageKey && record.revision === currentRevision
      ))
      ?? null;
  });
  const explored = $derived.by(() => {
    coordinatorRevisionVersion;
    return isWalkthroughExplored(scope, progressPageKey, currentRevision, serverRecords);
  });

  $effect(() => {
    if (!page || !button) {
      return;
    }
    return walkthroughCoordinator.registerTrigger({
      id: triggerId,
      tourKey,
      componentName: page.component,
      url: page.url,
      element: button,
      open: () => onclick(),
    });
  });

  function openTour(): void {
    if (walkthroughDialogActive()) {
      return;
    }
    walkthroughCoordinator.beginManual(progressPageKey, currentRevision, tourKey);
    onclick();
  }
</script>

<button
  bind:this={button}
  type="button"
  class="relative inline-flex size-8 shrink-0 items-center justify-center rounded-full text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 {explored ? 'opacity-35 transition-opacity hover:opacity-100' : progress ? 'opacity-90' : 'tour-ambient-wiggle tour-first-shake'}"
  aria-label={label}
  title={label}
  onclick={openTour}
>
  <HelpCircle class="size-4.5" />
  {#if !explored}
    <span aria-hidden="true" class="absolute right-0.5 top-0.5 size-2 rounded-full bg-primary ring-2 ring-background"></span>
  {/if}
</button>

<style>
  .tour-ambient-wiggle {
    animation: ambient-wiggle 12s ease-in-out infinite;
    transform-origin: center;
  }

  .tour-first-shake {
    animation: first-shake 560ms ease-in-out 1, ambient-wiggle 12s ease-in-out 560ms infinite;
  }

  @keyframes ambient-wiggle {
    0%, 8.333% { transform: rotate(0deg); }
    2.778% { transform: rotate(-8deg); }
    5.556% { transform: rotate(8deg); }
    100% { transform: rotate(0deg); }
  }

  @keyframes first-shake {
    0%, 100% { transform: translateX(0) rotate(0); }
    20%, 60% { transform: translateX(-2px) rotate(-8deg); }
    40%, 80% { transform: translateX(2px) rotate(8deg); }
  }

  @media (prefers-reduced-motion: reduce) {
    .tour-ambient-wiggle,
    .tour-first-shake { animation: none; }
  }
</style>
