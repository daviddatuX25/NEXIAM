<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import { useForm, usePage, router } from '@inertiajs/svelte';
  import { FileCode, FileText, Play } from 'lucide-svelte';

  let { template = {}, placeholderGroups = {}, domainPlaceholders = [] } = $props();

  const page = usePage();
  const csrfToken = $derived($page.props.csrf_token ?? '');

  const form = useForm({
    name: template.name ?? '',
    mode: template.mode ?? 'html',
    paper_size: template.paper_size ?? 'a4',
    orientation: template.orientation ?? 'portrait',
    logical_unit: template.logical_unit ?? 'full',
    content: template.content ?? '',
    document: null,
    watermark_text: template.watermark_text ?? '',
    is_active: template.is_active ?? false,
  });

  let rawDocumentFile = null;
  let validationResult = $state(null);
  let validationLoading = $state(false);
  let previewHtml = $state('');
  let previewLoading = $state(false);

  function handleDocument(e) {
    rawDocumentFile = e?.target?.files?.[0] ?? null;
    form.document = rawDocumentFile;
    runValidation();
    runPreview();
  }

  async function runValidation() {
    validationResult = null;
    if (form.mode !== 'docx' || !rawDocumentFile) return;
    validationLoading = true;
    const fd = new FormData();
    fd.append('logical_unit', form.logical_unit);
    fd.append('document', rawDocumentFile);
    try {
      const res = await fetch('/setup/result-templates/validate-document', {
        method: 'POST',
        body: fd,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
      });
      validationResult = res.ok ? await res.json() : null;
    } catch {
      validationResult = null;
    } finally {
      validationLoading = false;
    }
  }

  async function runPreview() {
    previewLoading = true;
    const fd = new FormData();
    fd.append('mode', form.mode);
    fd.append('paper_size', form.paper_size);
    fd.append('orientation', form.orientation);
    fd.append('logical_unit', form.logical_unit);
    if (form.mode === 'html' && form.content?.trim()) {
      fd.append('content', form.content);
    } else if (rawDocumentFile) {
      fd.append('document', rawDocumentFile);
    } else {
      previewLoading = false;
      previewHtml = '';
      return;
    }
    try {
      const res = await fetch('/setup/result-templates/preview', {
        method: 'POST',
        body: fd,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
      });
      const data = res.ok ? await res.json() : null;
      previewHtml = data?.html ?? '';
    } finally {
      previewLoading = false;
    }
  }

  let previewTimer;
  $effect(() => {
    if (form.mode === 'html') {
      clearTimeout(previewTimer);
      previewTimer = setTimeout(() => {
        if (form.content) runPreview();
        else previewHtml = '';
      }, 400);
    }
  });

  function submit() {
    if (form.mode === 'docx' && validationResult && !validationResult.valid && validationResult.missing?.length > 0) {
      const missing = validationResult.missing.map((m) => `{{${m}}}`).join(', ');
      if (!window.confirm(`The template is missing required placeholders: ${missing}. Save anyway?`)) return;
    }
    form.transform((data) => ({ ...data, document: rawDocumentFile }));
    form.put(`/setup/result-templates/${template.id}`, { forceFormData: true });
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Result Sheet Templates', href: '/setup/result-templates' },
    { label: template.name ?? 'Edit' },
  ];

  const groups = $derived([
    { title: 'Applicant', items: placeholderGroups.applicant ?? [] },
    { title: 'Exam', items: placeholderGroups.exam ?? [] },
    { title: 'Scores', items: placeholderGroups.scores ?? [] },
    { title: 'Recommendation', items: placeholderGroups.recommendation ?? [] },
    { title: 'Institution', items: placeholderGroups.institution ?? [] },
    { title: 'Personnel', items: placeholderGroups.personnel ?? [] },
    { title: 'Legacy aliases (ICAT)', items: placeholderGroups.aliases ?? [] },
  ]);
</script>

<svelte:head><title>Edit Result Sheet Template · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar title="Edit result sheet template" description="Changes apply to sheets rendered after saving." />

    <div class="grid gap-6 lg:grid-cols-5">
      <div class="space-y-3 lg:col-span-2">
        <div class="rounded-xl border border-border bg-card p-4">
          <h3 class="mb-3 text-sm font-semibold">Placeholder guide</h3>
          {#each groups as group}
            {#if group.items?.length}
              <div class="mb-3">
                <p class="mb-1 text-xs font-medium text-muted-foreground">{group.title}</p>
                <div class="flex flex-wrap gap-1.5">
                  {#each group.items as item}
                    <button
                      type="button"
                      class="rounded-md border border-border bg-muted/40 px-2 py-1 font-mono text-[11px] hover:bg-muted"
                      onclick={() => navigator.clipboard?.writeText(`{{${item.placeholder}}}`)}
                      title={item.description}
                    >
                      {'{{'}{item.placeholder}{'}}'}
                    </button>
                  {/each}
                </div>
              </div>
            {/if}
          {/each}
          {#if domainPlaceholders.length}
            <div class="mb-3">
              <p class="mb-1 text-xs font-medium text-muted-foreground">Per-domain (from active aptitude areas)</p>
              <div class="flex flex-wrap gap-1.5">
                {#each domainPlaceholders as dp}
                  <button
                    type="button"
                    class="rounded-md border border-border bg-muted/40 px-2 py-1 font-mono text-[11px] hover:bg-muted"
                    onclick={() => navigator.clipboard?.writeText(`{{${dp.slug}}}`)}
                  >
                    {'{{'}{dp.slug}{'}}'}
                  </button>
                {/each}
              </div>
            </div>
          {/if}
        </div>
      </div>

      <div class="space-y-4 lg:col-span-3">
        <form class="space-y-4 rounded-xl border border-border bg-card p-4" onsubmit={(e) => { e.preventDefault(); submit(); }}>
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="space-y-1.5">
              <Label for="name">Name</Label>
              <Input id="name" bind:value={form.name} class="min-h-10" required />
            </div>
            <div class="space-y-1.5">
              <Label>Mode</Label>
              <div class="flex gap-2">
                <button type="button" onclick={() => { form.mode = 'html'; }} class="flex min-h-10 flex-1 items-center justify-center gap-2 rounded-md border px-3 text-sm {form.mode === 'html' ? 'border-primary bg-primary/10 text-primary' : 'border-border'}">
                  <FileCode class="size-4" /> HTML
                </button>
                <button type="button" onclick={() => { form.mode = 'docx'; }} class="flex min-h-10 flex-1 items-center justify-center gap-2 rounded-md border px-3 text-sm {form.mode === 'docx' ? 'border-primary bg-primary/10 text-primary' : 'border-border'}">
                  <FileText class="size-4" /> Word / ODT
                </button>
              </div>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-3">
            <div class="space-y-1.5">
              <Label for="paper_size">Paper</Label>
              <select id="paper_size" class="min-h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm" bind:value={form.paper_size}>
                <option value="a4">A4</option>
                <option value="legal">Legal</option>
                <option value="letter">Letter</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <Label for="orientation">Orientation</Label>
              <select id="orientation" class="min-h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm" bind:value={form.orientation}>
                <option value="portrait">Portrait</option>
                <option value="landscape">Landscape</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <Label for="logical_unit">Logical unit</Label>
              <select id="logical_unit" class="min-h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm" bind:value={form.logical_unit}>
                <option value="full">Full page</option>
                <option value="half_a4">Half A4 (crosswise)</option>
                <option value="half_legal">Half Legal (crosswise)</option>
                <option value="half_letter">Half Letter (crosswise)</option>
              </select>
            </div>
          </div>

          <div class="space-y-1.5">
            <Label for="watermark_text">Watermark text (optional)</Label>
            <Input id="watermark_text" bind:value={form.watermark_text} class="min-h-10" maxlength="50" />
          </div>

          {#if form.mode === 'docx'}
            <div class="space-y-1.5">
              <Label>Replace document (optional)</Label>
              <input type="file" accept=".docx,.odt" class="min-h-10 w-full rounded-md border border-input bg-transparent text-sm file:mr-3 file:border-0 file:bg-muted file:px-3 file:py-2" onchange={handleDocument} />
              {#if template.document_extension}
                <p class="text-xs text-muted-foreground">Current: {template.document_extension.toUpperCase()} (checksummed)</p>
              {/if}
              {#if validationLoading}
                <p class="text-xs text-muted-foreground">Analyzing placeholders…</p>
              {:else if validationResult}
                <div class="rounded-md border p-3 text-xs">
                  <p>{validationResult.found?.length ?? 0} placeholders found</p>
                  {#if validationResult.missing?.length}
                    <p class="mt-1 text-destructive">Missing required: {validationResult.missing.map((m) => `{{${m}}}`).join(', ')}</p>
                  {/if}
                </div>
              {/if}
            </div>
          {:else}
            <div class="space-y-1.5">
              <Label for="content">HTML content</Label>
              <Textarea id="content" bind:value={form.content} class="min-h-72 font-mono text-xs" />
            </div>
          {/if}

          <div class="flex items-center justify-between gap-3">
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" bind:checked={form.is_active} class="size-4 rounded border-input" />
              Active
            </label>
            <div class="flex gap-2">
              <Button type="button" variant="outline" onclick={() => router.visit('/setup/result-templates')}>Cancel</Button>
              <Button type="submit" disabled={form.processing}>
                {form.processing ? 'Saving…' : 'Save changes'}
              </Button>
            </div>
          </div>
        </form>

        <div class="rounded-xl border border-border bg-card p-4">
          <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-semibold">Live preview</h3>
            <Button type="button" variant="outline" size="sm" class="h-8 gap-1.5 text-xs" onclick={runPreview}>
              <Play class="size-3.5" />
              Refresh
            </Button>
          </div>
          {#if previewLoading}
            <p class="text-xs text-muted-foreground">Rendering…</p>
          {:else if previewHtml}
            <iframe title="Template preview" class="h-[560px] w-full rounded-md border border-border bg-white" srcdoc={previewHtml} sandbox="" />
          {:else}
            <p class="text-xs text-muted-foreground">Refresh the preview to render the current content with sample data.</p>
          {/if}
        </div>
      </div>
    </div>
  </div>
</StaffLayout>
