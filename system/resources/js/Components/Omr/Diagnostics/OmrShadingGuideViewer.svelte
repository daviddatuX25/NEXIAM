<script>
    import { Minus, Plus, RotateCcw } from 'lucide-svelte';

    let {
        pages = [],
        sections = [],
        instructions = [],
        instanceCount = 1,
        selectedInstance = $bindable(1),
        markingLegend = [],
        outcomeSummary = null,
        patternMode = 'guided_mix',
        title = 'Intentional Marks',
    } = $props();

    let currentPage = $state(1);
    let zoom = $state(1);
    let panX = $state(0);
    let panY = $state(0);
    let dragging = $state(false);
    let viewerContainer = $state(null);
    let marksListContainer = $state(null);
    let selectedSectionKey = $state(null);
    let selectedQuestionLabel = $state(null);
    let dragStartX = 0;
    let dragStartY = 0;
    let panStartX = 0;
    let panStartY = 0;

    const normalizedLegend = $derived.by(() => {
        if (Array.isArray(markingLegend) && markingLegend.length > 0) {
            return markingLegend;
        }
        if (markingLegend && typeof markingLegend === 'object') {
            return Object.entries(markingLegend).map(([intent, label]) => ({
                intent,
                label: String(label),
            }));
        }
        return [
            { intent: 'correct', label: 'Correct Set A key' },
            { intent: 'wrong', label: 'Intentional wrong mark' },
            { intent: 'double', label: 'Double-shaded · expect review' },
            { intent: 'blank', label: 'Leave blank' },
        ];
    });
    const pageNumbers = $derived(
        [
            ...new Set([
                ...pages
                    .map((page) => Number(page?.page_no))
                    .filter((number) => Number.isInteger(number) && number > 0),
                ...sections
                    .map((section) => Number(section?.page_no))
                    .filter((number) => Number.isInteger(number) && number > 0),
                ...instructions
                    .map((instruction) => Number(instruction?.page_no))
                    .filter((number) => Number.isInteger(number) && number > 0),
            ]),
        ].sort((a, b) => a - b),
    );
    $effect(() => {
        if (pageNumbers.length > 0 && !pageNumbers.includes(currentPage)) {
            currentPage = pageNumbers[0];
            fit();
        }
    });
    const activePage = $derived(
        pages.find((page) => Number(page?.page_no) === currentPage) ?? null,
    );
    const activeInstance = $derived(
        activePage?.instances?.find(
            (instance) => Number(instance?.instance_no) === Number(selectedInstance),
        ) ?? null,
    );
    const activeBubbles = $derived(
        activeInstance?.bubbles ?? activePage?.bubbles ?? [],
    );
    const instanceInstructions = $derived(
        instructions.length > 0
            ? instructions.filter(
                  (instruction) =>
                      Number(instruction?.instance_no ?? 1) ===
                      Number(selectedInstance),
              )
            : pages.flatMap((page) => {
                  const instance = page?.instances?.find(
                      (item) =>
                          Number(item?.instance_no) === Number(selectedInstance),
                  );
                  return instance?.instructions ?? [];
              }),
    );
    const marks = $derived([
        ...instanceInstructions.filter(
            (instruction) =>
                Number(instruction?.page_no ?? currentPage) === currentPage,
        ),
        ...sections
            .filter((section) => Number(section?.page_no ?? 1) === currentPage)
            .flatMap((section) => {
                const source = Array.isArray(section?.marks)
                    ? section.marks
                    : Array.isArray(section?.items)
                      ? section.items
                      : [];
                return source.map((mark) => ({
                    ...mark,
                    section_name:
                        section?.name ??
                        section?.subtest_name ??
                        section?.code ??
                        null,
                }));
            }),
    ]);
    const expectedSummary = $derived.by(() => {
        if (outcomeSummary) return outcomeSummary;
        const currentInstanceInstructions = instanceInstructions;
        if (
            currentInstanceInstructions.length === 0 &&
            patternMode !== 'blank_baseline'
        ) {
            return null;
        }
        return {
            expected_raw_score:
                patternMode === 'blank_baseline'
                    ? 0
                    : currentInstanceInstructions.filter(
                          (instruction) => instruction?.intent === 'correct',
                      ).length,
            expected_flagged_review_count:
                currentInstanceInstructions.filter(
                    (instruction) => instruction?.intent === 'double',
                ).length,
        };
    });
    const imageUrl = $derived(
        activePage?.available === false
            ? null
            : activePage?.url ??
              activePage?.reference_url ??
              activePage?.image_url ??
              null,
    );
    const pageWidth = $derived(Number(activePage?.width_px) || 2480);
    const pageHeight = $derived(Number(activePage?.height_px) || 3508);
    const paperDisplayHeight = 410;
    const paperDisplayWidth = $derived(
        Math.max(180, Math.round((paperDisplayHeight * pageWidth) / pageHeight)),
    );

    function clampZoom(value) {
        return Math.min(3, Math.max(0.5, Math.round(value * 100) / 100));
    }

    function fit() {
        zoom = 1;
        panX = 0;
        panY = 0;
        dragging = false;
    }

    function zoomBy(amount) {
        const next = clampZoom(zoom + amount);
        if (next <= 1) {
            zoom = next;
            panX = 0;
            panY = 0;
            return;
        }
        const ratio = next / zoom;
        zoom = next;
        panX *= ratio;
        panY *= ratio;
    }

    function changePage(pageNo) {
        if (!pageNumbers.includes(Number(pageNo))) return;
        currentPage = Number(pageNo);
        selectedSectionKey = null;
        selectedQuestionLabel = null;
        fit();
    }

    function parseLabelIdentity(rawLabel, explicitSection = null) {
        const text = String(rawLabel ?? '').trim();
        const match = text.match(/^([A-Za-z0-9_]+?)(?:_Q_?|_ITEM_?|_)(\d+)$/i);
        const rawSection = explicitSection ?? (match ? match[1] : 'ITEMS');
        const sectionKey = String(rawSection).toUpperCase();
        const sectionTitle = String(rawSection)
            .replace(/_/g, ' ')
            .toLowerCase()
            .replace(/\b\w/g, (c) => c.toUpperCase());
        const questionNo = match ? Number(match[2]) : null;

        return {
            label: text,
            sectionKey,
            sectionTitle,
            questionNo,
            shortQuestion: questionNo !== null ? `Q${questionNo}` : text,
        };
    }
    function targetTokens(mark) {
        if (Array.isArray(mark?.target_tokens)) {
            return mark.target_tokens.map(String);
        }
        if (Array.isArray(mark?.tokens)) return mark.tokens.map(String);
        const token = mark?.token ?? mark?.option_token ?? mark?.option;
        return token === null || token === undefined || token === ''
            ? []
            : [String(token)];
    }

    function tokenOf(bubble) {
        return String(
            bubble?.option_token ??
                bubble?.option ??
                bubble?.token ??
                bubble?.label ??
                '',
        );
    }

    function markQuestion(mark) {
        return (
            mark?.native_label ??
            mark?.label ??
            mark?.question_label ??
            mark?.item_id ??
            `Q${mark?.question_no ?? mark?.item_no ?? ''}`
        );
    }

    function centerOf(bubble) {
        const x = Number(bubble?.x ?? bubble?.left ?? 0);
        const y = Number(bubble?.y ?? bubble?.top ?? 0);
        const width = Number(bubble?.w ?? bubble?.width ?? 0);
        const height = Number(bubble?.h ?? bubble?.height ?? 0);
        return { x: x + width / 2, y: y + height / 2, width, height };
    }

    function bubbleIntent(bubble) {
        const label = String(
            bubble?.native_label ?? bubble?.label ?? bubble?.item_id ?? '',
        );
        const token = tokenOf(bubble);
        const mark = marks.find(
            (item) =>
                String(
                    item?.native_label ??
                        item?.label ??
                        item?.question_label ??
                        item?.item_id ??
                        '',
                ) === label && targetTokens(item).includes(token),
        );
        return mark?.intent ?? bubble?.intent ?? 'blank';
    }

    const questionRowsByLabel = $derived.by(() => {
        const map = new Map();
        for (const bubble of activeBubbles) {
            const label = String(
                bubble?.native_label ?? bubble?.label ?? bubble?.item_id ?? '',
            );
            if (!label) continue;
            const x = Number(bubble?.x ?? bubble?.left ?? 0);
            const y = Number(bubble?.y ?? bubble?.top ?? 0);
            const w = Number(bubble?.w ?? bubble?.width ?? 0);
            const h = Number(bubble?.h ?? bubble?.height ?? 0);
            const existing = map.get(label);
            if (!existing) {
                const parsed = parseLabelIdentity(label, bubble?.section_name);
                map.set(label, {
                    label,
                    sectionKey: parsed.sectionKey,
                    sectionTitle: parsed.sectionTitle,
                    questionNo: parsed.questionNo,
                    shortQuestion: parsed.shortQuestion,
                    minX: x,
                    minY: y,
                    maxX: x + w,
                    maxY: y + h,
                });
            } else {
                existing.minX = Math.min(existing.minX, x);
                existing.minY = Math.min(existing.minY, y);
                existing.maxX = Math.max(existing.maxX, x + w);
                existing.maxY = Math.max(existing.maxY, y + h);
            }
        }
        return map;
    });

    const questionSetsOnPage = $derived.by(() => {
        const sets = new Map();
        for (const mark of marks) {
            const label = String(markQuestion(mark));
            const parsed = parseLabelIdentity(label, mark?.section_name);
            const rowBounds = questionRowsByLabel.get(label);
            const existing = sets.get(parsed.sectionKey);
            if (!existing) {
                sets.set(parsed.sectionKey, {
                    key: parsed.sectionKey,
                    title: parsed.sectionTitle,
                    count: 1,
                    minX: rowBounds ? rowBounds.minX : Infinity,
                    minY: rowBounds ? rowBounds.minY : Infinity,
                    maxX: rowBounds ? rowBounds.maxX : -Infinity,
                    maxY: rowBounds ? rowBounds.maxY : -Infinity,
                });
            } else {
                existing.count += 1;
                if (rowBounds) {
                    existing.minX = Math.min(existing.minX, rowBounds.minX);
                    existing.minY = Math.min(existing.minY, rowBounds.minY);
                    existing.maxX = Math.max(existing.maxX, rowBounds.maxX);
                    existing.maxY = Math.max(existing.maxY, rowBounds.maxY);
                }
            }
        }
        return [...sets.values()];
    });

    const filteredMarks = $derived(
        selectedSectionKey
            ? marks.filter(
                  (mark) =>
                      parseLabelIdentity(markQuestion(mark), mark?.section_name)
                          .sectionKey === selectedSectionKey,
              )
            : marks,
    );

    const highlightedSectionKey = $derived.by(() => {
        if (selectedSectionKey) return selectedSectionKey;
        if (selectedQuestionLabel) {
            return parseLabelIdentity(selectedQuestionLabel).sectionKey;
        }
        return null;
    });

    const activeSectionBounds = $derived.by(() => {
        if (!highlightedSectionKey) return null;
        const found = questionSetsOnPage.find(
            (set) => set.key === highlightedSectionKey,
        );
        if (!found || !Number.isFinite(found.minX)) return null;
        return found;
    });

    const activeQuestionBounds = $derived(
        selectedQuestionLabel
            ? (questionRowsByLabel.get(selectedQuestionLabel) ?? null)
            : null,
    );

    const selectedMark = $derived(
        selectedQuestionLabel
            ? (marks.find(
                  (item) => String(markQuestion(item)) === selectedQuestionLabel,
              ) ?? null)
            : null,
    );

    function centerBoundsInViewer(bounds, targetZoom = null) {
        if (!bounds || !viewerContainer) return;
        const scaleX = paperDisplayWidth / pageWidth;
        const scaleY = paperDisplayHeight / pageHeight;
        const boxW = Math.max(120, bounds.maxX - bounds.minX + 120);
        const boxH = Math.max(80, bounds.maxY - bounds.minY + 120);
        const computedZoom =
            targetZoom ??
            clampZoom(
                Math.min(
                    (viewerContainer.clientWidth || 640) / Math.max(1, boxW * scaleX * 1.15),
                    (viewerContainer.clientHeight || 440) / Math.max(1, boxH * scaleY * 1.25),
                    2.2,
                ),
            );
        const centerX = (bounds.minX + bounds.maxX) / 2;
        const centerY = (bounds.minY + bounds.maxY) / 2;
        zoom = clampZoom(computedZoom);
        panX = (pageWidth / 2 - centerX) * scaleX * zoom;
        panY = (pageHeight / 2 - centerY) * scaleY * zoom;
    }

    function selectQuestionSet(sectionKey) {
        if (selectedSectionKey === sectionKey && !selectedQuestionLabel) {
            selectedSectionKey = null;
            fit();
            return;
        }
        selectedSectionKey = sectionKey;
        selectedQuestionLabel = null;
        const setBounds = questionSetsOnPage.find(
            (set) => set.key === sectionKey,
        );
        if (setBounds && Number.isFinite(setBounds.minX)) {
            centerBoundsInViewer(setBounds, null);
        }
    }

    function selectQuestionOnCanvas(label) {
        selectedQuestionLabel = label;
        const rowBounds = questionRowsByLabel.get(label);
        if (rowBounds) {
            centerBoundsInViewer(rowBounds, 1.9);
        }
        if (marksListContainer) {
            queueMicrotask(() => {
                const btn = marksListContainer.querySelector(
                    `[data-mark-label="${CSS.escape(label)}"]`,
                );
                btn?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        }
    }

    function focusMark(mark) {
        const label = String(markQuestion(mark));
        selectedQuestionLabel = label;
        const rowBounds = questionRowsByLabel.get(label);
        if (rowBounds) {
            centerBoundsInViewer(rowBounds, 1.9);
            return;
        }
        const tokens = targetTokens(mark);
        const bubble = activeBubbles.find(
            (item) =>
                String(
                    item?.native_label ?? item?.label ?? item?.item_id ?? '',
                ) === label && tokens.includes(tokenOf(item)),
        );
        if (!bubble) return;
        const center = centerOf(bubble);
        centerBoundsInViewer(
            {
                minX: center.x - center.width,
                maxX: center.x + center.width,
                minY: center.y - center.height,
                maxY: center.y + center.height,
            },
            1.9,
        );
    }
    function onWheel(event) {
        event.preventDefault();
        const next = clampZoom(zoom + (event.deltaY < 0 ? 0.2 : -0.2));
        const rect = viewerContainer?.getBoundingClientRect();
        if (rect && next > 1) {
            const x = event.clientX - (rect.left + rect.width / 2);
            const y = event.clientY - (rect.top + rect.height / 2);
            const ratio = next / zoom;
            panX = panX * ratio + x * (1 - ratio);
            panY = panY * ratio + y * (1 - ratio);
        } else if (next <= 1) {
            panX = 0;
            panY = 0;
        }
        zoom = next;
    }

    function onPointerDown(event) {
        if (event.button !== 0 || zoom <= 1) return;
        dragging = true;
        dragStartX = event.clientX;
        dragStartY = event.clientY;
        panStartX = panX;
        panStartY = panY;
        event.currentTarget.setPointerCapture?.(event.pointerId);
    }

    function onPointerMove(event) {
        if (!dragging) return;
        panX = panStartX + event.clientX - dragStartX;
        panY = panStartY + event.clientY - dragStartY;
    }

    function onPointerUp(event) {
        dragging = false;
        event.currentTarget.releasePointerCapture?.(event.pointerId);
    }

    function onKeyDown(event) {
        if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            zoomBy(0.2);
        } else if (event.key === '-') {
            event.preventDefault();
            zoomBy(-0.2);
        } else if (event.key === '0') {
            event.preventDefault();
            fit();
        } else if (zoom > 1 && event.key.startsWith('Arrow')) {
            event.preventDefault();
            if (event.key === 'ArrowLeft') panX += 36;
            if (event.key === 'ArrowRight') panX -= 36;
            if (event.key === 'ArrowUp') panY += 36;
            if (event.key === 'ArrowDown') panY -= 36;
        }
    }
</script>

<div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]" data-omr-shading-guide-viewer>
    <section class="min-w-0 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            {#if Number(instanceCount) > 1}
                <div class="inline-flex flex-wrap gap-1 rounded-lg border p-1" role="tablist" aria-label="Mock sheet instance">
                    {#each Array.from({ length: Number(instanceCount) }, (_, index) => index + 1) as instance (instance)}
                        <button type="button" role="tab" aria-selected={selectedInstance === instance} class="rounded-md px-3 py-1.5 text-xs font-semibold transition" class:bg-primary={selectedInstance === instance} class:text-primary-foreground={selectedInstance === instance} onclick={() => { selectedInstance = instance; fit(); }}>
                            Instance #{instance}
                        </button>
                    {/each}
                </div>
            {:else}
                <h3 class="text-sm font-semibold">{title}</h3>
            {/if}
            <div class="flex items-center gap-1">
                <button type="button" onclick={() => zoomBy(-0.2)} aria-label="Zoom out" class="rounded-md border p-2 disabled:opacity-40" disabled={zoom <= 0.5}><Minus class="size-4" /></button>
                <span class="min-w-12 text-center font-mono text-xs">{Math.round(zoom * 100)}%</span>
                <button type="button" onclick={() => zoomBy(0.2)} aria-label="Zoom in" class="rounded-md border p-2 disabled:opacity-40" disabled={zoom >= 3}><Plus class="size-4" /></button>
                <button type="button" onclick={fit} aria-label="Fit to view" class="inline-flex items-center gap-1 rounded-md border px-2.5 py-2 text-xs font-semibold"><RotateCcw class="size-3.5" /> Fit</button>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            {#if pageNumbers.length > 1}
                <div class="flex flex-wrap gap-1" aria-label="Guide pages">
                    {#each pageNumbers as pageNo (pageNo)}
                        <button type="button" aria-current={currentPage === pageNo ? 'page' : undefined} class="rounded-md border px-3 py-1.5 text-xs font-medium" class:bg-muted={currentPage === pageNo} onclick={() => changePage(pageNo)}>Page {pageNo}</button>
                    {/each}
                </div>
            {/if}
            {#if questionSetsOnPage.length > 0}
                <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Highlight Question Set on page">
                    <button
                        type="button"
                        class={`rounded-full border px-2.5 py-1 text-[11px] font-semibold transition ${!selectedSectionKey && !selectedQuestionLabel ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-muted'}`}
                        onclick={() => {
                            selectedSectionKey = null;
                            selectedQuestionLabel = null;
                            fit();
                        }}
                    >
                        All Sets ({marks.length})
                    </button>
                    {#each questionSetsOnPage as set (set.key)}
                        <button
                            type="button"
                            class={`rounded-full border px-2.5 py-1 text-[11px] font-semibold transition ${selectedSectionKey === set.key ? 'border-sky-600 bg-sky-600 text-white' : 'bg-background hover:bg-muted'}`}
                            onclick={() => selectQuestionSet(set.key)}
                        >
                            {set.title} ({set.count})
                        </button>
                    {/each}
                </div>
            {/if}
        </div>
        {#if imageUrl}
            <!-- svelte-ignore a11y_no_noninteractive_tabindex a11y_no_noninteractive_element_interactions -->
            <div bind:this={viewerContainer} role="region" tabindex="0" aria-label={`Zoomable shading guide, page ${currentPage}`} class="relative flex h-[440px] touch-none items-center justify-center overflow-hidden rounded-xl border bg-slate-950/5 focus-visible:outline focus-visible:outline-2" class:cursor-grab={zoom > 1 && !dragging} class:cursor-grabbing={dragging} onwheel={onWheel} onpointerdown={onPointerDown} onpointermove={onPointerMove} onpointerup={onPointerUp} onpointercancel={onPointerUp} onkeydown={onKeyDown} ondblclick={fit}>
                <div
                    class="pointer-events-none relative inline-block origin-center rounded-md border bg-white shadow-sm"
                    style={`width: ${paperDisplayWidth}px; height: ${paperDisplayHeight}px; transform: translate(${panX}px, ${panY}px) scale(${zoom});`}
                >
                    {#key imageUrl}
                        <img
                            src={imageUrl}
                            alt={`Answer sheet reference page ${currentPage}`}
                            draggable="false"
                            class="h-full w-full rounded-md object-contain"
                        />
                    {/key}
                    {#if Array.isArray(activeBubbles) && activeBubbles.length > 0}
                        <svg viewBox={`0 0 ${pageWidth} ${pageHeight}`} class="absolute inset-0 h-full w-full" aria-hidden="true">
                            {#if activeSectionBounds}
                                <rect
                                    data-highlighted-question-set={activeSectionBounds.key}
                                    x={Math.max(0, activeSectionBounds.minX - 28)}
                                    y={Math.max(0, activeSectionBounds.minY - 36)}
                                    width={activeSectionBounds.maxX - activeSectionBounds.minX + 56}
                                    height={activeSectionBounds.maxY - activeSectionBounds.minY + 60}
                                    rx="18"
                                    fill="rgba(14, 165, 233, 0.08)"
                                    stroke="#0284c7"
                                    stroke-width="6"
                                    stroke-dasharray="16 8"
                                />
                                <rect
                                    x={Math.max(0, activeSectionBounds.minX - 28)}
                                    y={Math.max(0, activeSectionBounds.minY - 124)}
                                    width={Math.max(280, activeSectionBounds.title.length * 18 + 160)}
                                    height="44"
                                    rx="10"
                                    fill="#0284c7"
                                />
                                <text
                                    x={Math.max(0, activeSectionBounds.minX - 10)}
                                    y={Math.max(30, activeSectionBounds.minY - 95)}
                                    fill="#ffffff"
                                    font-size="25"
                                    font-weight="700"
                                >
                                    {activeSectionBounds.title} Set ({activeSectionBounds.count} items)
                                </text>
                            {/if}
                            {#if activeQuestionBounds}
                                {@const selectedTokens = selectedMark ? targetTokens(selectedMark) : []}
                                {@const calloutText = `${activeQuestionBounds.shortQuestion} (${activeQuestionBounds.label})${selectedTokens.length ? ` → Shade ${selectedMark?.intent === 'double' ? 'BOTH ' : ''}${selectedTokens.join(' + ')}` : ''}`}
                                <rect
                                    data-highlighted-question-row={activeQuestionBounds.label}
                                    x={Math.max(0, activeQuestionBounds.minX - 18)}
                                    y={Math.max(0, activeQuestionBounds.minY - 16)}
                                    width={activeQuestionBounds.maxX - activeQuestionBounds.minX + 36}
                                    height={activeQuestionBounds.maxY - activeQuestionBounds.minY + 38}
                                    rx="14"
                                    fill="rgba(37, 99, 235, 0.20)"
                                    stroke="#2563eb"
                                    stroke-width="6"
                                />
                                <rect
                                    x={Math.max(0, activeQuestionBounds.minX - 18)}
                                    y={Math.max(0, activeQuestionBounds.minY - 62)}
                                    width={Math.max(320, calloutText.length * 13.5 + 32)}
                                    height="42"
                                    rx="10"
                                    fill="#1d4ed8"
                                />
                                <text
                                    x={Math.max(0, activeQuestionBounds.minX - 2)}
                                    y={Math.max(28, activeQuestionBounds.minY - 34)}
                                    fill="#ffffff"
                                    font-size="22"
                                    font-weight="700"
                                >
                                    {calloutText}
                                </text>
                            {/if}
                            {#each activeBubbles as bubble (`${bubble.native_label ?? bubble.label ?? bubble.item_id}:${tokenOf(bubble)}:${bubble.x}:${bubble.y}`)}
                                {@const bLabel = String(bubble.native_label ?? bubble.label ?? bubble.item_id ?? '')}
                                {@const bSection = parseLabelIdentity(bLabel, bubble.section_name).sectionKey}
                                {@const bToken = tokenOf(bubble)}
                                {@const isSelectedQuestion = selectedQuestionLabel === bLabel}
                                {@const isTargetOfSelected = isSelectedQuestion && selectedMark && targetTokens(selectedMark).includes(bToken)}
                                {@const isDimmed = (selectedQuestionLabel && !isSelectedQuestion) || (!selectedQuestionLabel && highlightedSectionKey && bSection !== highlightedSectionKey)}
                                {@const center = centerOf(bubble)}
                                {@const intent = bubbleIntent(bubble)}
                                <g opacity={isDimmed ? 0.25 : 1}>
                                    {#if isTargetOfSelected}
                                        <circle
                                            data-highlighted-target-bubble={`${bLabel}:${bToken}`}
                                            cx={center.x}
                                            cy={center.y}
                                            r={Math.max(center.width, center.height) * 0.72}
                                            fill="rgba(37, 99, 235, 0.22)"
                                            stroke="#1d4ed8"
                                            stroke-width="4"
                                        />
                                    {/if}
                                    {#if intent === 'correct'}
                                        <circle cx={center.x} cy={center.y} r={Math.max(center.width, center.height) * 0.34} fill="#0f172a" stroke="#0f172a" stroke-width="2" />
                                    {:else if intent === 'wrong'}
                                        <circle cx={center.x} cy={center.y} r={Math.max(center.width, center.height) * 0.53} fill="rgba(245,158,11,0.22)" stroke="#f59e0b" stroke-width="3" />
                                    {:else if intent === 'double'}
                                        <circle cx={center.x} cy={center.y} r={Math.max(center.width, center.height) * 0.58} fill="rgba(239,68,68,0.22)" stroke="#ef4444" stroke-width="3" />
                                        <circle cx={center.x} cy={center.y} r={Math.max(center.width, center.height) * 0.34} fill="none" stroke="#ef4444" stroke-width="2.5" />
                                    {:else}
                                        <ellipse cx={center.x} cy={center.y} rx={center.width * 0.42} ry={center.height * 0.42} fill="none" stroke="rgba(148,163,184,0.55)" stroke-width="1.5" />
                                    {/if}
                                    {#if isSelectedQuestion && bToken}
                                        <text
                                            x={center.x}
                                            y={center.y + center.height * 0.85}
                                            text-anchor="middle"
                                            fill={isTargetOfSelected ? '#1d4ed8' : '#334155'}
                                            font-size="15"
                                            font-weight="700"
                                        >
                                            {bToken}
                                        </text>
                                    {/if}
                                </g>
                            {/each}
                            {#each [...questionRowsByLabel.values()] as qRow (qRow.label)}
                                <!-- svelte-ignore a11y_click_events_have_key_events a11y_no_static_element_interactions -->
                                <rect
                                    x={Math.max(0, qRow.minX - 10)}
                                    y={Math.max(0, qRow.minY - 8)}
                                    width={qRow.maxX - qRow.minX + 20}
                                    height={qRow.maxY - qRow.minY + 16}
                                    rx="8"
                                    fill="transparent"
                                    class="pointer-events-auto cursor-pointer"
                                    onclick={(e) => {
                                        e.stopPropagation();
                                        selectQuestionOnCanvas(qRow.label);
                                    }}
                                >
                                    <title>{qRow.sectionTitle} · {qRow.shortQuestion} ({qRow.label}) — Click to highlight</title>
                                </rect>
                            {/each}
                        </svg>
                    {/if}
                </div>
            </div>
        {:else}
            <div class="flex min-h-[350px] flex-col items-center justify-center gap-2 rounded-xl border bg-muted/20 p-6 text-center">
                <strong class="text-sm">Reference unavailable · Page {currentPage}</strong>
                <p class="max-w-md text-xs text-muted-foreground">This exact pinned page image is unavailable. Other pages and instances are not substituted.</p>
            </div>
        {/if}
        <p class="text-xs text-muted-foreground">Click any Question Set pill or question row to highlight its area on the sheet · Drag to pan after zooming · Wheel or +/- to zoom</p>
        <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs">
            {#each normalizedLegend as legend (legend.intent)}
                <span class="inline-flex items-center gap-2"><span class={`size-3.5 rounded-full border-2 ${legend.intent === 'correct' ? 'border-slate-900 bg-slate-900' : legend.intent === 'wrong' ? 'border-amber-500 bg-amber-500/20' : legend.intent === 'double' ? 'border-red-500 bg-red-500/20' : 'border-slate-400'}`}></span>{legend.label}</span>
            {/each}
        </div>
    </section>
    <aside class="min-w-0 space-y-3">
        <section class="rounded-xl border bg-card p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold">
                    Intentional Marks on Page {currentPage}
                    {#if selectedSectionKey}
                        <span class="text-sky-600 dark:text-sky-400">· {selectedSectionKey}</span>
                    {/if}
                </h3>
                {#if selectedSectionKey || selectedQuestionLabel}
                    <button
                        type="button"
                        class="text-primary text-xs font-medium underline underline-offset-4"
                        onclick={() => {
                            selectedSectionKey = null;
                            selectedQuestionLabel = null;
                            fit();
                        }}
                    >
                        Clear highlight
                    </button>
                {/if}
            </div>
            {#if filteredMarks.length > 0}
                <ul bind:this={marksListContainer} class="mt-3 max-h-[380px] space-y-2 overflow-y-auto pr-1">
                    {#each filteredMarks as mark, index (`${markQuestion(mark)}:${index}`)}
                        {@const qLabel = String(markQuestion(mark))}
                        {@const parsed = parseLabelIdentity(qLabel, mark?.section_name)}
                        {@const tokens = targetTokens(mark)}
                        {@const isSelected = selectedQuestionLabel === qLabel}
                        <li>
                            <button
                                type="button"
                                data-mark-label={qLabel}
                                class={`w-full rounded-lg border p-2.5 text-left text-xs transition focus-visible:outline focus-visible:outline-2 ${isSelected ? 'border-blue-600 bg-blue-500/10 ring-1 ring-blue-600' : 'hover:bg-muted/60'}`}
                                onclick={() => focusMark(mark)}
                            >
                                <div class="flex flex-wrap items-center justify-between gap-1">
                                    <span class="font-semibold">
                                        <span class="text-sky-700 dark:text-sky-300">{parsed.sectionTitle} · {parsed.shortQuestion}</span>
                                        <span class="text-muted-foreground ml-1 font-mono text-[10px]">({qLabel})</span>
                                    </span>
                                    <span class="font-semibold">→ Shade {mark.intent === 'double' ? 'BOTH ' : ''}({tokens.join(' + ') || '—'})</span>
                                </div>
                                <div class="mt-1 text-[11px] text-muted-foreground">
                                    {#if mark.key_token || mark.key}
                                        <span>[Key: {mark.key_token ?? mark.key}]</span>
                                    {/if}
                                    {#if mark.intent === 'correct'}<span class="text-foreground font-medium"> · Correct</span>
                                    {:else if mark.intent === 'wrong'}<span class="font-medium text-amber-600 dark:text-amber-400"> · Intentional Wrong (0 pt)</span>
                                    {:else if mark.intent === 'double'}<span class="font-medium text-red-600 dark:text-red-400"> · Double-Shade — Triggers Review / Rapid Triage</span>
                                    {:else}<span> · Leave blank</span>{/if}
                                </div>
                            </button>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p class="mt-2 text-xs text-muted-foreground">No answer marks are instructed on this page.</p>
            {/if}
        </section>
        {#if expectedSummary}
            <section class="rounded-xl border bg-muted/20 p-4" aria-label="Expected outcome summary">
                <h3 class="text-sm font-semibold">Expected Outcome Summary · Instance #{selectedInstance}</h3>
                <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                    <dt class="text-muted-foreground">Expected raw score</dt>
                    <dd class="text-right font-semibold">{expectedSummary.raw_score ?? expectedSummary.expected_raw_score ?? 'Unavailable'}</dd>
                    <dt class="text-muted-foreground">Expected flagged reviews</dt>
                    <dd class="text-right font-semibold">{expectedSummary.flagged_review_count ?? expectedSummary.expected_flagged_review_count ?? 0}</dd>
                </dl>
            </section>
        {/if}
    </aside>
</div>
