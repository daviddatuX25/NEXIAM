<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { router } from '@inertiajs/svelte';
  import {
    FileText,
    BookOpen,
    Link2,
    Printer,
    Eye,
    AlertCircle,
    ArrowRight,
    Sparkles,
  } from 'lucide-svelte';
  import { error as toastError, success as toastSuccess } from '@/lib/toast.js';

  let {
    templates = [],
    booklets = [],
    pairs = [],
    print_jobs = [],
    readiness = { active_pair: false, draft_templates: false, draft_booklets: false, paper: 'long' },
    errors = {},
  } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration' },
  ];

  let confirmKey = $state(null);

  $effect(() => {
    if (errors.omr_retire) toastError(errors.omr_retire);
  });

  const draftTemplatesCount = $derived(
    templates.reduce((acc, t) => acc + (t.versions?.filter((v) => v.status === 'draft').length || 0), 0)
  );

  const draftBookletsCount = $derived(
    booklets.reduce((acc, b) => acc + (b.versions?.filter((v) => v.status === 'draft').length || 0), 0)
  );

  const activePairs = $derived(pairs.filter((p) => p.status === 'active'));

  function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? iso : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  function retire(kind, id) {
    router.post(
      '/setup/omr/retire',
      { kind, id },
      {
        preserveScroll: true,
        onSuccess: () => {
          confirmKey = null;
          toastSuccess('Configuration retired.');
        },
        onError: (e) => {
          confirmKey = null;
          toastError(e.omr_retire ?? 'Could not retire.');
        },
      }
    );
  }
</script>

<StaffLayout {breadcrumbs}>
  <PageHeaderBar
    title="OMR Configuration"
    description="Answer-sheet designs, answer keys, and exam scanning pairings."
  >
    {#snippet actions()}
      <Button href="/setup/omr/templates/builder" variant="outline" size="sm">Template builder</Button>
      <Button href="/setup/omr/booklets/create" variant="outline" size="sm">Author booklet</Button>
      <Button href="/setup/omr/pairs" size="sm">Activate pair</Button>
    {/snippet}
  </PageHeaderBar>

  <section class="space-y-8 p-6">
    {#if errors.omr_retire}
      <div class="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        <AlertCircle class="size-4 shrink-0" />
        <span>{errors.omr_retire}</span>
      </div>
    {/if}

    <!-- 1. The Two Main Doors -->
    <div class="grid gap-5 md:grid-cols-2">
      <!-- Door 1: Answer Sheets -->
      <div class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-5 transition-all hover:border-primary/50">
        <div>
          <div class="flex items-start justify-between">
            <div class="flex size-11 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
              <FileText class="size-6" />
            </div>
            <Badge variant="secondary" class="text-xs">
              {templates.length} {templates.length === 1 ? 'template' : 'templates'}
              {#if draftTemplatesCount > 0}
                <span class="ml-1 text-muted-foreground">· {draftTemplatesCount} draft</span>
              {/if}
            </Badge>
          </div>

          <h3 class="mt-4 text-lg font-semibold text-foreground">Answer-sheet templates</h3>
          <p class="mt-1 text-sm text-muted-foreground">
            Bubble-sheet designs with version history, printable text, and PDF previews.
          </p>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-4">
          <Button href="/setup/omr/templates/builder" variant="outline" size="xs">Builder</Button>
          <Button href="/setup/omr/templates" variant="ghost" size="xs">
            View templates ({templates.length})
            <ArrowRight class="ml-1 size-3" />
          </Button>
        </div>
      </div>

      <!-- Door 2: Question Booklets -->
      <div class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-5 transition-all hover:border-primary/50">
        <div>
          <div class="flex items-start justify-between">
            <div class="flex size-11 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
              <BookOpen class="size-6" />
            </div>
            <Badge variant="secondary" class="text-xs">
              {booklets.length} {booklets.length === 1 ? 'booklet' : 'booklets'}
              {#if draftBookletsCount > 0}
                <span class="ml-1 text-muted-foreground">· {draftBookletsCount} draft</span>
              {/if}
            </Badge>
          </div>

          <h3 class="mt-4 text-lg font-semibold text-foreground">Question booklets</h3>
          <p class="mt-1 text-sm text-muted-foreground">
            Answer keys with correct options (A–D) grouped by aptitude area.
          </p>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-4">
          <Button href="/setup/omr/booklets/create" variant="outline" size="xs">Author booklet</Button>
          <Button href="/setup/omr/booklets" variant="ghost" size="xs">
            View booklets ({booklets.length})
            <ArrowRight class="ml-1 size-3" />
          </Button>
        </div>
      </div>
    </div>

    <!-- 2. Visual Pairing Diagram -->
    <div class="rounded-xl border border-border/90 bg-card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-base font-semibold text-foreground">Active scanning pairs</h2>
            <Badge variant="secondary" class="font-mono text-xs">{activePairs.length} active</Badge>
          </div>
          <p class="mt-0.5 text-xs text-muted-foreground">
            What gets scanned and scored together. Each pair couples one template version with an answer booklet version.
          </p>
        </div>

        <Button href="/setup/omr/pairs" size="sm">Pair sheet & booklet</Button>
      </div>

      <div class="mt-6 space-y-4">
        {#if activePairs.length > 0}
          {#each activePairs as pair (pair.id)}
            <div class="rounded-xl border border-border/70 bg-muted/20 p-4">
              <div class="flex flex-col gap-3.5 md:flex-row md:items-center md:justify-between">
                <!-- Left: Answer Sheet Template -->
                <div class="flex min-w-0 flex-1 items-center gap-3 rounded-lg border border-border/60 bg-card p-3.5">
                  <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <FileText class="size-4.5" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <strong class="truncate text-sm font-semibold text-foreground">{pair.template_name}</strong>
                      <Badge variant="secondary" class="shrink-0 text-[10px]">v{pair.template_version}</Badge>
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                      <span class="font-mono">{pair.template_code}</span>
                      <span>·</span>
                      <span>{pair.page_width}×{pair.page_height}</span>
                    </div>
                  </div>
                  {#if pair.template_version_id}
                    <Button
                      href={`/setup/omr/templates/${pair.template_version_id}/preview`}
                      variant="ghost"
                      size="icon-xs"
                      class="shrink-0 text-muted-foreground"
                      title="Preview sheet PDF"
                      target="_blank"
                      rel="noopener"
                    >
                      <Eye class="size-3.5" />
                    </Button>
                  {/if}
                </div>

                <!-- Center: Connector -->
                <div class="flex shrink-0 items-center justify-center">
                  <div class="flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    <Link2 class="size-3.5" />
                    <span>Paired</span>
                  </div>
                </div>

                <!-- Right: Question Booklet -->
                <div class="flex min-w-0 flex-1 items-center gap-3 rounded-lg border border-border/60 bg-card p-3.5">
                  <div class="flex size-9 shrink-0 items-center justify-center rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400">
                    <BookOpen class="size-4.5" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                      <strong class="truncate text-sm font-semibold text-foreground">{pair.booklet_name}</strong>
                      <Badge variant="secondary" class="shrink-0 text-[10px]">v{pair.booklet_version}</Badge>
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                      <span class="font-mono">{pair.booklet_code}</span>
                      <span>·</span>
                      <span>Answer key active</span>
                    </div>
                  </div>
                  {#if pair.booklet_id}
                    <Button href={`/setup/omr/booklets/${pair.booklet_id}`} variant="outline" size="xs" class="shrink-0">
                      View key
                    </Button>
                  {/if}
                </div>

                <!-- Actions -->
                <div class="flex shrink-0 items-center justify-end">
                  <Button
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground hover:text-destructive"
                    onclick={() => (confirmKey = `pair-${pair.id}`)}
                  >
                    Unpair
                  </Button>
                </div>
              </div>

              {#if confirmKey === `pair-${pair.id}`}
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs text-destructive">
                  <span>Retire this active pair? Open grading sessions keep their pinned pair; new sessions cannot select it.</span>
                  <div class="flex items-center gap-2">
                    <Button variant="destructive" size="xs" onclick={() => retire('pair', pair.id)}>Confirm retire</Button>
                    <Button variant="outline" size="xs" onclick={() => (confirmKey = null)}>Cancel</Button>
                  </div>
                </div>
              {/if}
            </div>
          {/each}
        {:else}
          <div class="rounded-xl border border-dashed border-border/80 p-8 text-center">
            <Link2 class="mx-auto size-8 text-muted-foreground" />
            <p class="mt-2 text-sm font-medium text-foreground">No active scanning pairs</p>
            <p class="mt-1 text-xs text-muted-foreground">
              Pair an answer-sheet template with a question booklet to enable OMR scoring.
            </p>
            <Button href="/setup/omr/pairs" size="sm" class="mt-4">Activate a pair now</Button>
          </div>
        {/if}
      </div>

      <!-- Ready to Pair -->
      {#if draftTemplatesCount > 0 || draftBookletsCount > 0}
        <div class="mt-5 rounded-lg border border-dashed border-amber-500/40 bg-amber-500/5 p-3.5 text-xs text-muted-foreground">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <Sparkles class="size-4 text-amber-600 dark:text-amber-400" />
              <span>
                <strong>Unpaired drafts available:</strong>
                {#if draftTemplatesCount > 0}{draftTemplatesCount} draft template(s){/if}
                {#if draftTemplatesCount > 0 && draftBookletsCount > 0} and {/if}
                {#if draftBookletsCount > 0}{draftBookletsCount} draft booklet(s){/if}.
              </span>
            </div>
            <Button href="/setup/omr/pairs" variant="outline" size="xs">Pair drafts now</Button>
          </div>
        </div>
      {/if}
    </div>

    <!-- 3. Printing Queue Bar -->
    <div class="rounded-xl border border-border/70 bg-card p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
            <Printer class="size-4.5" />
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-sm font-semibold text-foreground">Blank-sheet printing</h3>
              {#if print_jobs.length > 0}
                <Badge variant="secondary" class="text-[10px]">{print_jobs.length} recent</Badge>
              {/if}
            </div>
            <p class="text-xs text-muted-foreground">
              Rendered on configured paper ({readiness.paper}) with millimeter precision.
            </p>
          </div>
        </div>

        {#if print_jobs.length > 0}
          <div class="flex flex-wrap items-center gap-2">
            {#each print_jobs.slice(0, 3) as job (job.id)}
              <div class="flex items-center gap-2 rounded-md border border-border/60 bg-muted/40 px-2.5 py-1 text-xs">
                <span class="font-mono text-[11px] text-muted-foreground">#{job.id}</span>
                <Badge variant={job.status === 'ready' ? 'default' : 'secondary'} class="text-[10px]">{job.status}</Badge>
                <span class="text-[11px] text-muted-foreground">{formatDate(job.created_at)}</span>
                {#if job.status === 'ready'}
                  <a href={`/printing/jobs/${job.id}/download`} class="font-medium text-primary hover:underline">PDF</a>
                {/if}
              </div>
            {/each}
          </div>
        {/if}
      </div>
    </div>
  </section>
</StaffLayout>
