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

  const breadcrumbs = [
    { label: 'Setup', href: '/admin/setup' },
    { label: 'Admission Slip Templates', href: '/admin/admission-slip-templates' },
    { label: 'Create' },
  ];

  let {
    placeholders = [],
    htmlTemplateRules = '',
    docxPlaceholderNote = '',
    layoutOptions = {
      full: 'Full page',
      half_a4: 'Half A4',
      half_legal: 'Half Legal',
      half_letter: 'Half Letter'
    },
  } = $props();

  const page = usePage();
  const csrfToken = $derived($page.props.csrf_token ?? '');

  const form = useForm({
    name: '',
    mode: 'html',
    content: '',
    docx: null,
    paper_size: 'a4',
    orientation: 'portrait',
    logical_unit: 'full',
    is_active: true,
  });

  const placeholderItems = $derived(
    placeholders.map((ph) => ({ value: ph }))
  );

  // $state for reactivity tracking only — Svelte 5 wraps objects in Proxy,
  // which breaks FormData.append (internal slots require a real File).
  let documentFile = $state(null);
  let rawDocumentFile = null; // plain var holds the actual File object

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Admission slip template created');
    }
  };

  function handleDocumentFile(e) {
    const file = e?.files?.[0];
    rawDocumentFile = file;  // raw File for FormData / Inertia
    documentFile = file;     // triggers $effect reactivity
  }

  function submitForm(e) {
    e.preventDefault();
    $form.transform((data) => ({ ...data, docx: rawDocumentFile }));
    $form.post('/admin/admission-slip-templates', { forceFormData: true });
  }

  let previewHtml = $state('');
  let previewLoading = $state(false);
  let previewError = $state(null);
  let previewDebounce = null; // plain var: $state would make $effect depend on it → infinite loop on write

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
        fd.append('docx', rawDocumentFile);
      } else {
        previewLoading = false;
        previewHtml = '<p class="text-muted-foreground p-4">Upload a document file to preview.</p>';
        return;
      }

      fetch('/admin/admission-slip-templates/preview', {
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
          const json = await r.json();
          return json;
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
    else if ($form.mode === 'docx') previewHtml = '<p class="text-muted-foreground p-4">Upload a document file to preview.</p>';
    else previewHtml = '';
  });

  $effect(() => {
    $form.logical_unit;
    $form.paper_size;
    $form.orientation;
    if ($form.mode === 'html' && $form.content) fetchPreview();
    else if ($form.mode === 'docx' && documentFile) fetchPreview();
  });
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-6xl space-y-6">
    <GuidePanel title="Placeholder Reference & Templates Guide">
      <GuideSection title="Admission Slip Placeholders">
        <CopyableGroup items={placeholderItems} subtitle="Available Fields (click to copy)" />
      </GuideSection>

      {#if $form.mode === 'html'}
        <GuideSection title="HTML Rules" visible={!!htmlTemplateRules}>
          <p class="text-xs text-muted-foreground">{htmlTemplateRules}</p>
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
          <Input id="name" bind:value={$form.name} placeholder="e.g. Standard Admission Slip" required class="mt-1" />
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
              <span class="text-sm">DOCX</span>
            </ToggleGroupItem>
          </ToggleGroup>
        </div>

        <div>
          <label class="text-sm font-medium">Logical Unit</label>
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

        <div>
          <label class="text-sm font-medium">Paper Size</label>
          <Select.Root type="single" bind:value={$form.paper_size}>
            <Select.Trigger class="mt-1 w-full max-w-xs">
              {$form.paper_size.toUpperCase()}
            </Select.Trigger>
            <Select.Content>
              <Select.Item value="a4" label="A4">A4</Select.Item>
              <Select.Item value="legal" label="Legal">Legal</Select.Item>
              <Select.Item value="letter" label="Letter">Letter</Select.Item>
            </Select.Content>
          </Select.Root>
        </div>

        <div>
          <label class="text-sm font-medium">Orientation</label>
          <Select.Root type="single" bind:value={$form.orientation}>
            <Select.Trigger class="mt-1 w-full max-w-xs text-capitalize">
              {$form.orientation}
            </Select.Trigger>
            <Select.Content>
              <Select.Item value="portrait" label="Portrait">Portrait</Select.Item>
              <Select.Item value="landscape" label="Landscape">Landscape</Select.Item>
            </Select.Content>
          </Select.Root>
        </div>

        {#if $form.mode === 'html'}
          <div>
            <label for="content" class="text-sm font-medium">HTML + CSS (JavaScript not allowed)</label>
            <p class="text-xs text-muted-foreground mt-0.5">Enter custom HTML and CSS. Use placeholders like &#123;&#123;full_name&#125;&#125;. Scripts and event handlers are stripped for security.</p>
            <Textarea
              id="content"
              bind:value={$form.content}
              required={$form.mode === 'html'}
              rows="16"
              placeholder="<div style=&quot;padding: 1rem;&quot;><p>Hello <strong>&#123;&#123;full_name&#125;&#125;</strong></p></div>"
              class="mt-2 flex w-full font-mono"
            />
            {#if $form.errors?.content}<p class="text-sm text-destructive mt-1">{$form.errors.content}</p>{/if}
          </div>
        {:else}
          <div>
            <label for="document" class="text-sm font-medium">Document file (DOCX)</label>
            <FileUpload
              label="Upload document template"
              accept=".docx"
              maxSize="5MB"
              onfiles={handleDocumentFile}
            />
            {#if $form.errors?.docx}<p class="text-sm text-destructive mt-1">{$form.errors.docx}</p>{/if}
          </div>
        {/if}

        <div class="flex items-center gap-3">
          <Switch
            checked={$form.is_active}
            onCheckedChange={(checked) => $form.is_active = checked}
          />
          <label for="is_active" class="text-sm">Active</label>
        </div>

        <div class="flex gap-2 pt-4">
          <Button type="submit" disabled={$form.processing}>{$form.processing ? 'Creating...' : 'Create'}</Button>
          <Link href="/admin/admission-slip-templates"><Button type="button" variant="outline">Cancel</Button></Link>
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
          <p class="text-sm text-muted-foreground p-4">Edit content or upload DOCX to see preview.</p>
        {/if}
      </div>
    </div>
  </div>
</AuthenticatedLayout>
