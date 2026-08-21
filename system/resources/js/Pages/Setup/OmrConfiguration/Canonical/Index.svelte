<script>
  import { Link, useForm } from '@inertiajs/svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import {
    FileText,
    Plus,
    ArrowRight,
    Sparkles,
    CheckCircle2,
    Layers,
    ShieldCheck,
    FileSpreadsheet,
    FileCode,
    Image,
    Sliders,
    AlertCircle,
  } from 'lucide-svelte';

  let { drafts = [], create_url = '' } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: 'Template Studio' },
  ];

  const form = useForm({
    code: '',
    name: '',
    source_kind: 'image',
  });

  const sourceKinds = [
    { id: 'image', label: 'Image / Photo', desc: 'Scan or phone photo (auto-rectified)', icon: Image },
    { id: 'pdf', label: 'PDF Document', desc: 'Vector or rasterized exam sheet', icon: FileText },
    { id: 'docx', label: 'Word (DOCX)', desc: 'Pre-formatted exam layout', icon: FileSpreadsheet },
    { id: 'html', label: 'HTML Markup', desc: 'Clean semantic HTML with overlay tags', icon: FileCode },
    { id: 'preset', label: 'Synthetic Preset', desc: 'Standard 242-PPI grid blueprint', icon: Sliders },
  ];

  function createDraft(event) {
    event.preventDefault();
    form.post(create_url, { preserveScroll: true });
  }

  function formatStage(stage) {
    return (stage || 'draft').replaceAll('_', ' ');
  }

  function stageBadgeVariant(stage) {
    if (stage === 'compiled' || stage === 'physical_qualified') return 'default';
    if (stage === 'digital_verified' || stage === 'mapping_complete') return 'secondary';
    return 'outline';
  }
</script>

<StaffLayout {breadcrumbs}>
  <PageHeaderBar
    title="Template Studio"
    description="Create and edit evaluation answer sheets from any source format (HTML, PDF, DOCX, Image, or Blank)."
  >
    {#snippet actions()}
      <Button href="/setup/omr/templates" variant="outline" size="sm">View compiled templates</Button>
    {/snippet}
  </PageHeaderBar>

  <section class="space-y-8 p-6">
    <!-- 9-Stage Overview Banner -->
    <div class="rounded-xl border border-primary/20 bg-primary/5 p-5">
      <div class="flex items-center gap-3">
        <div class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
          <Sparkles class="size-5" />
        </div>
        <div>
          <h3 class="text-sm font-semibold text-foreground">Answer Sheet Template Studio</h3>
          <p class="text-xs text-muted-foreground">
            Design vector print layouts, customize headers and signature frames, map question bubble coordinates, and test with live camera capture.
          </p>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
      <!-- Left: Drafts List -->
      <article class="rounded-xl border border-border/80 bg-card shadow-sm">
        <div class="flex items-center justify-between border-b border-border/60 p-5">
          <div>
            <h2 class="font-semibold text-foreground">Active Authoring Drafts</h2>
            <p class="mt-0.5 text-xs text-muted-foreground">
              In-progress drafts remain editable with auto-save and lock versioning.
            </p>
          </div>
          <Badge variant="secondary" class="text-xs">
            {drafts.length} {drafts.length === 1 ? 'draft' : 'drafts'}
          </Badge>
        </div>

        <div class="divide-y divide-border/60">
          {#each drafts as draft (draft.id)}
            <Link
              href={draft.url}
              class="group flex flex-col justify-between gap-3 p-5 transition hover:bg-muted/30 sm:flex-row sm:items-center"
            >
              <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-semibold text-foreground group-hover:text-primary transition-colors">
                    {draft.name}
                  </span>
                  <Badge variant="outline" class="text-[10px] uppercase font-mono tracking-wider">
                    {draft.source_kind}
                  </Badge>
                  {#if !draft.can_edit}
                    <Badge variant="destructive" class="text-[10px]">Read-only</Badge>
                  {/if}
                </div>
                <p class="font-mono text-xs text-muted-foreground">
                  {draft.code} · Owner: {draft.owner_name ?? 'Staff'}
                </p>
              </div>

              <div class="flex items-center gap-3">
                <Badge variant={stageBadgeVariant(draft.stage)} class="capitalize text-xs">
                  {formatStage(draft.stage)}
                </Badge>
                <Button size="xs" variant="ghost" class="group-hover:translate-x-0.5 transition-transform">
                  Continue
                  <ArrowRight class="ml-1 size-3.5" />
                </Button>
              </div>
            </Link>
          {:else}
            <div class="p-12 text-center">
              <Layers class="mx-auto size-9 text-muted-foreground" />
              <h3 class="mt-2 text-sm font-semibold text-foreground">No active authoring drafts</h3>
              <p class="mt-1 text-xs text-muted-foreground">
                Create a new draft on the right to start authoring an answer sheet.
              </p>
            </div>
          {/each}
        </div>
      </article>

      <!-- Right: Create Draft Card -->
      <aside class="h-fit rounded-xl border border-border/80 bg-card p-5 shadow-sm">
        <div class="flex items-center gap-2">
          <div class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            <Plus class="size-4" />
          </div>
          <div>
            <h2 class="font-semibold text-foreground">Start New Template</h2>
            <p class="text-xs text-muted-foreground">Initialize a fresh authoring draft</p>
          </div>
        </div>

        <form class="mt-5 space-y-4" onsubmit={createDraft}>
          <div class="space-y-1.5">
            <Label for="draft-code">Template Code</Label>
            <Input
              id="draft-code"
              placeholder="e.g. APTITUDE-2026-A"
              bind:value={form.code}
              class="font-mono text-xs uppercase"
              required
            />
          </div>

          <div class="space-y-1.5">
            <Label for="draft-name">Template Name</Label>
            <Input
              id="draft-name"
              placeholder="e.g. General Aptitude Test Form A"
              bind:value={form.name}
              required
            />
          </div>

          <div class="space-y-2">
            <Label>Source Kind</Label>
            <div class="grid gap-2">
              {#each sourceKinds as sk}
                {@const Icon = sk.icon}
                <label
                  class={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all ${
                    form.source_kind === sk.id
                      ? 'border-primary bg-primary/5 ring-1 ring-primary'
                      : 'border-border/60 hover:bg-muted/20'
                  }`}
                >
                  <input
                    type="radio"
                    name="source_kind"
                    value={sk.id}
                    bind:group={form.source_kind}
                    class="mt-1"
                  />
                  <div class="flex-1 text-xs">
                    <div class="flex items-center gap-1.5 font-medium text-foreground">
                      <Icon class="size-3.5 text-muted-foreground" />
                      {sk.label}
                    </div>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">{sk.desc}</p>
                  </div>
                </label>
              {/each}
            </div>
          </div>

          {#if Object.keys(form.errors).length}
            <div class="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-3 text-xs text-destructive">
              <AlertCircle class="size-4 shrink-0" />
              <span>{Object.values(form.errors)[0]}</span>
            </div>
          {/if}

          <Button class="w-full" type="submit" disabled={form.processing}>
            {form.processing ? 'Creating draft…' : 'Create Template Draft'}
          </Button>
        </form>
      </aside>
    </div>
  </section>
</StaffLayout>
