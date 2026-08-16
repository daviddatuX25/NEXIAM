<script>
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { router } from '@inertiajs/svelte';
  import { blockBounds as canonicalBlockBounds, blockValues as canonicalBlockValues, bubbleCenter, DEFAULT_BUBBLE_DIMENSIONS, isVerticalBlock, labelBaseline, mmToPx, ptToPx, visualBlockBounds } from '@/lib/omrPreviewGeometry.js';
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
  let bubbleDimensions = $state([...DEFAULT_BUBBLE_DIMENSIONS]);

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
        if (spec.direction) template.direction = spec.direction;
        template.bubblesGap = Number(spec.bubblesGap ?? template.bubblesGap);
        template.labelsGap = Number(spec.labelsGap ?? template.labelsGap);
        if (Array.isArray(spec.bubbleValues) && spec.bubbleValues.length > 0) template.bubbleValues = [...spec.bubbleValues];
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
          direction: spec.direction ?? 'horizontal',
          prefix,
          from,
          questions,
          origin: [...(spec.origin ?? [200, 1000])],
          bubblesGap: Number(spec.bubblesGap ?? 66),
          labelsGap: Number(spec.labelsGap ?? 80),
          ...(Array.isArray(spec.bubbleValues) && spec.bubbleValues.length > 0 ? { bubbleValues: [...spec.bubbleValues] } : {}),
        });
      }
    }
    return blocks;
  }

  function hydrate(draft) {
    templateCode = draft.code ?? '';
    templateName = draft.name ?? '';
    const json = draft.template_json ?? {};
    bubbleDimensions = [...(json.bubbleDimensions ?? DEFAULT_BUBBLE_DIMENSIONS)];
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

  function setPaper(value) {
    if (locked) return;
    pages.forEach((page) => (page.paper = value));
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

  const MARGIN = 80; // renderer inset (mm-equivalent geometry units)
  const COLUMN_X = [180, 620, 1060, 1500]; // v4-compatible column origins

  function blockBounds(block) {
    return canonicalBlockBounds(block, bubbleDimensions);
  }

  function visualBounds(block) {
    const bounds = visualBlockBounds(block, bubbleDimensions);
    return { block, ...bounds };
  }

  function rectsOverlap(a, b) {
    return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
  }

  function bottomLimit(paper) {
    // The canonical solver reserves 230px for footer/calibration clearance
    // on every page, regardless of whether optional footer text is present.
    const dims = paper?.dimensions ?? [2060, 3144];
    return dims[1] - MARGIN - 230;
  }

  function pageIssues(page, paper) {
    const dims = paper?.dimensions ?? [2060, 3144];
    const issues = [];
    const boxes = page.fieldBlocks.map((block) => visualBounds(block));

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
    const dims = paperOf(page).dimensions;
    const boxes = page.fieldBlocks
      .filter((b) => b !== block)
      .map((b) => visualBounds(b));

    const candidateY = [900];
    for (const existing of page.fieldBlocks) {
      const bubble = blockBounds(existing);
      candidateY.push(Math.round(Number(existing.origin[1]) + bubble.h + 120));
    }

    for (const x of COLUMN_X) {
      for (const y of candidateY) {
        const candidate = visualBounds({ ...block, origin: [x, y] });
        const outOfBounds =
          candidate.x < MARGIN
          || candidate.y < MARGIN
          || candidate.x + candidate.w > dims[0] - MARGIN
          || candidate.y + candidate.h > bottomLimit(paperOf(page));
        if (outOfBounds) continue;
        const collision = boxes.some((existing) => rectsOverlap(candidate, existing));
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
      bubbleDimensions: [...bubbleDimensions],
      pageDimensions: topLevelPageDimensions,
      pages: pages.map((page, index) => {
        const fieldBlocks = {};
        for (const block of page.fieldBlocks) {
          const isApplicant = block.fieldType === 'QTYPE_INT' && !block.birthdate;
          const label = blockLabel(block);
          fieldBlocks[label] = {
            fieldType: block.fieldType,
            direction: block.direction ?? (block.fieldType === 'QTYPE_INT' ? 'vertical' : 'horizontal'),
            fieldLabels: [isApplicant ? 'APPLICANT_NUMBER_1..10' : label],
            origin: [Number(block.origin[0]), Number(block.origin[1])],
            bubblesGap: Number(block.bubblesGap),
            labelsGap: Number(block.labelsGap),
            ...(Array.isArray(block.bubbleValues) ? { bubbleValues: [...block.bubbleValues] } : {}),
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
      title: overlay.title.trim(),
      header_lines: nonEmptyLines(overlay.header_lines),
      footer_lines: nonEmptyLines(overlay.footer_lines),
      name_box_label: overlay.name_box_label.trim(),
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
    const layoutIssues = pages.flatMap((page, pageIndex) => pageIssues(page, paperOf(page)).map((issue) => ({ ...issue, pageIndex })));
    if (layoutIssues.length > 0) {
      const first = layoutIssues[0];
      localError = `Page ${first.pageIndex + 1}: ${first.reasons[0]}. Adjust the block origin or spacing before saving.`;
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
  function nonEmptyLines(lines) {
    return lines.filter((line) => String(line ?? '').trim() !== '');
  }

  function overlayMetrics(page, pageIndex, paper) {
    const pageHeightMm = (paper.dimensions[1] * 25.4) / 242;
    const insetMm = (80 / 242) * 25.4;
    const headerLines = nonEmptyLines(overlay.header_lines);
    const note = pageNote(pageIndex).trim();
    const pageLabelTopMm = 9 + headerLines.length * 5;
    const noteTopMm = pageLabelTopMm + 5;
    const afterHeaderMm = note ? noteTopMm + 5 : noteTopMm;
    const firstBlockTopMm = page.fieldBlocks.reduce(
      (min, block) => Math.min(min, (visualBounds(block).y * 25.4) / 242),
      Number.POSITIVE_INFINITY,
    );
    const nameBoxBottomMm = Number.isFinite(firstBlockTopMm) ? Math.max(0, firstBlockTopMm - (8 / 242) * 25.4) : null;
    const nameBoxTopCandidateMm = nameBoxBottomMm === null ? null : nameBoxBottomMm - 8;
    const topPlacementSafe = nameBoxTopCandidateMm !== null && nameBoxTopCandidateMm >= afterHeaderMm + 1;
    let nameBoxTopMm = nameBoxTopCandidateMm;
    let nameBoxX = mmToPx(insetMm + 2);
    let nameBoxWidth = mmToPx(80);

    // Match BundleRenderer: compact identity bands and full headers use the
    // unused upper-right area instead of hiding the manual fallback field.
    if (!topPlacementSafe || bubbleDimensions[0] <= 40) {
      nameBoxWidth = mmToPx(50);
      nameBoxX = mmToPx((paper.dimensions[0] * 25.4) / 242 - insetMm - 50 - 2);
      nameBoxTopMm = Math.max(afterHeaderMm + 2, nameBoxTopCandidateMm ?? insetMm + 2);
    }

    const footerAnchorMm = pageHeightMm - insetMm - 6;
    const footerLines = nonEmptyLines(overlay.footer_lines);
    const textY = (topMm, points) => mmToPx(topMm) + ptToPx(points) * 0.82;
    const frameTopMm = Math.max(
      insetMm,
      Math.min(
        Math.max(insetMm, afterHeaderMm - 2),
        Number.isFinite(firstBlockTopMm) ? firstBlockTopMm - 0.5 : Number.POSITIVE_INFINITY,
      ),
    );
    const frameBottomMm = pageHeightMm - insetMm;

    return {
      title: overlay.title.trim() || 'SYNTHETIC NEXIAM OMR ANSWER SHEET',
      headerLines,
      note,
      pageLabelTopMm,
      frameTopY: mmToPx(frameTopMm),
      frameBottomY: mmToPx(frameBottomMm),
      titleY: textY(4, 11),
      headerY: headerLines.map((_, index) => textY(9 + index * 5, 9)),
      pageLabelY: textY(pageLabelTopMm, 9),
      noteY: note ? textY(noteTopMm, 9) : null,
      nameBox: {
        label: overlay.name_box_label.trim() || 'NAME (PRINT):',
        x: nameBoxX,
        labelY: textY(nameBoxTopMm, 8),
        y: mmToPx(nameBoxTopMm + 3.5),
        width: nameBoxWidth,
        height: mmToPx(8),
      },
      footerY: footerLines.map((_, index) => textY(footerAnchorMm - (index + 1) * 5, 8)),
      footerLines,
      calibrationY: textY(pageHeightMm - insetMm + 1.5, 8),
      calibrationBarY: mmToPx(pageHeightMm - insetMm + 5.2),
      calibrationBarX: (paper.dimensions[0] - mmToPx(100)) / 2,
    };
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
                    <select id="paper-{pageIndex}" class={selectInput} value={pages[0].paper} onchange={(e) => setPaper(e.currentTarget.value)} disabled={locked}>
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
            {@const chrome = overlayMetrics(page, pageIndex, paper)}
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
                class="w-full rounded border bg-white text-slate-900 dark:bg-slate-950/60 dark:text-slate-100"
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
                  y={chrome.frameTopY}
                  width={paper.dimensions[0] - 2 * MARGIN}
                  height={chrome.frameBottomY - chrome.frameTopY}
                  fill="none"
                  stroke="currentColor"
                  stroke-width={mmToPx(0.7)}
                  stroke-opacity="0.65"
                />
                <text x={paper.dimensions[0] / 2} y={chrome.titleY} font-size={ptToPx(11)} font-weight="bold" text-anchor="middle">
                  {chrome.title}
                </text>
                {#each chrome.headerLines as line, i (i)}
                  <text x={paper.dimensions[0] / 2} y={chrome.headerY[i]} font-size={ptToPx(9)} font-weight="bold" text-anchor="middle">
                    {line}
                  </text>
                {/each}
                <text x={paper.dimensions[0] / 2} y={chrome.pageLabelY} font-size={ptToPx(9)} font-weight="bold" text-anchor="middle">
                  PAGE {pageIndex + 1} OF {pages.length}
                </text>
                {#if chrome.note}
                  <text x={paper.dimensions[0] / 2} y={chrome.noteY} font-size={ptToPx(9)} text-anchor="middle">
                    {chrome.note}
                  </text>
                {/if}
                {#if chrome.nameBox}
                  <text x={chrome.nameBox.x} y={chrome.nameBox.labelY} font-size={ptToPx(8)} font-weight="bold">
                    {chrome.nameBox.label}
                  </text>
                  <rect
                    x={chrome.nameBox.x}
                    y={chrome.nameBox.y}
                    width={chrome.nameBox.width}
                    height={chrome.nameBox.height}
                    fill="none"
                    stroke="currentColor"
                    stroke-width={mmToPx(0.35)}
                  />
                {/if}
                {#each page.fieldBlocks as block (block.key)}
                  {@const ox = Number(block.origin[0])}
                  {@const oy = Number(block.origin[1])}
                  {@const bb = blockBounds(block)}
                  {@const visual = visualBounds(block)}
                  {@const values = canonicalBlockValues(block)}
                  {@const vertical = isVerticalBlock(block)}
                  {@const columns = vertical ? (block.fieldType === 'QTYPE_INT' ? (block.birthdate ? 6 : 10) : Math.max(1, Number(block.questions ?? 0))) : values.length}
                  {@const rows = vertical ? values.length : Math.max(0, Number(block.questions ?? 0))}
                  {@const issue = issues.find((item) => item.block === block)}
                  {#if issue}
                    <rect
                      x={visual.x - 8}
                      y={visual.y - 8}
                      width={visual.w + 16}
                      height={visual.h + 16}
                      fill="none"
                      stroke="red"
                      stroke-width="6"
                      stroke-dasharray="20 12"
                      rx="12"
                    />
                  {/if}
                  {#each Array(columns) as _, c}
                    {@const center = bubbleCenter(block, 0, c, bubbleDimensions)}
                    <text x={center.x} y={labelBaseline(oy)} font-size={ptToPx(9)} text-anchor="middle">
                      {vertical ? (block.fieldType === 'QTYPE_INT' ? c + 1 : Number(block.from ?? 1) + c) : values[c]}
                    </text>
                  {/each}
                  {#each Array(rows) as _, r}
                    {@const rowCenter = bubbleCenter(block, r, 0, bubbleDimensions)}
                    <text
                      x={ox - (vertical ? 3 : 3.5)}
                      y={rowCenter.y + ptToPx(8) * 0.35}
                      font-size={ptToPx(8)}
                      text-anchor="end"
                    >
                      {vertical ? values[r] : Number(block.from ?? 1) + r}
                    </text>
                    {#each Array(columns) as _, c}
                      {@const center = bubbleCenter(block, r, c, bubbleDimensions)}
                      <ellipse
                        cx={center.x}
                        cy={center.y}
                        rx={bubbleDimensions[0] / 2}
                        ry={bubbleDimensions[1] / 2}
                        fill="none"
                        stroke="currentColor"
                        stroke-width={mmToPx(0.35)}
                      />
                    {/each}
                  {/each}
                {/each}
                {#each chrome.footerLines as line, i (i)}
                  <text x={paper.dimensions[0] / 2} y={chrome.footerY[i]} font-size={ptToPx(8)} text-anchor="middle">
                    {line}
                  </text>
                {/each}
                <text x={paper.dimensions[0] / 2} y={chrome.calibrationY} font-size={ptToPx(8)} text-anchor="middle">
                  Print at 100% scale · do NOT fit to page · calibration bar = 100mm
                </text>
                <rect
                  x={chrome.calibrationBarX}
                  y={chrome.calibrationBarY}
                  width={mmToPx(100)}
                  height={mmToPx(2.5)}
                  fill="currentColor"
                />
              </svg>
            </div>
          {/each}
        </div>
      </aside>
    </div>
  </section>
