<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { FileUpload } from '@/Components/ui/file-upload';
  import Switch from '@/Components/ui/switch/switch.svelte';
  import { Textarea } from '@/Components/ui/textarea';
  import { ToggleGroup, ToggleGroupItem } from '@/Components/ui/toggle-group';
  import * as Select from '@/Components/ui/select';
  import { success } from '@/lib/toast';
  import { FileCode, FileText } from 'lucide-svelte';
  import { GuidePanel, GuideSection, CopyableGroup, GuideNote } from '@/Components/Guide';
  import DocxTemplateAnalyzer from '@/Components/DocxTemplateAnalyzer.svelte';

  const breadcrumbs = [
    { label: 'Setup', href: '/admin/setup' },
    { label: 'Result Sheet Templates', href: '/admin/release/result-templates' },
    { label: 'Edit' },
  ];

  let {
    template,
    placeholdersApplicant1 = [],
    placeholdersApplicant2 = [],
    domainPlaceholders = [],
    placeholderGroups = {},
    exampleRating = '',
    htmlScoresNote = '',
    htmlTemplateRules = '',
    docxPlaceholderNote = '',
    layoutOptions = { full: 'Full page', half_a4: 'Half-crosswise' },
  } = $props();
  const page = usePage();
  const csrfToken = $derived($page.props.csrf_token ?? '');

  const form = useForm({
    name: template?.name ?? '',
    mode: template?.mode ?? 'html',
    content: template?.content ?? '',
    document: null,
    paper_size: template?.paper_size ?? 'a4',
    orientation: template?.orientation ?? 'portrait',
    logical_unit: template?.logical_unit ?? 'full',
    is_active: template?.is_active ?? true,
    watermark_text: template?.watermark_text ?? null,
  });

  let isCrosswise = $derived($form.logical_unit !== 'full');

  const applicant1Items = $derived(
    placeholdersApplicant1
      .filter((ph) => !ph.endsWith('_check}}'))
      .map((ph) => ({ value: ph }))
  );
  const applicant2Items = $derived(
    placeholdersApplicant2
      .filter((ph) => !ph.endsWith('_check_2}}'))
      .map((ph) => ({ value: ph }))
  );
  const courseCheckItems1 = $derived(
    placeholderGroups?.course_checks?.map((p) => ({ value: p.placeholder, label: p.description })) ?? [],
  );
  const courseCheckItems2 = $derived(
    placeholderGroups?.course_checks_2?.map((p) => ({ value: p.placeholder, label: p.description })) ?? [],
  );
  const institutionItems = $derived(
    placeholderGroups?.institution?.map((p) => ({ value: p.placeholder, label: p.description })) ?? [],
  );
  const personnelItems = $derived(
    placeholderGroups?.personnel?.map((p) => ({ value: p.placeholder, label: p.description })) ?? [],
  );
  const domainItems1 = $derived(
    domainPlaceholders.flatMap((dp) => [
      { value: dp.example, label: `${dp.slug} — Percentile` },
      { value: dp.example.replace('}}', '_rating}}'), label: `${dp.slug} — Rating` },
    ]),
  );
  const domainItems2 = $derived(
    domainPlaceholders.flatMap((dp) => [
      { value: dp.example.replace('}}', '_2}}'), label: `${dp.slug} — Percentile` },
      { value: dp.example.replace('}}', '_rating_2}}'), label: `${dp.slug} — Rating` },
    ]),
  );

  // $state for reactivity tracking only — Svelte 5 wraps objects in Proxy,
  // which breaks FormData.append (internal slots require a real File).
  let documentFile = $state(null);
  let rawDocumentFile = null; // plain var holds the actual File object

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Result sheet template updated');
    }
  };

  function handleDocumentFile(e) {
    const file = e.detail?.files?.[0] ?? e.files?.[0];
    rawDocumentFile = file;  // raw File for FormData / Inertia
    documentFile = file;     // triggers $effect reactivity
  }

  let previewHtml = $state('');
  let previewLoading = $state(false);
  let previewError = $state(null);
  let previewDebounce = null; // plain var: $state would make $effect depend on it → infinite loop on write

  let validationResult = $state(null); // { valid, found, missing, extra }
  let validationLoading = $state(false);

  function fetchValidation() {
    if ($form.mode !== 'docx') { validationResult = null; return; }
    validationLoading = true;
    const fd = new FormData();
    fd.append('logical_unit', $form.logical_unit);
    if (rawDocumentFile) {
      fd.append('document', rawDocumentFile);
    } else if (template?.id) {
      fd.append('template_id', template.id);
    } else {
      validationLoading = false;
      return;
    }
    fetch('/admin/release/result-templates/validate-document', {
      method: 'POST',
      body: fd,
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
    })
      .then((r) => r.ok ? r.json() : null)
      .then((data) => {
        if (data) validationResult = data;
        else validationResult = null;
      })
      .catch(() => { validationResult = null; })
      .finally(() => (validationLoading = false));
  }

  function submitForm(e) {
    e.preventDefault();
    if ($form.mode === 'docx' && validationResult && !validationResult.valid && validationResult.missing?.length > 0) {
      const missingList = validationResult.missing.map(m => `{{${m}}}`).join(', ');
      const confirmSave = confirm(
        `Are you sure you want to save? The template is missing the following required placeholders: ${missingList}`
      );
      if (!confirmSave) {
        return;
      }
    }
    $form.transform((data) => ({ ...data, document: rawDocumentFile }));
    $form.put(`/admin/release/result-templates/${template.id}`, { forceFormData: true });
  }

  function fetchPreview() {
    clearTimeout(previewDebounce);
    previewDebounce = setTimeout(() => {
      previewLoading = true;
      previewError = null;
      const fd = new FormData();
      fd.append('mode', $form.mode);
      fd.append('paper_size', $form.paper_size);
      fd.append('orientation', $form.orientation);
      fd.append('logical_unit', $form.logical_unit);

      if ($form.mode === 'html' && $form.content?.trim()) {
        fd.append('content', $form.content);
      } else if (rawDocumentFile) {
        fd.append('document', rawDocumentFile);
      } else if (template?.id && $form.mode === 'docx') {
        fd.append('template_id', template.id);
      } else {
        previewLoading = false;
        previewHtml = '<p class="text-muted-foreground p-4">Upload a document file to preview.</p>';
        return;
      }

      fetch('/admin/release/result-templates/preview', {
        method: 'POST',
        body: fd,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
      })
        .then(async (r) => {
          if (!r.ok) {
            const text = await r.text();
            let msg = 'Preview failed';
            try { msg = JSON.parse(text)?.error ?? JSON.parse(text)?.message ?? msg; } catch {}
            throw new Error(msg + ` (${r.status})`);
          }
          return r.json();
        })
        .then((data) => {
          previewHtml = data.html || '';
          previewError = null;
        })
        .catch((err) => {
          previewError = err.message || 'Preview failed';
          previewHtml = '';
        })
        .finally(() => (previewLoading = false));
    }, 400);
  }

  $effect(() => {
    $form.mode;
    documentFile;
    $form.content;
    if ($form.mode === 'html' && $form.content) fetchPreview();
    else if ($form.mode === 'docx' && documentFile) fetchPreview();
    else if ($form.mode === 'docx' && template?.document_path) fetchPreview();
    else if ($form.mode === 'docx') previewHtml = '<p class="text-muted-foreground p-4">Upload a document file to replace, or save to use current.</p>';
    else previewHtml = '';
  });

  $effect(() => {
    $form.logical_unit;
    $form.paper_size;
    $form.orientation;
    if ($form.mode === 'html' && $form.content) fetchPreview();
    else if ($form.mode === 'docx' && (documentFile || template?.document_path)) fetchPreview();
  });

  $effect(() => {
    documentFile;
    $form.mode;
    $form.logical_unit;
    fetchValidation();
  });
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-6xl space-y-6">
    <GuidePanel title="Placeholder Reference & Templates Guide">
      <GuideSection title={isCrosswise ? 'Applicant 1 — Single print / odd in crosswise bulk' : 'Applicant Placeholders'}>
        <CopyableGroup items={applicant1Items} subtitle="Identity & Info" />
        {#if courseCheckItems1.length > 0}
          <CopyableGroup
            items={courseCheckItems1}
            subtitle="Course Recommendation Checks (e.g. BSIT_check resolves to ✔ if recommended, otherwise empty)"
            class="mt-3"
          />
        {/if}
        {#if domainPlaceholders.length > 0}
          <CopyableGroup
            items={domainItems1}
            subtitle={`Score Domains — Percentile & Descriptive Rating${exampleRating ? ` (e.g. ${exampleRating})` : ''}`}
            class="mt-3"
          />
        {/if}
      </GuideSection>

      <GuideSection title="Applicant 2 — Even in crosswise bulk" visible={isCrosswise}>
        <CopyableGroup items={applicant2Items} subtitle="Identity & Info" />
        {#if courseCheckItems2.length > 0}
          <CopyableGroup
            items={courseCheckItems2}
            subtitle="Course Recommendation Checks"
            class="mt-3"
          />
        {/if}
        {#if domainPlaceholders.length > 0}
          <CopyableGroup
            items={domainItems2}
            subtitle={`Score Domains — Percentile & Descriptive Rating${exampleRating ? ` (e.g. ${exampleRating})` : ''}`}
            class="mt-3"
          />
        {/if}
      </GuideSection>

      <GuideSection title="Institution" visible={(placeholderGroups?.institution?.length ?? 0) > 0}>
        <CopyableGroup items={institutionItems} />
      </GuideSection>

      <GuideSection title="Personnel" visible={(placeholderGroups?.personnel?.length ?? 0) > 0}>
        <CopyableGroup items={personnelItems} />
      </GuideSection>

      {#if $form.mode === 'html'}
        <GuideSection title="HTML Rules" visible={!!htmlTemplateRules}>
          <p class="text-xs text-muted-foreground">{htmlTemplateRules}</p>
        </GuideSection>
        <GuideSection title="Scores Table" visible={!!htmlScoresNote}>
          <p class="text-xs text-muted-foreground">{@html htmlScoresNote}</p>
        </GuideSection>
      {:else}
        <GuideSection title="Document Notes" visible={!!docxPlaceholderNote}>
          <p class="text-xs text-muted-foreground">{docxPlaceholderNote}</p>
        </GuideSection>
      {/if}
    </GuidePanel>

    <div class="grid gap-6 lg:grid-cols-2">
      <form onsubmit={submitForm} class="space-y-4 rounded-lg border bg-card p-6">
        <div>
          <label for="name" class="text-sm font-medium">Name</label>
          <Input id="name" bind:value={$form.name} placeholder="Default" required class="mt-1" />
          {#if $form.errors?.name}<p class="text-sm text-destructive mt-1">{$form.errors.name}</p>{/if}
        </div>

        <!-- Mode Switch -->
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium">Mode</label>
          <ToggleGroup bind:value={$form.mode} type="single" variant="default" class="bg-muted p-1 rounded-md">
            <ToggleGroupItem value="html" class="gap-1.5 px-3 py-1.5">
              <FileCode class="size-4" />
              <span class="text-sm">HTML</span>
            </ToggleGroupItem>
            <ToggleGroupItem value="docx" class="gap-1.5 px-3 py-1.5">
              <FileText class="size-4" />
              <span class="text-sm">DOCX / ODT</span>
            </ToggleGroupItem>
          </ToggleGroup>
          {#if template?.document_path && $form.mode === 'docx'}
            <span class="text-xs text-muted-foreground">Current file exists. Upload to replace.</span>
          {/if}
        </div>

        {#if $form.mode === 'html'}
          <div>
            <label class="text-sm font-medium">Layout</label>
            <Select.Root type="single" bind:value={$form.logical_unit}>
              <Select.Trigger class="mt-1 w-full max-w-xs">
                {layoutOptions[$form.logical_unit] ?? 'Select layout'}
              </Select.Trigger>
              <Select.Content>
                {#each Object.entries(layoutOptions) as [k, v]}
                  <Select.Item value={k} label={v}>{v}</Select.Item>
                {/each}
              </Select.Content>
            </Select.Root>
          </div>
        {/if}

        {#if $form.mode === 'html'}
          <div>
            <label for="content" class="text-sm font-medium">HTML + CSS (JavaScript not allowed)</label>
            <p class="text-xs text-muted-foreground mt-0.5">Enter custom HTML and CSS. Use placeholders like &#123;&#123;applicant_name&#125;&#125;. Scripts and event handlers are stripped for security.</p>
            <Textarea
              id="content"
              bind:value={$form.content}
              required={$form.mode === 'html'}
              rows="16"
              placeholder="<div style=&quot;padding: 1rem;&quot;><p>Hello <strong>&#123;&#123;applicant_name&#125;&#125;</strong></p></div>"
              class="mt-2 flex w-full font-mono"
            />
            {#if $form.errors?.content}<p class="text-sm text-destructive mt-1">{$form.errors.content}</p>{/if}
          </div>
        {:else}
          <div>
            <label for="document" class="text-sm font-medium">Document file (DOCX or ODT)</label>
            <FileUpload
              label="Upload document template"
              accept=".docx,.odt"
              maxSize="5MB"
              onfiles={handleDocumentFile}
            />
            {#if $form.errors?.document}<p class="text-sm text-destructive mt-1">{$form.errors.document}</p>{/if}
            {#if validationLoading}
              <p class="text-xs text-muted-foreground mt-1">Checking placeholders…</p>
            {:else if validationResult}
              <DocxTemplateAnalyzer result={validationResult} loading={validationLoading} />
            {/if}
          </div>
        {/if}

        {#if template.is_active}
          <p class="text-sm text-muted-foreground">This template is currently active. Activate another template from the list to change.</p>
        {/if}

        <div>
          <label for="watermark_text" class="text-sm font-medium">Watermark text (optional)</label>
          <p class="text-xs text-muted-foreground mt-0.5">Leave blank for no watermark. Shown diagonally on each PDF page (e.g. DRAFT, FINAL).</p>
          <Input
            id="watermark_text"
            bind:value={$form.watermark_text}
            placeholder="DRAFT"
            maxlength="50"
            class="mt-1 max-w-xs"
          />
          {#if $form.errors?.watermark_text}<p class="text-sm text-destructive mt-1">{$form.errors.watermark_text}</p>{/if}
        </div>

        <div class="flex gap-2 pt-4">
          <Button type="submit" disabled={$form.processing}>{$form.processing ? 'Saving...' : 'Save'}</Button>
          <Link href="/admin/release/result-templates"><Button type="button" variant="outline">Cancel</Button></Link>
        </div>
      </form>

      <div class="rounded-lg border bg-card p-4">
        <h3 class="text-sm font-medium mb-2">Live preview</h3>
        {#if $form.mode === 'docx'}
          <GuideNote variant="warning" title="Preview is approximate">
            The document preview is converted to HTML, which may differ from the final PDF output.
            Always verify the printed result.
          </GuideNote>
        {/if}
        {#if previewLoading}
          <p class="text-sm text-muted-foreground p-4">Loading…</p>
        {:else if previewError}
          <p class="text-sm text-destructive p-4">{previewError}</p>
        {:else if previewHtml}
          <iframe
            srcdoc={previewHtml}
            class="w-full rounded border bg-white"
            style="min-height: 400px; max-height: 600px;"
            sandbox="allow-same-origin"
            title="Document Preview"
            onload={(e) => {
              const h = e.target.contentDocument?.body?.scrollHeight;
              if (h) e.target.style.height = `${h + 32}px`;
            }}
          ></iframe>
        {:else}
          <p class="text-sm text-muted-foreground p-4">Edit content or upload document to see preview.</p>
        {/if}
      </div>
    </div>
  </div>
</AuthenticatedLayout>