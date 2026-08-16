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
  } = $props();

  const defaultPaper =
    paper_options.find((p) => p.value === 'long')?.value ?? paper_options[0]?.value ?? 'long';
  const numberInput = 'flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

  let templateCode = $state('');
  let templateName = $state('');
  let saving = $state(false);
  let previewing = $state(false);
  let solving = $state(false);
  let localError = $state('');
  let serverError = $state('');
  let locked = $state(false);
  let overlayOpen = $state(false);
  let debounceTimer = undefined;

  let blueprint = $state({
    schema: 'nexiam.omr.layout-blueprint.v1',
    paper: defaultPaper,
    columns: 6,
    identity: { applicant_number: true, birthdate: false, repeat_on_every_page: true },
    sections: [],
  });

  let overlay = $state({
    title: '',
    header_lines: [],
    edq_header: false,
    edq_header_label: '',
    exam_information_lines: [],
    instructions_lines: [],
    birthdate_label: '',
    birthdate_placeholder: 'MMDDYYYY',
    footer_lines: [],
    show_calibration: true,
    name_box_label: '',
    per_page: [],
  });

  let projection = $state(null); // { pages, page_count, template_json_hash, diagnostics }
  let diagnostics = $state([]);

  function emptySection() {
    return {
      area_code: areas[0]?.code ?? '',
      question_from: 1,
      question_to: 25,
      option_count: 4,
      break_before: null,
    };
  }

  function initFromDraft() {
    if (draft_version) {
      templateCode = draft_version.code ?? '';
      templateName = draft_version.name ?? '';
      const ov = draft_version.overlay ?? {};
      overlay = {
        title: ov.title ?? '',
        header_lines: Array.isArray(ov.header_lines) ? ov.header_lines : [],
        edq_header: ov.edq_header === true,
        edq_header_label: ov.edq_header_label ?? '',
        exam_information_lines: Array.isArray(ov.exam_information_lines) ? ov.exam_information_lines : [],
        instructions_lines: Array.isArray(ov.instructions_lines) ? ov.instructions_lines : [],
        birthdate_label: ov.birthdate_label ?? '',
        birthdate_placeholder: ov.birthdate_placeholder ?? 'MMDDYYYY',
        footer_lines: Array.isArray(ov.footer_lines) ? ov.footer_lines : [],
        show_calibration: ov.show_calibration !== false,
        name_box_label: ov.name_box_label ?? '',
        per_page: Array.isArray(ov.per_page) ? ov.per_page : [],
      };
      const bp = draft_version.layout_blueprint_json;
      if (bp && typeof bp === 'object' && bp.schema) {
        blueprint = {
          schema: bp.schema,
          paper: bp.paper ?? defaultPaper,
          columns: bp.columns ?? 4,
          identity: {
            applicant_number: bp.identity?.applicant_number ?? true,
            birthdate: bp.identity?.birthdate ?? false,
            repeat_on_every_page: bp.identity?.repeat_on_every_page ?? true,
          },
          sections: (bp.sections ?? []).map((s) => ({
            area_code: s.area_code ?? areas[0]?.code ?? '',
            question_from: Number(s.question_from ?? 1),
            question_to: Number(s.question_to ?? 25),
            option_count: Number(s.option_count ?? 4),
            break_before: s.break_before ?? null,
          })),
        };
        scheduleProjection();
        return;
      }
    }
    // Fresh template: start with one 25-question section per available domain,
    // up to the six columns supported by the compact one-page profile.
    const starterSections = areas.slice(0, 6).map((area) => ({ ...emptySection(), area_code: area.code }));
    blueprint = {
      ...blueprint,
      columns: 6,
      sections: starterSections.length ? starterSections : [{ ...emptySection(), question_to: 25 }],
    };
    scheduleProjection();
  }

  function paperDimensions(paper) {
    return paper_options.find((p) => p.value === paper)?.dimensions ?? [2060, 3144];
  }

  function addSection() {
    blueprint.sections = [...blueprint.sections, emptySection()];
    scheduleProjection();
  }

  function removeSection(index) {
    blueprint.sections = blueprint.sections.filter((_, i) => i !== index);
    scheduleProjection();
  }

  function moveSection(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= blueprint.sections.length) return;
    const next = [...blueprint.sections];
    const [moved] = next.splice(index, 1);
    next.splice(target, 0, moved);
    blueprint.sections = next;
    scheduleProjection();
  }

  function setSection(index, patch) {
    blueprint.sections = blueprint.sections.map((s, i) => (i === index ? { ...s, ...patch } : s));
    scheduleProjection();
  }

  function nonEmptyLines(lines) {
    return (Array.isArray(lines) ? lines : []).map((line) => String(line ?? '').trim()).filter(Boolean);
  }

  function geometryUnits(mm) {
    return (mm * 242) / 25.4;
  }

  function overlayMetrics(page, pageIndex, width) {
    const headerLines = nonEmptyLines(overlay.header_lines);
    const note = overlay.per_page.find((item) => Number(item?.page_no) === pageIndex + 1)?.note?.trim() ?? '';
    const pageLabelTopMm = 9 + headerLines.length * 5;
    const noteTopMm = pageLabelTopMm + 5;
    const afterHeaderMm = note ? noteTopMm + 5 : noteTopMm;
    const firstBlockTopMm = Math.min(
      ...page.fieldBlocks.map((block) => ((Number(block.origin?.[1] ?? 99999) - (9 * 242) / 72 - 8) * 25.4) / 242),
    );
    const candidateMm = Number.isFinite(firstBlockTopMm)
      ? Math.max(0, firstBlockTopMm - (8 / 242) * 25.4) - 8
      : null;
    const topPlacementSafe = candidateMm !== null && candidateMm >= afterHeaderMm + 1;
    let topMm = candidateMm;
    let leftMm = 80 / 242 * 25.4 + 2;
    let widthMm = 80;

    // Match BundleRenderer: compact identity bands and full headers use the
    // unused upper-right area instead of hiding the manual fallback field.
    if (!topPlacementSafe || BUBBLE <= 40) {
      widthMm = 50;
      leftMm = (width * 25.4) / 242 - 80 / 242 * 25.4 - widthMm - 2;
      topMm = Math.max(afterHeaderMm + 2, candidateMm ?? 80 / 242 * 25.4 + 2);
    }

    return {
      label: overlay.name_box_label.trim() || 'NAME (PRINT):',
      x: geometryUnits(leftMm),
      labelY: geometryUnits(topMm),
      y: geometryUnits(topMm + 3.5),
      width: geometryUnits(widthMm),
      height: geometryUnits(8),
    };
  }

  function csrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el instanceof HTMLMetaElement ? el.content : '';
  }

  async function refreshProjection() {
    if (!blueprint.sections.length) return;
    solving = true;
    diagnostics = [];
    try {
      const res = await fetch('/setup/omr/layout/preview', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ layout_blueprint_json: JSON.stringify(blueprint) }),
      });
      const body = await res.json();
      if (!res.ok) {
        projection = null;
        diagnostics = body?.diagnostics ?? [{ code: 'preview_failed', message: body?.error ?? body?.message ?? 'The blueprint could not be solved.' }];
      } else {
        projection = body;
        diagnostics = body.diagnostics ?? [];
      }
    } catch {
      projection = null;
      diagnostics = [{ code: 'preview_failed', message: 'The layout service is unavailable.' }];
    } finally {
      solving = false;
    }
  }

  function scheduleProjection() {
    clearTimeout(debounceTimer);
    solving = true;
    debounceTimer = setTimeout(() => refreshProjection(), 400);
  }

  function assembleOverlay() {
    return {
      ...(overlay.title.trim() ? { title: overlay.title.trim() } : {}),
      header_lines: overlay.header_lines.filter((l) => l.trim()),
      ...(overlay.edq_header ? {
        edq_header: true,
        ...(overlay.edq_header_label.trim() ? { edq_header_label: overlay.edq_header_label.trim() } : {}),
        exam_information_lines: overlay.exam_information_lines.filter((l) => l.trim()),
        instructions_lines: overlay.instructions_lines.filter((l) => l.trim()),
        birthdate_label: overlay.birthdate_label.trim() || 'BIRTHDATE:',
        birthdate_placeholder: overlay.birthdate_placeholder.trim() || 'MMDDYYYY',
      } : {}),
      footer_lines: overlay.footer_lines.filter((l) => l.trim()),
      show_calibration: overlay.show_calibration,
      ...(overlay.name_box_label.trim() ? { name_box_label: overlay.name_box_label.trim() } : {}),
      per_page: overlay.per_page.filter((p) => p && String(p.note ?? '').trim()),
    };
  }

  async function previewPdf() {
    if (previewing || locked) return;
    if (diagnostics.length || !projection) {
      localError = 'Fix the layout diagnostics before previewing the PDF.';
      return;
    }
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
          layout_blueprint_json: JSON.stringify(blueprint),
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
          /* keep default */
        }
        localError = message;
        return;
      }
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      window.open(url, '_blank');
      setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch {
      localError = 'The preview service is unavailable.';
    } finally {
      previewing = false;
    }
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
    for (const [index, section] of blueprint.sections.entries()) {
      if (!section.area_code) {
        localError = `Section ${index + 1} needs an area.`;
        return;
      }
      if (!Number.isInteger(section.question_from) || !Number.isInteger(section.question_to) || section.question_from < 1 || section.question_to < section.question_from) {
        localError = `Section ${index + 1} has an invalid question range.`;
        return;
      }
    }
    saving = true;
    router.post(
      '/setup/omr/templates',
      {
        template_code: templateCode.trim(),
        template_name: templateName.trim(),
        layout_blueprint_json: JSON.stringify(blueprint),
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

  initFromDraft();

  let BUBBLE = $derived(blueprint.columns === 6 ? 40 : 56);
  let MCQ_GAP = $derived(blueprint.columns === 6 ? 52 : 66);
  const IDENTITY_LABELS_GAP = 66;
  const IDENTITY_BUBBLES_GAP = 60;
  const ROW_PITCH = 80;
</script>

<section class="space-y-6">
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
              placeholder="e.g. 2026 Aptitude Answer Sheet"
              value={templateName}
              oninput={(e) => (templateName = e.currentTarget.value)}
              disabled={locked}
              required
            />
          </div>
        </div>
      </div>

      <div class="rounded-lg border p-4">
        <h2 class="mb-3 text-lg font-semibold">Sheet layout</h2>
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="space-y-2">
            <Label>Paper</Label>
            <select
              class={numberInput}
              value={blueprint.paper}
              onchange={(e) => { blueprint.paper = e.currentTarget.value; scheduleProjection(); }}
              disabled={locked}
            >
              {#each paper_options as paper}
                <option value={paper.value}>{paper.label}</option>
              {/each}
            </select>
          </div>
          <div class="space-y-2">
            <Label>Columns</Label>
            <div class="flex gap-1">
              {#each [2, 3, 4, 6] as count}
                <Button
                  variant={blueprint.columns === count ? 'default' : 'outline'}
                  size="sm"
                  onclick={() => { blueprint.columns = count; scheduleProjection(); }}
                  disabled={locked}
                >
                  {count}
                </Button>
              {/each}
            </div>
          </div>
          <div class="space-y-2">
            <Label>Identity grids</Label>
            <div class="space-y-1.5 text-sm">
              <label class="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={blueprint.identity.applicant_number}
                  onchange={(e) => { blueprint.identity.applicant_number = e.currentTarget.checked; scheduleProjection(); }}
                  disabled={locked}
                />
                Applicant number
              </label>
              <label class="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={blueprint.identity.birthdate}
                  onchange={(e) => { blueprint.identity.birthdate = e.currentTarget.checked; scheduleProjection(); }}
                  disabled={locked}
                />
                Birthdate (MMDDYYYY)
              </label>
              <label class="flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={blueprint.identity.repeat_on_every_page}
                  onchange={(e) => { blueprint.identity.repeat_on_every_page = e.currentTarget.checked; scheduleProjection(); }}
                  disabled={locked}
                />
                Repeat on every page
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-lg border p-4">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-lg font-semibold">Question sections</h2>
          <Button variant="outline" size="sm" onclick={addSection} disabled={locked}>Add section</Button>
        </div>
        <p class="mb-3 text-xs text-muted-foreground">
          Sections fill columns top-to-bottom in order; use six 25-question sections for the one-page 150-question profile.
          A section that reaches the bottom continues in the next column, then the next page.
        </p>
        <div class="space-y-3">
          {#each blueprint.sections as section, index (index)}
            <div class="rounded border p-3">
              <div class="mb-2 flex items-center justify-between">
                <span class="text-sm font-medium">Section {index + 1}</span>
                <div class="flex items-center gap-1">
                  <Button variant="ghost" size="sm" onclick={() => moveSection(index, -1)} disabled={index === 0 || locked} title="Move up">↑</Button>
                  <Button variant="ghost" size="sm" onclick={() => moveSection(index, 1)} disabled={index === blueprint.sections.length - 1 || locked} title="Move down">↓</Button>
                  <Button variant="ghost" size="sm" class="text-destructive" onclick={() => removeSection(index)} disabled={locked} title="Remove section">Remove</Button>
                </div>
              </div>
              <div class="grid gap-3 sm:grid-cols-4">
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Area</Label>
                  <select
                    class={numberInput}
                    value={section.area_code}
                    onchange={(e) => setSection(index, { area_code: e.currentTarget.value })}
                    disabled={locked}
                  >
                    <option value="" disabled>Choose an area…</option>
                    {#each areas as area}
                      <option value={area.code}>{area.code} — {area.name}</option>
                    {/each}
                  </select>
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Question from</Label>
                  <input
                    class={numberInput}
                    type="number"
                    min="1"
                    value={section.question_from}
                    oninput={(e) => setSection(index, { question_from: Number(e.currentTarget.value) })}
                    disabled={locked}
                  />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Question to</Label>
                  <input
                    class={numberInput}
                    type="number"
                    min="1"
                    value={section.question_to}
                    oninput={(e) => setSection(index, { question_to: Number(e.currentTarget.value) })}
                    disabled={locked}
                  />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Break before</Label>
                  <select
                    class={numberInput}
                    value={section.break_before ?? 'none'}
                    onchange={(e) => setSection(index, { break_before: e.currentTarget.value === 'none' ? null : e.currentTarget.value })}
                    disabled={locked}
                  >
                    <option value="none">Continue flow</option>
                    <option value="next_column">Start next column</option>
                    <option value="next_page">Start next page</option>
                  </select>
                </div>
              </div>
            </div>
          {/each}
          {#if blueprint.sections.length === 0}
            <p class="rounded bg-muted p-3 text-sm text-muted-foreground">
              No sections yet — add one to start building the sheet.
            </p>
          {/if}
        </div>
      </div>

      <div class="rounded-lg border p-4">
        <button
          type="button"
          class="flex w-full items-center justify-between"
          onclick={() => (overlayOpen = !overlayOpen)}
        >
          <span class="text-lg font-semibold">Sheet text / overlay</span>
          <span class="text-muted-foreground">{overlayOpen ? '−' : '+'}</span>
        </button>
        {#if overlayOpen}
          <div class="mt-3 grid gap-3">
            <div class="space-y-1">
              <Label class="text-xs text-muted-foreground">Title (printed on every page)</Label>
              <Input value={overlay.title} oninput={(e) => (overlay.title = e.currentTarget.value)} disabled={locked} />
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
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={overlay.edq_header} onchange={(e) => (overlay.edq_header = e.currentTarget.checked)} disabled={locked} />
              Add EDQ / header zone
            </label>
            {#if overlay.edq_header}
              <div class="space-y-1">
                <Label class="text-xs text-muted-foreground">EDQ label</Label>
                <Input value={overlay.edq_header_label} oninput={(e) => (overlay.edq_header_label = e.currentTarget.value)} placeholder="EDQ / HEADER ZONE" disabled={locked} />
              </div>
              <div class="space-y-1">
                <Label class="text-xs text-muted-foreground">Exam information (one per line)</Label>
                <textarea class={numberInput} rows="2" value={overlay.exam_information_lines.join('\n')} oninput={(e) => (overlay.exam_information_lines = e.currentTarget.value.split('\n'))} disabled={locked}></textarea>
              </div>
              <div class="space-y-1">
                <Label class="text-xs text-muted-foreground">Instructions (one per line)</Label>
                <textarea class={numberInput} rows="2" value={overlay.instructions_lines.join('\n')} oninput={(e) => (overlay.instructions_lines = e.currentTarget.value.split('\n'))} disabled={locked}></textarea>
              </div>
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Birthdate label</Label>
                  <Input value={overlay.birthdate_label} oninput={(e) => (overlay.birthdate_label = e.currentTarget.value)} placeholder="BIRTHDATE:" disabled={locked} />
                </div>
                <div class="space-y-1">
                  <Label class="text-xs text-muted-foreground">Birthdate format</Label>
                  <Input value={overlay.birthdate_placeholder} oninput={(e) => (overlay.birthdate_placeholder = e.currentTarget.value)} placeholder="MMDDYYYY" disabled={locked} />
                </div>
              </div>
            {/if}
            <div class="space-y-1">
              <Label class="text-xs text-muted-foreground">Footer lines (one per line, max 3)</Label>
              <textarea class={numberInput} rows="2" value={overlay.footer_lines.join('\n')} oninput={(e) => (overlay.footer_lines = e.currentTarget.value.split('\n'))} disabled={locked}></textarea>
            </div>
            <label class="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={overlay.show_calibration} onchange={(e) => (overlay.show_calibration = e.currentTarget.checked)} disabled={locked} />
              Show calibration strip
            </label>
            <div class="space-y-1">
              <Label class="text-xs text-muted-foreground">Name box label</Label>
              <Input value={overlay.name_box_label} oninput={(e) => (overlay.name_box_label = e.currentTarget.value)} disabled={locked} />
            </div>
          </div>
        {/if}
      </div>
    </div>

    <aside class="min-w-0 space-y-4">
      <div class="rounded-lg border p-4">
        <div class="mb-2 flex items-center justify-between">
          <h2 class="font-semibold">Preview</h2>
          <span class="text-xs text-muted-foreground">{solving ? 'Solving…' : projection ? `${projection.layout.page_count} page${projection.layout.page_count === 1 ? '' : 's'}` : ''}</span>
        </div>

        {#if diagnostics.length}
          <div class="space-y-2">
            {#each diagnostics as diagnostic}
              <p class="rounded border border-red-200 bg-red-50 p-2 text-xs text-red-800">
                <span class="font-mono">{diagnostic.code}</span> — {diagnostic.message}
              </p>
            {/each}
          </div>
        {/if}

        {#if projection && !diagnostics.length}
          {#each projection.layout.pages as page, pageIndex}
            {@const [w, h] = page.pageDimensions}
            {@const chrome = overlayMetrics(page, pageIndex, w)}
            <svg viewBox={`0 0 ${w} ${h}`} class="mb-3 w-full rounded border">
              <rect x="0" y="0" width={w} height={h} fill="white" />
              <rect x="80" y="80" width={w - 160} height={h - 160} fill="none" stroke="currentColor" stroke-width="6" stroke-dasharray="24 16" />
              <text x={w / 2} y="68" font-size="30" font-weight="bold" text-anchor="middle" fill="currentColor">{overlay.title.trim() || 'NEXIAM ANSWER SHEET'}</text>
              {#each nonEmptyLines(overlay.header_lines).slice(0, 2) as line, lineIndex}
                <text x={w / 2} y={105 + lineIndex * 28} font-size="22" font-weight="bold" text-anchor="middle" fill="currentColor">{line}</text>
              {/each}
              {#if overlay.edq_header}
                <rect x="80" y="80" width={w - 160} height="820" fill="none" stroke="currentColor" stroke-width="3" />
                <text x="100" y="220" font-size="20" font-weight="bold" fill="currentColor">{overlay.edq_header_label.trim() || 'EDQ / HEADER ZONE'}</text>
                {#each nonEmptyLines(overlay.exam_information_lines).slice(0, 2) as line, lineIndex}
                  <text x="120" y={255 + lineIndex * 24} font-size="18" fill="currentColor">{line}</text>
                {/each}
                {#each nonEmptyLines(overlay.instructions_lines).slice(0, 2) as line, lineIndex}
                  <text x="120" y={325 + lineIndex * 24} font-size="18" fill="currentColor">{line}</text>
                {/each}
                <text x={chrome.x} y={chrome.labelY + geometryUnits(15)} font-size="24" font-weight="bold" fill="currentColor">{overlay.birthdate_label.trim() || 'BIRTHDATE:'}</text>
                <rect x={chrome.x} y={chrome.y + geometryUnits(15)} width={chrome.width} height={chrome.height} fill="none" stroke="currentColor" stroke-width="3" />
                <text x={chrome.x + chrome.width / 2} y={chrome.y + geometryUnits(15) + chrome.height / 2 + 8} font-size="20" text-anchor="middle" fill="currentColor">{overlay.birthdate_placeholder.trim() || 'MMDDYYYY'}</text>
              {/if}
              <text x={chrome.x} y={chrome.labelY} font-size="24" font-weight="bold" fill="currentColor">{chrome.label}</text>
              <rect x={chrome.x} y={chrome.y} width={chrome.width} height={chrome.height} fill="none" stroke="currentColor" stroke-width="3" />
              <!-- One dashed printable-margin guide; the PDF uses a solid frame. -->
              {#each page.fieldBlocks as block}
                {@const origin = block.origin}
                {@const labels = (block.fieldLabels ?? [])[0] ?? ''}
                {#if block.fieldType === 'QTYPE_INT'}
                  {@const isBirthdate = labels.startsWith('BIRTHDATE_')}
                  {@const count = isBirthdate ? 6 : 10}
                  {@const labelsGap = Number(block.labelsGap ?? IDENTITY_LABELS_GAP)}
                  {@const bubblesGap = Number(block.bubblesGap ?? IDENTITY_BUBBLES_GAP)}
                  {#each Array(count) as _, c}
                    <text x={origin[0] + c * labelsGap + BUBBLE / 2} y={origin[1] - 5} font-size="34" text-anchor="middle" fill="currentColor">{c + 1}</text>
                    {#each Array(10) as _, r}
                      <circle cx={origin[0] + c * labelsGap + BUBBLE / 2} cy={origin[1] + r * bubblesGap + BUBBLE / 2} r={BUBBLE / 2} fill="none" stroke="currentColor" stroke-width="3" />
                    {/each}
                  {/each}
                  {#each Array(10) as _, r}
                    <text x={origin[0] - 8} y={origin[1] + r * bubblesGap + BUBBLE / 2 + 12} font-size="30" text-anchor="end" fill="currentColor">{r}</text>
                  {/each}
                {:else}
                  {@const match = /^([A-Z0-9_]+)_Q_(\d+)\.\.(\d+)$/.exec(labels)}
                  {@const from = match ? Number(match[2]) : 1}
                  {#each ['A', 'B', 'C', 'D'] as letter, c (letter)}
                    <text x={origin[0] + c * MCQ_GAP + BUBBLE / 2} y={origin[1] - 8} font-size="36" text-anchor="middle" fill="currentColor">{letter}</text>
                  {/each}
                  {@const rows = match ? Number(match[3]) - from + 1 : 0}
                  {#each Array(Math.max(0, rows)) as _, r}
                    <text x={origin[0] - 8} y={origin[1] + r * ROW_PITCH + BUBBLE / 2 + 12} font-size="30" text-anchor="end" fill="currentColor">{from + r}</text>
                    {#each [0, 1, 2, 3] as c}
                      <circle cx={origin[0] + c * MCQ_GAP + BUBBLE / 2} cy={origin[1] + r * ROW_PITCH + BUBBLE / 2} r={BUBBLE / 2} fill="none" stroke="currentColor" stroke-width="3" />
                    {/each}
                  {/each}
                {/if}
              {/each}
            </svg>
          {/each}
        {:else if !diagnostics.length}
          <p class="text-sm text-muted-foreground">Add sections to preview the layout.</p>
        {/if}
      </div>

      <div class="flex flex-col gap-2">
        <Button variant="outline" onclick={previewPdf} disabled={locked || previewing || solving || !!diagnostics.length}>
          {previewing ? 'Rendering…' : 'Preview PDF'}
        </Button>
        <Button onclick={submit} disabled={locked || saving}>{saving ? 'Saving…' : draft_version ? 'Save changes' : 'Create template'}</Button>
      </div>
    </aside>
  </div>
</section>
