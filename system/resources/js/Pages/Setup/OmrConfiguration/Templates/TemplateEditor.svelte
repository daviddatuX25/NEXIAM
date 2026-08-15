<script>
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { router } from '@inertiajs/svelte';
  import { error as toastError, success as toastSuccess } from '@/lib/toast.js';

  let {
    areas = [],
    paper_options = [],
    engine = {},
    errors = {},
    draft_version = null,
    draft_refusal = null,
  } = $props();

  const defaultPaper =
    paper_options.find((p) => p.value === 'long')?.value ?? paper_options[0]?.value ?? 'long';

  let templateCode = $state('');
  let templateName = $state('');
  let saving = $state(false);
  let previewing = $state(false);
  let localError = $state('');
  let serverError = $state('');
  let locked = $state(false);
  let blockSeq = $state(0);

  let overlay = $state({
    title: '',
    header_lines: [],
    footer_lines: [],
    name_box_label: '',
    per_page: [],
  });

  function pad(n) {
    return String(n).padStart(3, '0');
  }

  function applicantBlock() {
    return {
      key: 'applicant',
      label: 'ApplicantNumber',
      fieldType: 'QTYPE_INT',
      prefix: 'APPLICANT_NUMBER',
      from: 1,
      questions: 10,
      origin: [200, 260],
      bubblesGap: 60,
      labelsGap: 66,
    };
  }

  function birthdateBlock() {
    return {
      key: 'Birthdate',
      label: 'BIRTHDATE_1..6',
      birthdate: true,
      fieldType: 'QTYPE_INT',
      prefix: 'BIRTHDATE',
      from: 1,
      questions: 6,
      origin: [1000, 260],
      bubblesGap: 60,
      labelsGap: 66,
    };
  }

  function createPage(paper) {
    return {
      paper,
      newBlockAreaId: null,
      overlayOpen: false,
      fieldBlocks: [applicantBlock()],
    };
  }

  let pages = $state([createPage(defaultPaper)]);

  function paperOf(page) {
    return (
      paper_options.find((p) => p.value === page.paper) ??
      paper_options[0] ?? { value: 'long', label: 'Long', dimensions: [2060, 3144] }
    );
  }

  function paperForDimensions(dims) {
    if (!dims) return defaultPaper;
    const match = paper_options.find((p) => String(p.dimensions) === String(dims));
    return match ? match.value : defaultPaper;
  }

  function blockLabel(block) {
    if (block.fieldType === 'QTYPE_INT') {
      return block.birthdate ? 'BIRTHDATE_1..6' : 'ApplicantNumber';
    }
    const from = block.from ?? 1;
    const questions = block.questions ?? 10;
    return `${block.prefix}_Q_${pad(from)}..${pad(from + questions - 1)}`;
  }

  function hydrateBlocks(fieldBlocks) {
    const blocks = [];
    for (const [key, spec] of Object.entries(fieldBlocks ?? {})) {
      const type = spec?.fieldType ?? 'QTYPE_MCQ4';
      if (type === 'QTYPE_INT') {
        const labels = (spec?.fieldLabels ?? [])[0] ?? '';
        const template = /^BIRTHDATE/.test(labels) ? birthdateBlock() : applicantBlock();
        template.origin = [...(spec.origin ?? template.origin)];
        template.bubblesGap = Number(spec.bubblesGap ?? template.bubblesGap);
        template.labelsGap = Number(spec.labelsGap ?? template.labelsGap);
        blocks.push(template);
      } else {
        const m = /^([A-Z0-9]+)_Q_(\d+)\.\.(\d+)$/.exec((spec?.fieldLabels ?? [])[0] ?? '');
        const prefix = m ? m[1] : String((spec?.fieldLabels ?? [])[0] ?? key).split('_Q_')[0];
        const from = m ? Number(m[2]) : 1;
        const to = m ? Number(m[3]) : null;
        const questions = to != null && to >= from ? to - from + 1 : 10;
        const area = areas.find((a) => a.code === prefix);
        const label = `${prefix}_Q_${pad(from)}..${pad(to ?? from + questions - 1)}`;
        blocks.push({
          key: label,
          label,
          area_id: area?.id ?? null,
          area_code: prefix,
          fieldType: type,
          prefix,
          from,
          questions,
          origin: [...(spec.origin ?? [200, 1000])],
          bubblesGap: Number(spec.bubblesGap ?? 66),
          labelsGap: Number(spec.labelsGap ?? 80),
        });
      }
    }
    return blocks;
  }

  function hydrate(draft) {
    templateCode = draft.code ?? '';
    templateName = draft.name ?? '';
    const json = draft.template_json ?? {};
    const rawPages =
      Array.isArray(json.pages) && json.pages.length > 0
        ? json.pages
        : [{ page_no: 1, pageDimensions: json.pageDimensions, fieldBlocks: json.fieldBlocks }];
    pages = rawPages.map((raw) => ({
      paper: paperForDimensions(raw?.pageDimensions),
      newBlockAreaId: null,
      overlayOpen: false,
      fieldBlocks: hydrateBlocks(raw?.fieldBlocks),
    }));
    const ov = draft.overlay ?? {};
    overlay = {
      title: ov.title ?? '',
      header_lines: Array.isArray(ov.header_lines) ? [...ov.header_lines] : [],
      footer_lines: Array.isArray(ov.footer_lines) ? [...ov.footer_lines] : [],
      name_box_label: ov.name_box_label ?? '',
      per_page: Array.isArray(ov.per_page)
        ? ov.per_page.map((p) => ({ page_no: Number(p.page_no), note: p.note ?? '' }))
        : [],
    };
  }

  $effect(() => {
    if (draft_refusal) locked = true;
    if (draft_version) hydrate(draft_version);
  });

  $effect(() => {
    const first = errors.omr_builder ?? Object.values(errors)[0];
    if (first && first !== serverError) {
      serverError = first;
      toastError(first);
    }
  });

  function sanitizePrefix(code) {
    const cleaned = (code ?? '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    return cleaned || 'AREA';
  }

  function addPage() {
    if (locked || pages.length >= 4) return;
    pages.push(createPage(pages[0].paper));
  }

  function removePage(index) {
    if (locked || pages.length <= 1) return;
    pages.splice(index, 1);
  }

  function removeBlock(page, key) {
    if (locked) return;
    page.fieldBlocks = page.fieldBlocks.filter((b) => b.key !== key);
  }

  function setNum(block, field, value) {
    block[field] = Number(value);
  }

  function hasBirthdate(page) {
    return page.fieldBlocks.some((b) => b.birthdate);
  }

  // ---- Layout engine: margin-safe block placement (mirrors the renderer) ----
  // The printer reserves an 80px geometry margin on every side. A block's
  // bounding box must stay inside that margin on EVERY page, and blocks must
  // not overlap. New question blocks auto-place into a v4-style 4-column
  // band; if nothing fits, the add is refused with a typed reason.

  const BUBBLE = 56; // canonical bubble size emitted in template.json
  const MARGIN = 80; // renderer inset (mm-equivalent geometry units)
  const COLUMN_X = [180, 620, 1060, 1500]; // v4-compatible column origins

  function blockBounds(block) {
    if (block.fieldType === 'QTYPE_INT') {
      const cols = block.birthdate ? 6 : 10;
      return {
        w: cols * Number(block.labelsGap) + BUBBLE,
        h: 10 * Number(block.bubblesGap) + BUBBLE,
      };
    }
    return {
      w: 4 * Number(block.bubblesGap) + BUBBLE,
      h: Math.max(1, Number(block.questions)) * Number(block.labelsGap) + BUBBLE,
    };
  }

  function rectsOverlap(a, b) {
    return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
  }

  function bottomLimit(paper) {
    // The renderer reserves the bottom margin strip for the calibration bar
    // plus, when footer text is set, a footer zone above it. Blocks must end
    // above that so nothing collides on ANY page.
    const dims = paper?.dimensions ?? [2060, 3144];
    const footerReserve = overlay.footer_lines.filter((l) => l.trim()).length > 0 ? 150 : 0;
    return dims[1] - MARGIN - footerReserve;
  }

  function pageIssues(page, paper) {
    const dims = paper?.dimensions ?? [2060, 3144];
    const issues = [];
    const boxes = page.fieldBlocks.map((b) => {
      const { w, h } = blockBounds(b);
      return { block: b, x: Number(b.origin[0]), y: Number(b.origin[1]), w, h };
    });

    for (const box of boxes) {
      const reasons = [];
      if (box.x < MARGIN || box.y < MARGIN || box.x + box.w > dims[0] - MARGIN || box.y + box.h > bottomLimit(paper)) {
        reasons.push('extends past the printable margin');
      }
      for (const other of boxes) {
        if (other === box) continue;
        if (rectsOverlap(box, other)) {
          reasons.push(`overlaps ${other.block.birthdate ? 'birthdate grid' : other.block.label}`);
        }
      }
      if (reasons.length > 0) issues.push({ block: box.block, box, reasons });
    }

    return issues;
  }

  function placeBlock(page, block) {
    const { w, h } = blockBounds(block);
    const dims = paperOf(page).dimensions;
    const boxes = page.fieldBlocks
      .filter((b) => b !== block)
      .map((b) => {
        const { w: bw, h: bh } = blockBounds(b);
        return { x: Number(b.origin[0]), y: Number(b.origin[1]), w: bw, h: bh };
      });

    const candidateY = [900];
    for (const b of boxes) candidateY.push(Math.round(b.y + b.h + 120));

    for (const x of COLUMN_X) {
      for (const y of candidateY) {
        const box = { x, y, w, h };
        const outOfBounds = x < MARGIN || y < MARGIN || x + w > dims[0] - MARGIN || y + h > bottomLimit(paperOf(page));
        if (outOfBounds) continue;
        const collision = boxes.some((b) => rectsOverlap(box, b));
        if (!collision) return [x, y];
      }
    }

    return null;
  }

  function addBlock(page) {
    if (locked) return;
    const area = areas.find((a) => String(a.id) === String(page.newBlockAreaId));
    if (!area) return;
    blockSeq += 1;
    const prefix = sanitizePrefix(area.code);
    const block = {
      key: `qb-${blockSeq}`,
      label: `${prefix}_Q_001..010`,
      area_id: area.id,
      area_code: area.code,
      fieldType: 'QTYPE_MCQ4',
      prefix,
      from: 1,
      questions: 10,
      origin: [200, 1000 + (page.fieldBlocks.length - 1) * 1400],
      bubblesGap: 66,
      labelsGap: 80,
    };

    const placed = placeBlock(page, block);
    if (placed === null) {
      toastError(`Page ${pages.indexOf(page) + 1} has no room for another block inside the printable margin — add a page instead.`);
      return;
    }

    block.origin = placed;
    page.fieldBlocks.push(block);
    page.newBlockAreaId = null;
  }

  function toggleBirthdate(page) {
    if (locked) return;
    const idx = page.fieldBlocks.findIndex((b) => b.birthdate);
    if (idx >= 0) {
      page.fieldBlocks.splice(idx, 1);
    } else {
      page.fieldBlocks.push(birthdateBlock());
    }
  }

  function pageNote(pageIndex) {
    return overlay.per_page.find((p) => p.page_no === pageIndex + 1)?.note ?? '';
  }

  function setPageNote(pageIndex, value) {
    const existing = overlay.per_page.find((p) => p.page_no === pageIndex + 1);
    if (existing) {
      existing.note = value;
    } else if (value !== '') {
      overlay.per_page.push({ page_no: pageIndex + 1, note: value });
    }
  }

  function assembleTemplateJson() {
    const topLevelPageDimensions = paperOf(pages[0]).dimensions;
    return {
      bubbleDimensions: [56, 56],
      pageDimensions: topLevelPageDimensions,
      pages: pages.map((page, index) => {
        const fieldBlocks = {};
        for (const block of page.fieldBlocks) {
          const isApplicant = block.fieldType === 'QTYPE_INT' && !block.birthdate;
          const label = blockLabel(block);
          fieldBlocks[label] = {
            fieldType: block.fieldType,
            ...(block.fieldType === 'QTYPE_INT' ? {} : { direction: 'horizontal' }),
            fieldLabels: [isApplicant ? 'APPLICANT_NUMBER_1..10' : label],
            origin: [Number(block.origin[0]), Number(block.origin[1])],
            bubblesGap: Number(block.bubblesGap),
            labelsGap: Number(block.labelsGap),
          };
        }
        return {
          page_no: index + 1,
          pageDimensions: paperOf(page).dimensions,
          fieldBlocks,
          preProcessors: [{ name: 'CropPage', options: { morphKernel: [5, 5] } }],
        };
      }),
    };
  }

  function assembleOverlay() {
    return {
      title: overlay.title,
      header_lines: overlay.header_lines,
      footer_lines: overlay.footer_lines,
      name_box_label: overlay.name_box_label,
      per_page: overlay.per_page.map((p) => ({ page_no: p.page_no, note: p.note })),
    };
  }

  function submit() {
    if (saving || locked) return;
    localError = '';
    if (!/^[A-Z0-9-]{3,32}$/.test(templateCode.trim())) {
      localError = 'Template code must be 3–32 characters using only uppercase letters, digits, and dashes.';
      return;
    }
    if (!templateName.trim()) {
      localError = 'Template name is required.';
      return;
    }
    saving = true;
    router.post(
      '/setup/omr/templates',
      {
        template_code: templateCode.trim(),
        template_name: templateName.trim(),
        template_json: JSON.stringify(assembleTemplateJson()),
        overlay: assembleOverlay(),
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          saving = false;
          toastSuccess('Template saved.');
        },
        onError: (errs) => {
          saving = false;
          localError = errs.omr_builder ?? Object.values(errs)[0] ?? 'Failed to save template.';
        },
      },
    );
  }

  function csrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el instanceof HTMLMetaElement ? el.content : '';
  }

  async function previewPdf() {
    if (previewing || locked) return;
    previewing = true;
    try {
      const res = await fetch('/setup/omr/templates/preview-draft', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          template_json: JSON.stringify(assembleTemplateJson()),
          overlay: assembleOverlay(),
        }),
      });
      if (!res.ok) {
        let message = 'Preview failed.';
        try {
          const body = await res.json();
          if (body?.error) message = body.error;
          else if (body?.message) message = body.message;
          else if (body?.errors) message = Object.values(body.errors)[0];
        } catch {
          /* non-JSON error body */
        }
        toastError(message);
        return;
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      window.open(url, '_blank');
    } catch (err) {
      toastError(err?.message ?? 'Preview failed.');
    } finally {
      previewing = false;
    }
  }

  const numberInput =
    'w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30';
  const selectInput =
    'h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<section class="space-y-6 p-6">
    {#if draft_refusal}
      <p class="rounded bg-red-50 p-3 text-sm text-red-800">{draft_refusal}</p>
    {/if}
    {#if serverError}
      <p class="rounded bg-red-50 p-3 text-sm text-red-800">{serverError}</p>
    {/if}
    {#if localError}
      <p class="rounded bg-red-50 p-3 text-sm text-red-800">{localError}</p>
    {/if}

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
      <div class="min-w-0 space-y-6">
        <div class="rounded-lg border p-4">
          <h2 class="mb-3 text-lg font-semibold">Template details</h2>
          {#if draft_version}
            <p class="mb-3 text-xs text-muted-foreground">
              Editing an existing draft — saving registers a new draft version.
            </p>
          {/if}
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="template_code">Template code</Label>
              <Input
                id="template_code"
                placeholder="e.g. REAL-OMR-2026"
                value={templateCode}
                oninput={(e) => (templateCode = e.currentTarget.value)}
                disabled={locked}
                required
              />
              <p class="text-xs text-muted-foreground">Uppercase letters, digits, dashes — 3 to 32 characters.</p>
            </div>
            <div class="space-y-2">
              <Label for="template_name">Template name</Label>
              <Input
                id="template_name"
                placeholder="e.g. Real OMR 2026"
                value={templateName}
                oninput={(e) => (templateName = e.currentTarget.value)}
                disabled={locked}
                required
              />
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <h2 class="text-lg font-semibold">Pages</h2>
          {#each pages as page, pageIndex (pageIndex)}
            <div class="rounded-lg border p-4">
              <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                  <h3 class="font-semibold">Page {pageIndex + 1}</h3>
                  <div class="space-y-1">
                    <Label for="paper-{pageIndex}" class="text-xs text-muted-foreground">Paper</Label>
                    <select id="paper-{pageIndex}" class={selectInput} bind:value={page.paper} disabled={locked}>
                      {#each paper_options as option (option.value)}
                        <option value={option.value}>{option.label}</option>
                      {/each}
                    </select>
                  </div>
                  <label class="flex items-center gap-2 text-sm">
                    <input
                      type="checkbox"
                      checked={hasBirthdate(page)}
                      onchange={() => toggleBirthdate(page)}
                      disabled={locked}
                    />
                    Birthdate grid (6 digits)
                  </label>
                </div>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  disabled={locked || pages.length <= 1}
                  onclick={() => removePage(pageIndex)}
                >
                  Remove page
                </Button>
              </div>

              <div class="space-y-3">
                {#each page.fieldBlocks as block (block.key)}
                  <div class="rounded-lg border border-dashed p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                      <div class="flex items-center gap-2">
                        <span class="font-mono text-sm font-semibold">{blockLabel(block)}</span>
                        <span class="text-xs text-muted-foreground">{block.fieldType}</span>
                        {#if block.area_code}
                          <span class="text-xs text-muted-foreground">({block.area_code})</span>
                        {/if}
                      </div>
                      {#if block.fieldType !== 'QTYPE_INT'}
                        <Button type="button" variant="ghost" size="sm" onclick={() => removeBlock(page, block.key)}>
                          Remove
                        </Button>
                      {/if}
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5">
                      <div class="space-y-1">
                        <Label class="text-xs text-muted-foreground">Origin X</Label>
                        <input
                          type="number"
                          class={numberInput}
                          value={block.origin[0]}
                          oninput={(e) => setNum(block.origin, 0, e.currentTarget.value)}
                          disabled={locked}
                        />
                      </div>
                      <div class="space-y-1">
                        <Label class="text-xs text-muted-foreground">Origin Y</Label>
                        <input
                          type="number"
                          class={numberInput}
                          value={block.origin[1]}
                          oninput={(e) => setNum(block.origin, 1, e.currentTarget.value)}
                          disabled={locked}
                        />
                      </div>
                      <div class="space-y-1">
                        <Label class="text-xs text-muted-foreground">Bubbles gap</Label>
                        <input
                          type="number"
                          class={numberInput}
                          value={block.bubblesGap}
                          oninput={(e) => setNum(block, 'bubblesGap', e.currentTarget.value)}
                          disabled={locked}
                        />
                      </div>
                      <div class="space-y-1">
                        <Label class="text-xs text-muted-foreground">Labels gap</Label>
                        <input
                          type="number"
                          class={numberInput}
                          value={block.labelsGap}
                          oninput={(e) => setNum(block, 'labelsGap', e.currentTarget.value)}
                          disabled={locked}
                        />
                      </div>
                      {#if block.fieldType !== 'QTYPE_INT'}
                        <div class="space-y-1">
                          <Label class="text-xs text-muted-foreground">Questions (1–100)</Label>
                          <input
                            type="number"
                            min="1"
                            max="100"
                            class={numberInput}
                            value={block.questions}
                            oninput={(e) => setNum(block, 'questions', e.currentTarget.value)}
                            disabled={locked}
                          />
                        </div>
                      {:else}
                        <div class="space-y-1">
                          <Label class="text-xs text-muted-foreground">Digits</Label>
                          <input
                            type="text"
                            class={numberInput}
                            value={block.birthdate ? 'BIRTHDATE_1..6' : 'APPLICANT_NUMBER_1..10'}
                            disabled
                          />
                        </div>
                      {/if}
                    </div>
                  </div>
                {/each}
              </div>

              <div class="mt-3 rounded-lg border border-dashed p-3">
                <div class="flex flex-wrap items-end gap-3">
                  <div class="min-w-40 flex-1 space-y-1">
                    <Label class="text-xs text-muted-foreground">Area</Label>
                    <select class={selectInput} bind:value={page.newBlockAreaId} disabled={locked}>
                      <option value="">Choose an area…</option>
                      {#each areas as area (area.id)}
                        <option value={area.id}>{area.code} — {area.name}</option>
                      {/each}
                    </select>
                  </div>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={locked || !page.newBlockAreaId}
                    onclick={() => addBlock(page)}
                  >
                    Add question block
                  </Button>
                </div>
              </div>

              <div class="mt-3 rounded-lg border border-dashed p-3">
                <button
                  type="button"
                  class="flex w-full items-center justify-between text-sm font-medium"
                  onclick={() => (page.overlayOpen = !page.overlayOpen)}
                  disabled={locked}
                >
                  <span>Sheet text / overlay</span>
                  <span class="text-muted-foreground">{page.overlayOpen ? '−' : '+'}</span>
                </button>
                {#if page.overlayOpen}
                  <div class="mt-3 grid gap-3">
                    <div class="space-y-1">
                      <Label class="text-xs text-muted-foreground">Title (printed on every page)</Label>
                      <Input
                        value={overlay.title}
                        oninput={(e) => (overlay.title = e.currentTarget.value)}
                        disabled={locked}
                      />
                    </div>
                    <div class="space-y-1">
                      <Label class="text-xs text-muted-foreground">Header lines (one per line, max 4)</Label>
                      <textarea
                        class={numberInput}
                        rows="2"
                        value={overlay.header_lines.join('\n')}
                        oninput={(e) => (overlay.header_lines = e.currentTarget.value.split('\n'))}
                        disabled={locked}
                      ></textarea>
                    </div>
                    <div class="space-y-1">
                      <Label class="text-xs text-muted-foreground">Footer lines (one per line, max 3)</Label>
                      <textarea
                        class={numberInput}
                        rows="2"
                        value={overlay.footer_lines.join('\n')}
                        oninput={(e) => (overlay.footer_lines = e.currentTarget.value.split('\n'))}
                        disabled={locked}
                      ></textarea>
                    </div>
                    <div class="space-y-1">
                      <Label class="text-xs text-muted-foreground">Name box label</Label>
                      <Input
                        value={overlay.name_box_label}
                        oninput={(e) => (overlay.name_box_label = e.currentTarget.value)}
                        disabled={locked}
                      />
                    </div>
                    <div class="space-y-1">
                      <Label class="text-xs text-muted-foreground">Note for this page</Label>
                      <Input
                        value={pageNote(pageIndex)}
                        oninput={(e) => setPageNote(pageIndex, e.currentTarget.value)}
                        disabled={locked}
                      />
                    </div>
                  </div>
                {/if}
              </div>
            </div>
          {/each}

          <Button type="button" variant="outline" disabled={locked || pages.length >= 4} onclick={addPage}>
            Add page
          </Button>
        </div>

        <div class="flex items-center gap-3">
          <Button type="button" variant="outline" onclick={previewPdf} disabled={locked || previewing}>
            {previewing ? 'Rendering…' : 'Preview PDF'}
          </Button>
          <Button onclick={submit} disabled={locked || saving}>{saving ? 'Saving…' : draft_version ? 'Save changes' : 'Create template'}</Button>
        </div>
        <p class="text-xs text-muted-foreground">
          Preview renders the current unsaved state as a real PDF in a new tab.
        </p>
      </div>

      <aside class="space-y-4">
        <div class="rounded-lg border p-4">
          <h2 class="mb-2 text-sm font-semibold text-muted-foreground">Engine pin</h2>
          <dl class="space-y-1 text-sm">
            <div class="flex justify-between gap-3">
              <dt class="text-muted-foreground">Engine</dt>
              <dd class="font-mono">{engine.name ?? '—'}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-muted-foreground">Commit</dt>
              <dd class="font-mono">{engine.commit?.slice(0, 8) ?? '—'}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-muted-foreground">Patch</dt>
              <dd class="font-mono break-all">{engine.patch_identity ?? '—'}</dd>
            </div>
          </dl>
        </div>

        <div class="space-y-4">
          <h2 class="text-lg font-semibold">Preview</h2>
          {#each pages as page, pageIndex (pageIndex)}
            {@const paper = paperOf(page)}
            {@const previewTitle = (overlay.title || templateName || 'Answer sheet').toUpperCase()}
            {@const issues = pageIssues(page, paper)}
            <div class="rounded-lg border p-3">
              <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium text-muted-foreground">
                  Page {pageIndex + 1} — {paper.label}
                </p>
                {#if issues.length > 0}
                  <p class="text-xs font-semibold text-red-600">
                    {issues.length} layout issue{issues.length === 1 ? '' : 's'} — outside printable margin or overlapping
                  </p>
                {:else}
                  <p class="text-xs font-semibold text-emerald-600">Inside printable margins</p>
                {/if}
              </div>
              <svg
                viewBox={`0 0 ${paper.dimensions[0]} ${paper.dimensions[1]}`}
                class="w-full rounded border bg-white dark:bg-slate-950/60"
              >
                <rect
                  x="0"
                  y="0"
                  width={paper.dimensions[0]}
                  height={paper.dimensions[1]}
                  fill="none"
                  stroke="currentColor"
                  stroke-opacity="0.15"
                />
                <rect
                  x={MARGIN}
                  y={MARGIN}
                  width={paper.dimensions[0] - 2 * MARGIN}
                  height={paper.dimensions[1] - 2 * MARGIN}
                  fill="none"
                  stroke="currentColor"
                  stroke-opacity="0.2"
                  stroke-dasharray="14 10"
                />
                <text
                  x={paper.dimensions[0] / 2}
                  y="110"
                  font-size="54"
                  text-anchor="middle"
                  class="fill-muted-foreground"
                >
                  {previewTitle}
                </text>
                {#each overlay.header_lines.filter((l) => l.trim()) as line, i (i)}
                  <text
                    x={paper.dimensions[0] / 2}
                    y={170 + i * 46}
                    font-size="30"
                    text-anchor="middle"
                    class="fill-muted-foreground"
                  >
                    {line}
                  </text>
                {/each}
                {#each page.fieldBlocks as block (block.key)}
                  {@const ox = block.origin[0]}
                  {@const oy = block.origin[1]}
                  {@const bb = blockBounds(block)}
                  {@const issue = issues.find((i) => i.block === block)}
                  {#if issue}
                    <rect
                      x={ox - 8}
                      y={oy - 96}
                      width={bb.w + 16}
                      height={bb.h + 104}
                      fill="none"
                      stroke="red"
                      stroke-width="6"
                      stroke-dasharray="20 12"
                      rx="12"
                    />
                  {/if}
                  {#if block.fieldType === 'QTYPE_INT'}
                    {@const cols = block.birthdate ? 6 : 10}
                    <text x={ox} y={oy - 84} font-size="30" class="fill-muted-foreground">{blockLabel(block)}</text>
                    {#each Array(cols) as _, c}
                      <text x={ox + c * block.labelsGap} y={oy - 34} font-size="30" text-anchor="middle" class="fill-muted-foreground">
                        {c + 1}
                      </text>
                    {/each}
                    {#each Array(10) as _, r}
                      <text x={ox - 26} y={oy + r * block.bubblesGap + 10} font-size="28" text-anchor="end" class="fill-muted-foreground">
                        {r}
                      </text>
                      {#each Array(cols) as _, c}
                        <circle
                          cx={ox + c * block.labelsGap}
                          cy={oy + r * block.bubblesGap}
                          r="16"
                          fill="currentColor"
                          opacity="0.55"
                        />
                      {/each}
                    {/each}
                  {:else}
                    <text x={ox} y={oy - 84} font-size="30" class="fill-muted-foreground">{blockLabel(block)}</text>
                    {#each ['A', 'B', 'C', 'D'] as letter, c (letter)}
                      <text x={ox + c * block.bubblesGap} y={oy - 34} font-size="30" text-anchor="middle" class="fill-muted-foreground">
                        {letter}
                      </text>
                    {/each}
                    {#each Array(Math.max(0, block.questions)) as _, r}
                      <text x={ox - 26} y={oy + r * block.labelsGap + 10} font-size="28" text-anchor="end" class="fill-muted-foreground">
                        {r + 1}
                      </text>
                      {#each [0, 1, 2, 3] as c}
                        <circle
                          cx={ox + c * block.bubblesGap}
                          cy={oy + r * block.labelsGap}
                          r="16"
                          fill="currentColor"
                          opacity="0.55"
                        />
                      {/each}
                    {/each}
                  {/if}
                {/each}
              </svg>
            </div>
          {/each}
        </div>
      </aside>
    </div>
  </section>
