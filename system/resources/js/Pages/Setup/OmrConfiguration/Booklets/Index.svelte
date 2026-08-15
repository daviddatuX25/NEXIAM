<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Link, router } from '@inertiajs/svelte';
  import { BookOpen, Plus, ArrowRight, Eye, AlertCircle, Trash2 } from 'lucide-svelte';
  import { success as toastSuccess, error as toastError } from '@/lib/toast.js';

  let { booklets = [], areas = [], errors = {} } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: 'Answer booklets' },
  ];

  let confirmKey = $state(null);

  $effect(() => {
    if (errors.omr_retire) toastError(errors.omr_retire);
  });

  function latestVersion(booklet) {
    return booklet.versions?.[0] ?? null;
  }

  function retire(kind, id) {
    router.post(
      '/setup/omr/retire',
      { kind, id },
      {
        preserveScroll: true,
        onSuccess: () => {
          confirmKey = null;
          toastSuccess('Booklet version retired.');
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
    title="Answer booklets"
    description="Question booklets with correct answer keys authored directly in NEXIAM, ready to pair with answer-sheet templates."
  >
    {#snippet actions()}
      <Button href="/setup/omr/booklets/create" size="sm">Author new booklet</Button>
    {/snippet}
  </PageHeaderBar>

  <section class="space-y-6 p-6">
    {#if errors.omr_retire}
      <div class="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        <AlertCircle class="size-4 shrink-0" />
        <span>{errors.omr_retire}</span>
      </div>
    {/if}

    <div class="grid gap-4 sm:grid-cols-2">
      {#each booklets as booklet (booklet.id)}
        {@const latest = latestVersion(booklet)}
        {@const drafts = booklet.versions?.filter((v) => v.status === 'draft') ?? []}
        {@const activeVer = booklet.versions?.find((v) => v.status === 'active') ?? null}

        <div class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-5 shadow-xs transition-all hover:border-primary/50 hover:shadow-xs">
          <div>
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                  <BookOpen class="size-5" />
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-foreground">
                    <Link href={`/setup/omr/booklets/${booklet.id}`} class="hover:text-primary hover:underline">
                      {booklet.name}
                    </Link>
                  </h3>
                  <p class="font-mono text-xs text-muted-foreground">{booklet.code}</p>
                </div>
              </div>

              {#if activeVer}
                <Badge variant="default" class="text-[10px]">Active v{activeVer.version}</Badge>
              {:else if drafts.length > 0}
                <Badge variant="secondary" class="text-[10px]">Draft v{drafts[0].version}</Badge>
              {/if}
            </div>

            <!-- Version summary info -->
            <div class="mt-4 space-y-2 rounded-lg border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">
              <div class="flex items-center justify-between">
                <span>Total versions:</span>
                <span class="font-medium text-foreground">{booklet.versions?.length ?? 0}</span>
              </div>
              {#if latest}
                <div class="flex items-center justify-between">
                  <span>Questions in v{latest.version}:</span>
                  <span class="font-medium text-foreground">{latest.questions_count ?? 0} questions</span>
                </div>
                <div class="flex items-center justify-between font-mono text-[11px]">
                  <span>Key digest:</span>
                  <span title={latest.answer_key_hash ?? ''}>
                    {(latest.answer_key_hash ?? '').slice(0, 12)}…
                  </span>
                </div>
              {/if}
            </div>
          </div>

          <!-- Bottom Actions -->
          <div class="mt-5 flex items-center justify-between border-t border-border/60 pt-3.5">
            <Button href={`/setup/omr/booklets/${booklet.id}`} variant="outline" size="xs">
              View key & versions
            </Button>

            {#if drafts.length > 0}
              <Button href={`/setup/omr/booklets/create?draft=${drafts[0].id}`} size="xs">
                Edit draft v{drafts[0].version}
              </Button>
            {/if}
          </div>
        </div>
      {/each}

      {#if booklets.length === 0}
        <div class="col-span-2 rounded-xl border border-dashed border-border/80 p-10 text-center">
          <BookOpen class="mx-auto size-9 text-muted-foreground" />
          <h3 class="mt-2 text-sm font-semibold text-foreground">No question booklets yet</h3>
          <p class="mt-1 text-xs text-muted-foreground">
            Author an answer booklet with aptitude area questions to pair with answer sheets.
          </p>
          <Button href="/setup/omr/booklets/create" size="sm" class="mt-4">Author first booklet</Button>
        </div>
      {/if}
    </div>
  </section>
</StaffLayout>
