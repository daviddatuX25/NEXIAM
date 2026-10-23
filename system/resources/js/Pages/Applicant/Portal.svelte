<script>
  import { onMount } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import ApplicantHome from '@/Pages/Applicant/ApplicantHome.svelte';

  let {
    page_state = 'ready',
    identity = null,
    stages = [],
    pipeline = [],
    notices = {},
    quick_links = [],
    applicant_navigation = [],
  } = $props();

  const secondaryKeys = ['release_schedule', 'release_day', 'results', 'program_choice', 'admission_support', 'office_inquiries', 'access_history', 'policies', 'announcements'];
  const secondaryLinks = $derived((applicant_navigation ?? []).filter((item) => secondaryKeys.includes(item.key)));
  let selectionPending = $state(false);
  let selectionFailed = $state(false);
  let selectionAttemptedUrl = $state(null);
  let selectionFailedUrl = $state(null);

  onMount(() => {
    const removeStart = router.on('start', (event) => {
      const visit = event.detail.visit;
      if (visit?.poll || visit?.prefetch || (Array.isArray(visit?.only) && !visit.only.includes('page_state'))) {
        return;
      }
      selectionAttemptedUrl = visit.method === 'get' ? String(visit.url) : null;
      selectionFailedUrl = null;
      selectionPending = true;
      selectionFailed = false;
    });
    const removeFinish = router.on('finish', () => {
      selectionPending = false;
    });
    const fail = () => {
      selectionPending = false;
      selectionFailed = true;
      selectionFailedUrl = selectionAttemptedUrl;
    };
    const removeException = router.on('exception', fail);
    const removeHttpException = router.on('httpException', fail);
    return () => {
      removeStart();
      removeFinish();
      removeException();
      removeHttpException();
    };
  });

  function retrySelection() {
    if (!selectionFailedUrl) return;
    router.visit(selectionFailedUrl);
  }
</script>

<ApplicantLayout title="Your status">
  <div class="space-y-8">
    <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(16rem,30%)_minmax(0,1fr)] lg:gap-8">
      <section class="min-w-0" aria-label="Applicant Home">
        <ApplicantHome {page_state} {identity} {stages} {pipeline} {notices} {quick_links} {secondaryLinks} hideFooter={true} />
      </section>
      <aside class="hidden min-h-48 items-center justify-center rounded-xl border border-dashed border-border bg-card/40 p-8 text-center lg:flex" aria-label="Selected detail" aria-busy={selectionPending}>
        <div class="max-w-md space-y-2">
          {#if selectionFailed}
            <h2 class="text-lg font-semibold">Details unavailable</h2>
            <p class="text-sm text-muted-foreground">Your Home remains available. Retry loading the selected page.</p>
            {#if selectionFailedUrl}<button type="button" class="inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onclick={retrySelection}>Retry</button>{/if}
          {:else}
            <h2 class="text-lg font-semibold">Choose a milestone</h2>
            <p class="text-sm text-muted-foreground">Select a milestone in Your status to open its current details here. Your Home progress stays unchanged.</p>
          {/if}
        </div>
      </aside>
    </div>

    <div class="w-full space-y-6">
      <ApplicantHome {page_state} {identity} {stages} {pipeline} {notices} {quick_links} {secondaryLinks} onlyFooter={true} />
    </div>
  </div>
</ApplicantLayout>
