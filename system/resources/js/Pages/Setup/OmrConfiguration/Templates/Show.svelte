<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import { Link, router } from '@inertiajs/svelte';
  import {
    FileText,
    Eye,
    Plus,
    Copy,
    Edit3,
    Trash2,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    AlertCircle,
    Sliders,
    Sparkles
  } from 'lucide-svelte';
  import { error as toastError, success as toastSuccess } from '@/lib/toast.js';

  let { template = {}, pairs = [], capabilities = {}, errors = {} } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: template.code ?? 'Template' },
  ];

  let serverError = $state('');

  $effect(() => {
    const first = Object.values(errors)[0];
    if (first && first !== serverError) {
      serverError = first;
      toastError(first);
    }
  });

  function statusVariant(status) {
    if (status === 'active') return 'default';
    if (status === 'draft') return 'secondary';
    return 'outline';
  }

  function shortHash(value) {
    return value ? value.slice(0, 12) : '—';
  }

  // Inline retire confirmation
  let confirming = $state(null);
  let retiring = $state(false);
  let copyingVersionId = $state(null);

  function cascadePairs(version) {
    const ids = new Set(version.pair_ids ?? []);
    return pairs.filter((pair) => ids.has(pair.id));
  }

  function requestRetire(kind, id, label, cascade = []) {
    confirming = { kind, id, label, pairs: cascade };
  }

  function confirmRetire() {
    if (!confirming || retiring) return;
    retiring = true;
    router.post(
      '/setup/omr/retire',
      { kind: confirming.kind, id: confirming.id },
      {
        preserveScroll: true,
        onSuccess: () => {
          toastSuccess(`${confirming.label} retired.`);
          confirming = null;
          retiring = false;
        },
        onError: (errs) => {
          retiring = false;
          toastError(errs.omr_retire ?? 'Could not retire.');
        },
      }
    );
  }

  // Copy active version to new draft
  function copyToNewDraft(version) {
    if (copyingVersionId) return;
    copyingVersionId = version.id;
    router.post(
      `/setup/omr/templates/${version.id}/overlay`,
      {
        version_id: version.id,
        overlay: version.print_overlay_json ?? {},
      },
      {
        onFinish: () => {
          copyingVersionId = null;
        },
      }
    );
  }

  // Sheet overlay text editor
  let sheetOpenVersionId = $state(null);
  let sheetTitle = $state('');
  let sheetNameBoxLabel = $state('');
  let sheetHeaderLines = $state('');
  let sheetFooterLines = $state('');
  let sheetPerPageNotes = $state('');
  let sheetSaving = $state(false);

  // Technical hashes collapse toggle state
  let showTechDetails = $state({});

  function toggleTech(versionId) {
    showTechDetails[versionId] = !showTechDetails[versionId];
  }

  function toggleSheetEditor(version) {
    if (sheetOpenVersionId === version.id) {
      sheetOpenVersionId = null;
      return;
    }
    sheetOpenVersionId = version.id;
    const ov = version.print_overlay_json ?? {};
    sheetTitle = ov.title ?? '';
    sheetNameBoxLabel = ov.name_box_label ?? '';
    sheetHeaderLines = (ov.header_lines ?? []).join('\n');
    sheetFooterLines = (ov.footer_lines ?? []).join('\n');
    sheetPerPageNotes = (ov.per_page ?? [])
      .map((p) => `${p.page_no}: ${p.note}`)
      .join('\n');
  }

  function saveSheet(version) {
    if (sheetSaving) return;
    sheetSaving = true;

    const overlay = {
      title: sheetTitle.trim(),
      name_box_label: sheetNameBoxLabel.trim(),
      header_lines: sheetHeaderLines
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean),
      footer_lines: sheetFooterLines
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean),
      per_page: sheetPerPageNotes
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean)
        .map((l) => {
          const match = l.match(/^(\d+)\s*:\s*(.+)$/);
          return match ? { page_no: Number(match[1]), note: match[2].trim() } : null;
        })
        .filter(Boolean),
    };

    router.post(
      `/setup/omr/templates/${version.id}/overlay`,
      { version_id: version.id, overlay },
      {
        preserveScroll: true,
        onSuccess: () => {
          toastSuccess('Sheet text saved.');
          sheetOpenVersionId = null;
          router.reload({ only: ['template'] });
        },
        onError: (errs) => {
          toastError(errs.omr_overlay ?? 'Could not save sheet text.');
        },
        onFinish: () => {
          sheetSaving = false;
        },
      }
    );
  }

  const versions = $derived(template.versions ?? []);
  const activeVersion = $derived(versions.find((v) => v.status === 'active') ?? null);
  const draftVersions = $derived(versions.filter((v) => v.status === 'draft') ?? []);
  const retiredVersions = $derived(versions.filter((v) => v.status === 'retired') ?? []);
</script>

<StaffLayout {breadcrumbs}>
  <PageHeaderBar
    title={template.name ?? 'Template'}
    description={`${template.code ?? ''}${template.is_synthetic ? ' · synthetic template' : ''}`}
  >
    {#snippet actions()}
      <Button asChild size="sm">
        <Link href="/setup/omr/templates/builder">Template builder</Link>
      </Button>
    {/snippet}
  </PageHeaderBar>

  <section class="space-y-6 p-6">
    {#if serverError}
      <div class="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
        <AlertCircle class="size-4 shrink-0" />
        <span>{serverError}</span>
      </div>
    {/if}

    <!-- Main Versions Management -->
    <div>
      <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-foreground">Template versions</h2>
        <Badge variant="secondary" class="text-xs">{versions.length} total</Badge>
      </div>

      {#if versions.length === 0}
        <div class="mt-3 rounded-xl border border-dashed border-border/80 p-8 text-center text-sm text-muted-foreground">
          No versions registered for this template yet.
        </div>
      {:else}
        <div class="mt-3 space-y-4">
          {#each versions as version (version.id)}
            {@const isDraft = version.status === 'draft'}
            {@const isActive = version.status === 'active'}
            {@const cascade = cascadePairs(version)}

            <div class="rounded-xl border {isActive ? 'border-primary/40 bg-card shadow-xs' : 'border-border/80 bg-card'} p-5 transition-all">
              <!-- Header Row -->
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                  <span class="font-mono text-base font-bold text-foreground">v{version.version}</span>
                  <Badge variant={statusVariant(version.status)} class="capitalize text-xs">
                    {version.status}
                  </Badge>
                  <span class="text-xs text-muted-foreground">
                    {version.page_width}×{version.page_height} px · {version.page_count ?? 1} page{version.page_count === 1 ? '' : 's'}
                  </span>
                </div>

                <!-- Primary Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                  <!-- Preview PDF (Always available) -->
                  <Button
                    variant="outline"
                    size="sm"
                    href={`/setup/omr/templates/${version.id}/preview`}
                    target="_blank"
                    rel="noopener"
                  >
                    <Eye class="mr-1.5 size-3.5" />
                    Preview sheet
                  </Button>

                  <!-- Custom Sheet Text (Header/Footer/Title) -->
                  <Button
                    variant="outline"
                    size="sm"
                    onclick={() => toggleSheetEditor(version)}
                  >
                    <Sliders class="mr-1.5 size-3.5" />
                    Sheet text
                  </Button>

                  <!-- Draft: Continue in Builder -->
                  {#if isDraft}
                    <Button asChild size="sm">
                      <Link href={`/setup/omr/templates/builder?version=${version.id}`}>
                        <Edit3 class="mr-1.5 size-3.5" />
                        Edit in builder
                      </Link>
                    </Button>
                  {/if}

                  <!-- Active: New version from this -->
                  {#if isActive}
                    <Button
                      variant="outline"
                      size="sm"
                      onclick={() => copyToNewDraft(version)}
                      disabled={copyingVersionId === version.id}
                    >
                      <Copy class="mr-1.5 size-3.5" />
                      {copyingVersionId === version.id ? 'Copying…' : 'New version from this'}
                    </Button>
                  {/if}

                  <!-- Retire -->
                  {#if version.status !== 'retired'}
                    <Button
                      variant="ghost"
                      size="sm"
                      class="text-muted-foreground hover:text-destructive"
                      onclick={() =>
                        requestRetire(
                          'template_version',
                          version.id,
                          `version v${version.version}`,
                          cascadePairs(version),
                        )}
                    >
                      Retire
                    </Button>
                  {/if}
                </div>
              </div>

              <!-- Inline Retire Confirm Drawer -->
              {#if confirming?.kind === 'template_version' && confirming.id === version.id}
                <div class="mt-4 rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-xs text-destructive">
                  <p class="font-semibold">Retire template version v{version.version}?</p>
                  {#if confirming.pairs.length > 0}
                    <p class="mt-1">This will also retire {confirming.pairs.length} active scanning pair(s):</p>
                    <ul class="mt-1 list-disc space-y-0.5 pl-5">
                      {#each confirming.pairs as pair (pair.id)}
                        <li>{pair.label}</li>
                      {/each}
                    </ul>
                  {:else}
                    <p class="mt-1">No active pairs are linked to this version.</p>
                  {/if}
                  <div class="mt-3 flex gap-2">
                    <Button size="xs" variant="destructive" onclick={confirmRetire} disabled={retiring}>
                      {retiring ? 'Retiring…' : 'Confirm retire'}
                    </Button>
                    <Button size="xs" variant="outline" onclick={() => (confirming = null)}>Cancel</Button>
                  </div>
                </div>
              {/if}

              <!-- Collapsible Sheet Text Editor -->
              {#if sheetOpenVersionId === version.id}
                <div class="mt-4 rounded-xl border border-primary/30 bg-muted/30 p-4">
                  <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-foreground">
                      Custom printable sheet text (v{version.version})
                    </h4>
                    {#if !isDraft}
                      <span class="text-xs text-muted-foreground">
                        Saving changes on an active version creates a new editable draft.
                      </span>
                    {/if}
                  </div>

                  {#if errors.omr_overlay}
                    <p class="mt-3 rounded bg-red-50 p-3 text-xs text-red-800">{errors.omr_overlay}</p>
                  {/if}

                  <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                      <Label for={`sheet_title_${version.id}`} class="text-xs font-medium">Sheet title</Label>
                      <Input
                        id={`sheet_title_${version.id}`}
                        bind:value={sheetTitle}
                        placeholder="e.g. Entrance Exam 2026 — Answer Sheet"
                        class="h-8 text-xs"
                      />
                    </div>
                    <div class="space-y-1">
                      <Label for={`sheet_name_box_${version.id}`} class="text-xs font-medium">Name box label</Label>
                      <Input
                        id={`sheet_name_box_${version.id}`}
                        bind:value={sheetNameBoxLabel}
                        placeholder="e.g. NAME (PRINT):"
                        class="h-8 text-xs"
                      />
                    </div>
                  </div>

                  <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="space-y-1">
                      <Label for={`sheet_header_${version.id}`} class="text-xs font-medium">Header lines</Label>
                      <Textarea
                        id={`sheet_header_${version.id}`}
                        bind:value={sheetHeaderLines}
                        rows="2"
                        placeholder={'One line per row\ne.g. EX-DEMO-1 · MAIN HALL 101'}
                        class="text-xs"
                      />
                    </div>
                    <div class="space-y-1">
                      <Label for={`sheet_footer_${version.id}`} class="text-xs font-medium">Footer lines</Label>
                      <Textarea
                        id={`sheet_footer_${version.id}`}
                        bind:value={sheetFooterLines}
                        rows="2"
                        placeholder={'One line per row\ne.g. Shade completely. Erasures void the sheet.'}
                        class="text-xs"
                      />
                    </div>
                  </div>

                  <div class="mt-3 flex items-center gap-2">
                    <Button size="xs" onclick={() => saveSheet(version)} disabled={sheetSaving}>
                      {sheetSaving ? 'Saving…' : isDraft ? 'Save sheet text' : 'Save as new draft edition'}
                    </Button>
                    <Button size="xs" variant="outline" onclick={() => (sheetOpenVersionId = null)}>Cancel</Button>
                  </div>
                </div>
              {/if}

              <!-- Collapsible Forensic / Engine Details -->
              <div class="mt-4 border-t border-border/50 pt-3">
                <button
                  type="button"
                  class="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                  onclick={() => toggleTech(version.id)}
                >
                  {#if showTechDetails[version.id]}
                    <ChevronDown class="size-3.5" />
                  {:else}
                    <ChevronRight class="size-3.5" />
                  {/if}
                  <span>Technical hashes & engine specifications</span>
                </button>

                {#if showTechDetails[version.id]}
                  <dl class="mt-3 grid gap-x-6 gap-y-1.5 rounded-lg bg-muted/30 p-3 text-xs sm:grid-cols-2">
                    <div class="flex justify-between border-b border-border/40 py-1">
                      <dt class="text-muted-foreground">Engine:</dt>
                      <dd class="font-mono text-foreground">{version.engine_name} @ {version.engine_commit?.slice(0, 8)}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border/40 py-1">
                      <dt class="text-muted-foreground">Patch identity:</dt>
                      <dd class="font-mono text-foreground">{version.patch_identity ?? '—'}</dd>
                    </div>
                    <div class="flex justify-between border-b border-border/40 py-1">
                      <dt class="text-muted-foreground">Bundle hash:</dt>
                      <dd class="font-mono text-foreground" title={version.native_bundle_hash}>
                        {shortHash(version.native_bundle_hash)}…
                      </dd>
                    </div>
                    <div class="flex justify-between border-b border-border/40 py-1">
                      <dt class="text-muted-foreground">Config hash:</dt>
                      <dd class="font-mono text-foreground" title={version.config_hash}>
                        {shortHash(version.config_hash)}…
                      </dd>
                    </div>
                  </dl>
                {/if}
              </div>
            </div>
          {/each}
        </div>
      {/if}
    </div>

    <!-- Linked Pairs Section -->
    <div>
      <h2 class="text-base font-semibold text-foreground">Linked scanning pairs</h2>
      <p class="text-xs text-muted-foreground">Pairs using versions of this answer sheet.</p>

      {#if pairs.length === 0}
        <p class="mt-2 text-xs text-muted-foreground">No pairs registered for this template.</p>
      {:else}
        <div class="mt-3 space-y-2">
          {#each pairs as pair (pair.id)}
            <div class="flex items-center justify-between rounded-xl border border-border/70 bg-card p-3.5 text-xs">
              <span class="font-medium text-foreground">{pair.label}</span>
              <div class="flex items-center gap-2">
                <Badge variant={pair.status === 'active' ? 'default' : 'outline'} class="capitalize text-[10px]">
                  {pair.status}
                </Badge>
                {#if pair.status === 'active'}
                  <Button
                    variant="outline"
                    size="xs"
                    onclick={() =>
                      requestRetire('pair', pair.id, pair.label, [])}
                  >
                    Retire
                  </Button>
                {/if}
              </div>
            </div>
          {/each}
        </div>
      {/if}
    </div>
  </section>
</StaffLayout>
