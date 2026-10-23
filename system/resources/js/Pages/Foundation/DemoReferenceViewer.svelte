<script>
  import * as Dialog from '@/Components/ui/dialog';
  import { Button } from '@/Components/ui/button';
  import { safeMarkdownHtml } from '@/lib/safeMarkdownHtml';
  import { Download, ExternalLink, Minus, Plus, RotateCcw } from 'lucide-svelte';

  let { open = $bindable(false), reference = null } = $props();
  let status = $state('idle');
  let error = $state('');
  let documentHtml = $state('');
  let imageSource = $state('');
  let zoom = $state(100);

  $effect(() => {
    const selected = reference;
    if (!open || !selected) {
      status = 'idle';
      documentHtml = '';
      imageSource = '';
      return;
    }

    status = 'loading';
    error = '';
    documentHtml = '';
    imageSource = '';
    zoom = 100;

    const controller = new AbortController();
    let current = true;
    let objectUrl = null;
    void (async () => {
      try {
        const response = await fetch(selected.url, {
          credentials: 'same-origin',
          headers: { 'ngrok-skip-browser-warning': '1' },
          signal: controller.signal,
        });
        const type = response.headers.get('content-type') ?? '';
        if (!response.ok) {
          throw new Error(`The reference could not be loaded (HTTP ${response.status}).`);
        }
        if (selected.kind === 'diagram') {
          if (!type.includes('image/svg+xml')) throw new Error('The server did not return an SVG diagram.');
          const image = await response.blob();
          if (!current) return;
          objectUrl = URL.createObjectURL(image);
          imageSource = objectUrl;
        } else {
          if (!type.includes('text/markdown') && !type.includes('text/plain')) {
            throw new Error('The server did not return a research document.');
          }
          const markdown = await response.text();
          if (!current) return;
          documentHtml = safeMarkdownHtml(markdown);
          status = 'ready';
        }
      } catch (failure) {
        if (!current || controller.signal.aborted) return;
        error = failure instanceof Error ? failure.message : 'The reference could not be loaded.';
        status = 'error';
      }
    })();
    return () => {
      current = false;
      controller.abort();
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  });

  function imageLoaded(event) {
    if (open && reference?.kind === 'diagram' && event.currentTarget.getAttribute('src') === imageSource) {
      status = 'ready';
    }
  }

  function imageFailed(event) {
    if (open && reference?.kind === 'diagram' && event.currentTarget.getAttribute('src') === imageSource) {
      error = 'The diagram could not be loaded. You can try opening the source separately.';
      status = 'error';
    }
  }
</script>

<Dialog.Root bind:open>
  <Dialog.Content class="flex h-[90dvh] w-[calc(100%-1rem)] max-w-[calc(100%-1rem)] flex-col gap-0 overflow-hidden border-slate-700 bg-slate-950 p-0 text-slate-100 sm:max-w-5xl" aria-describedby="guide-reference-description">
    <Dialog.Header class="shrink-0 border-b border-slate-800 p-4 pr-12 sm:p-5 sm:pr-14">
      <Dialog.Title class="text-lg font-semibold text-slate-100">{reference?.title ?? 'Reference'}</Dialog.Title>
      <Dialog.Description id="guide-reference-description" class="mt-1 text-sm leading-5 text-slate-400">
        {reference?.note ?? 'Read-only reference. Closing it keeps your workflow and selected step.'}
      </Dialog.Description>
      {#if reference}
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <a href={reference.url} download class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">
            <Download class="size-3.5" aria-hidden="true" /> Download source
          </a>
          {#if reference.kind === 'diagram'}
            <a href={reference.url} target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs font-semibold text-slate-100 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">
              <ExternalLink class="size-3.5" aria-hidden="true" /> Open separately
            </a>
            <div class="flex items-center gap-1" aria-label="Diagram zoom">
              <Button type="button" variant="outline" class="min-h-10 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" aria-label="Zoom out" disabled={zoom <= 50} onclick={() => (zoom = Math.max(50, zoom - 25))}><Minus class="size-3.5" aria-hidden="true" /></Button>
              <span class="min-w-12 text-center text-xs tabular-nums">{zoom}%</span>
              <Button type="button" variant="outline" class="min-h-10 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" aria-label="Zoom in" disabled={zoom >= 200} onclick={() => (zoom = Math.min(200, zoom + 25))}><Plus class="size-3.5" aria-hidden="true" /></Button>
              <Button type="button" variant="outline" class="min-h-10 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" aria-label="Fit diagram width" onclick={() => (zoom = 100)}><RotateCcw class="size-3.5" aria-hidden="true" /></Button>
            </div>
          {/if}
        </div>
      {/if}
    </Dialog.Header>

    <div id="guide-reference-content" class="relative min-h-0 flex-1 overflow-auto p-4 sm:p-6" aria-busy={status === 'loading'}>
      {#if status === 'loading'}
        <p class="mb-4 text-sm text-slate-300" role="status">Loading reference…</p>
      {/if}
      {#if status === 'error'}
        <p class="rounded-lg border border-rose-500/30 bg-rose-500/10 p-4 text-sm leading-6 text-rose-200" role="alert">{error}</p>
      {:else if reference?.kind === 'diagram' && imageSource}
        {#key imageSource}
          <img src={imageSource} alt={reference.title} class="block h-auto max-w-none rounded bg-white" style:width={`${zoom}%`} onload={imageLoaded} onerror={imageFailed} />
        {/key}
      {:else if status === 'ready'}
        <div class="guide-reference-markdown">{@html documentHtml}</div>
      {/if}
    </div>
    <div class="shrink-0 border-t border-slate-800 p-3 text-right sm:px-5">
      <Button type="button" variant="outline" class="min-h-10 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" onclick={() => (open = false)}>Close reference</Button>
    </div>
  </Dialog.Content>
</Dialog.Root>

<style>
  :global(.guide-reference-markdown) { color: #cbd5e1; font-size: 0.9375rem; line-height: 1.7; overflow-wrap: anywhere; }
  :global(.guide-reference-markdown h1), :global(.guide-reference-markdown h2), :global(.guide-reference-markdown h3), :global(.guide-reference-markdown h4) { color: #f1f5f9; font-weight: 600; line-height: 1.35; margin: 1.75rem 0 0.75rem; }
  :global(.guide-reference-markdown h1) { font-size: 1.5rem; }
  :global(.guide-reference-markdown h2) { font-size: 1.25rem; }
  :global(.guide-reference-markdown h3) { font-size: 1.125rem; }
  :global(.guide-reference-markdown p) { max-width: 75ch; margin: 0.75rem 0; }
  :global(.guide-reference-markdown ul), :global(.guide-reference-markdown ol) { padding-left: 1.5rem; margin: 0.75rem 0; }
  :global(.guide-reference-markdown ul) { list-style: disc; }
  :global(.guide-reference-markdown ol) { list-style: decimal; }
  :global(.guide-reference-markdown li) { margin: 0.375rem 0; }
  :global(.guide-reference-markdown table) { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; margin: 1rem 0; }
  :global(.guide-reference-markdown th), :global(.guide-reference-markdown td) { border: 1px solid #334155; padding: 0.5rem 0.75rem; vertical-align: top; }
  :global(.guide-reference-markdown th) { background: #1e293b; color: #f1f5f9; font-weight: 600; }
  :global(.guide-reference-markdown a) { color: #93c5fd; text-decoration: underline; text-underline-offset: 0.2em; }
  :global(.guide-reference-markdown a:focus-visible) { outline: 2px solid #60a5fa; outline-offset: 2px; }
  :global(.guide-reference-markdown blockquote) { border-left: 1px solid #475569; padding-left: 1rem; margin: 1rem 0; }
  :global(.guide-reference-markdown pre) { overflow-x: auto; padding: 1rem; background: #0f172a; border-radius: 0.5rem; }
  :global(.guide-reference-markdown code) { font-family: var(--font-mono); font-size: 0.875em; }
  :global(.guide-reference-markdown hr) { border-color: #334155; margin: 1.5rem 0; }
</style>
