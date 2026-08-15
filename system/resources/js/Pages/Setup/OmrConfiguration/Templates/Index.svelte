<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { FileText, ArrowRight, Eye } from 'lucide-svelte';
  import { error as toastError } from '@/lib/toast.js';

  let { templates = [], errors = {} } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: 'Answer sheets' },
  ];

  $effect(() => {
    if (errors.omr_retire) toastError(errors.omr_retire);
  });

  function latestVersion(template) {
    return template.versions?.[0] ?? null;
  }
</script>

<StaffLayout {breadcrumbs}>
  <PageHeaderBar
    title="Answer-sheet templates"
    description="Bubble-sheet designs used for OMR scanning. Each template keeps an immutable version history."
  >
    {#snippet actions()}
      <Button href="/setup/omr/templates/builder" size="sm">New template</Button>
      <Button href="/setup/omr/intake" variant="outline" size="sm">Register bundle</Button>
    {/snippet}
  </PageHeaderBar>

  <section class="space-y-6 p-6">
    <div class="grid gap-4 sm:grid-cols-2">
      {#each templates as template (template.id)}
        {@const latest = latestVersion(template)}
        {@const drafts = template.versions?.filter((v) => v.status === 'draft') ?? []}
        {@const activeVer = template.versions?.find((v) => v.status === 'active') ?? null}

        <div class="flex flex-col justify-between rounded-xl border border-border/80 bg-card p-5 transition-all hover:border-primary/50">
          <div>
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                  <FileText class="size-5" />
                </div>
                <div>
                  <h3 class="text-sm font-semibold text-foreground">{template.name}</h3>
                  <p class="font-mono text-xs text-muted-foreground">{template.code}</p>
                </div>
              </div>

              {#if activeVer}
                <Badge variant="default" class="text-[10px]">Active v{activeVer.version}</Badge>
              {:else if drafts.length > 0}
                <Badge variant="secondary" class="text-[10px]">Draft v{drafts[0].version}</Badge>
              {/if}
            </div>

            <div class="mt-4 space-y-2 rounded-lg border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">
              <div class="flex items-center justify-between">
                <span>Total versions:</span>
                <span class="font-medium text-foreground">{template.versions?.length ?? 0}</span>
              </div>
              {#if latest}
                <div class="flex items-center justify-between">
                  <span>Latest sheet (v{latest.version}):</span>
                  <span class="font-medium text-foreground">{latest.page_count} page{latest.page_count === 1 ? '' : 's'} · {latest.page_width}×{latest.page_height}</span>
                </div>
              {/if}
            </div>
          </div>

          <div class="mt-5 flex items-center justify-between border-t border-border/60 pt-3.5">
            <Button href={`/setup/omr/templates/${template.id}`} variant="outline" size="xs">
              <Eye class="mr-1 size-3" />
              View versions
            </Button>

            {#if drafts.length > 0}
              <Button href={`/setup/omr/templates/builder?version=${drafts[0].id}`} size="xs">
                Edit draft v{drafts[0].version}
              </Button>
            {/if}
          </div>
        </div>
      {/each}

      {#if templates.length === 0}
        <div class="col-span-2 rounded-xl border border-dashed border-border/80 p-10 text-center">
          <FileText class="mx-auto size-9 text-muted-foreground" />
          <h3 class="mt-2 text-sm font-semibold text-foreground">No answer-sheet templates yet</h3>
          <p class="mt-1 text-xs text-muted-foreground">
            Design a bubble sheet in the visual builder or register an existing native bundle.
          </p>
          <div class="mt-4 flex items-center justify-center gap-2">
            <Button href="/setup/omr/templates/builder" size="sm">Open builder</Button>
            <Button href="/setup/omr/intake" variant="outline" size="sm">Register bundle</Button>
          </div>
        </div>
      {/if}
    </div>
  </section>
</StaffLayout>
