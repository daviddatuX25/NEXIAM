<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
  import ModeBanner from '@/Components/kit/ModeBanner.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';
  import * as Table from '@/Components/ui/table';
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { Eye, Layers, Plus } from 'lucide-svelte';

  let {
    page_state = 'ready',
    sessions = [],
    candidate_exam_sessions = [],
    aptitude_areas_count = 0,
    degraded = false,
  } = $props();

  const page = usePage();
  const openError = $derived(page.props.errors?.open ?? '');

  let viewMode = $state('responsive');
  let openDialog = $state(false);
  let selectedSessionId = $state('');
  let opening = $state(false);

  function statusVariant(status) {
    return status === 'finalized' ? 'success' : 'warning';
  }

  function statusLabel(status) {
    if (status === 'finalized') return 'Finalized';
    if (status === 'review') return 'In review';
    return 'In progress';
  }

  function submitOpen() {
    if (!selectedSessionId || opening) return;
    opening = true;
    router.post('/grading', { exam_session_id: Number(selectedSessionId) }, {
      onError: () => {
        opening = false;
      },
      onSuccess: () => {
        opening = false;
        openDialog = false;
        selectedSessionId = '';
      },
    });
  }
</script>

<svelte:head><title>Scoring</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Scoring' }]}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if degraded}
      <ModeBanner variant="warning" title="Manual scoring mode">
        Inference provider unavailable; manual entry remains authoritative.
      </ModeBanner>
    {/if}

    {#if page_state === 'denied'}
      <p class="text-sm text-muted-foreground">You do not have permission to access scoring.</p>
    {:else if aptitude_areas_count === 0}
      <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 p-6">
        <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400">
          <Layers class="size-5" />
          <h2 class="text-lg font-semibold">No aptitude areas configured</h2>
        </div>
        <p class="mt-1 text-sm text-muted-foreground">
          Aptitude areas must be set up before you can open scoring sessions.
        </p>
      </div>
    {:else}
      <PageHeaderBar
        title="Scoring"
        description="Open sessions where scores are being or have been entered."
      >
        {#snippet actions()}
          <Button
            class="min-h-10 gap-2"
            onclick={() => (openDialog = true)}
            disabled={candidate_exam_sessions.length === 0}
              title={
                candidate_exam_sessions.length === 0
                  ? 'No exam sessions left to open'
                  : 'Open scoring session'
              }
            >
            <Plus class="size-4" />
            Open scoring session
          </Button>
        {/snippet}
      </PageHeaderBar>

      <SwitchableListView bind:viewMode overflow="auto">
        {#snippet table()}
          <Table.Root class="w-full min-w-[560px] text-sm">
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-4 py-3">Session</Table.Head>
                <Table.Head class="px-4 py-3">When</Table.Head>
                <Table.Head class="px-4 py-3">Progress</Table.Head>
                <Table.Head class="px-4 py-3">Status</Table.Head>
                <Table.Head class="px-4 py-3 text-center">Action</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each sessions as gs (gs.id)}
                <Table.Row class="border-t border-border hover:bg-muted/30">
                  <Table.Cell class="px-4 py-3 font-medium">
                    {gs.exam_code} — {gs.room_name}
                  </Table.Cell>
                  <Table.Cell class="px-4 py-3 text-muted-foreground">{gs.starts_at ?? '—'}</Table.Cell>
                  <Table.Cell class="px-4 py-3">
                    <Badge variant={statusVariant(gs.status)}>
                      {gs.applicants_scored ?? 0} of {gs.applicants_total ?? 0} scored
                    </Badge>
                    {#if (gs.applicants_absent ?? 0) > 0}
                      <span class="ml-2 text-xs text-muted-foreground">
                        {(gs.applicants_absent ?? 0)} absent excluded
                      </span>
                    {/if}
                  </Table.Cell>
                  <Table.Cell class="px-4 py-3">
                    <Badge variant={statusVariant(gs.status)}>{statusLabel(gs.status)}</Badge>
                  </Table.Cell>
                  <Table.Cell class="px-4 py-3 text-center">
                    <Link href={`/grading/sessions/${gs.id}`}>
                      <Button variant="outline" size="sm" class="h-8 px-2 text-xs font-semibold">
                        {gs.status === 'finalized' ? 'View' : 'Input scores'}
                      </Button>
                    </Link>
                  </Table.Cell>
                </Table.Row>
              {:else}
                <Table.Row>
                  <Table.Cell colspan={5} class="px-4 py-12 text-center text-muted-foreground">
                    No grading sessions yet. Open one for a completed exam session.
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        {/snippet}

        {#snippet cards()}
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {#each sessions as gs (gs.id)}
              <div class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="truncate font-semibold tracking-tight">{gs.exam_code} — {gs.room_name}</p>
                    <p class="text-sm text-muted-foreground">{gs.starts_at ?? '—'}</p>
                  </div>
                  <Badge variant={statusVariant(gs.status)}>{statusLabel(gs.status)}</Badge>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  <Badge variant={statusVariant(gs.status)} class="w-fit">
                    {gs.applicants_scored ?? 0} of {gs.applicants_total ?? 0} scored
                  </Badge>
                  {#if (gs.applicants_absent ?? 0) > 0}
                    <span class="text-xs text-muted-foreground">
                      {(gs.applicants_absent ?? 0)} absent excluded
                    </span>
                  {/if}
                </div>
                <div class="mt-auto pt-1">
                  <Link href={`/grading/sessions/${gs.id}`} class="w-full">
                    <Button variant="outline" size="sm" class="min-h-10 w-full font-semibold">
                      <Eye class="mr-1.5 size-3.5" />
                      {gs.status === 'finalized' ? 'View' : 'Input scores'}
                    </Button>
                  </Link>
                </div>
              </div>
            {:else}
              <div class="col-span-full rounded-xl border border-dashed border-border bg-muted/20 py-12 text-center text-muted-foreground">
                No grading sessions yet.
              </div>
            {/each}
          </div>
        {/snippet}
      </SwitchableListView>
    {/if}
  </div>

  <Dialog.Root bind:open={openDialog}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
          <Dialog.Title>Open scoring session</Dialog.Title>
          <Dialog.Description>
            Choose an exam session to start entering scores. Each exam session has one scoring session.
          </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-2">
        {#if openError}
          <div class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive">
            {openError}
          </div>
        {/if}
        <label class="block text-sm font-medium" for="open-grading-session">Exam session</label>
        <select
          id="open-grading-session"
          class="flex min-h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
          bind:value={selectedSessionId}
        >
          <option value="">Select an exam session…</option>
          {#each candidate_exam_sessions as candidate (candidate.id)}
            <option value={String(candidate.id)}>
              {candidate.code} · {candidate.starts_at} · {candidate.room_name}
            </option>
          {/each}
        </select>
        {#if candidate_exam_sessions.length === 0}
          <p class="text-xs text-muted-foreground">
            No exam sessions left to open. Closing an exam session opens its grading session
            automatically.
          </p>
        {/if}
      </div>
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => (openDialog = false)}>Cancel</Button>
        <Button
          type="button"
          disabled={!selectedSessionId || opening || candidate_exam_sessions.length === 0}
          onclick={submitOpen}
        >
          {opening ? 'Opening…' : 'Open session'}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</StaffLayout>
