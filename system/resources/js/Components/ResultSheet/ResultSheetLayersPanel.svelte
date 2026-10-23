<script>
  import {
    Layers,
    Lock,
    Unlock,
    ArrowUp,
    ArrowDown,
    ChevronsUp,
    ChevronsDown,
    GripVertical,
    FileText,
    UserRound,
    CalendarClock,
    Brackets,
    Table2,
    BadgePercent,
    Sparkles,
    PenLine,
    StickyNote,
    AlignLeft,
    Image as ImageIcon,
    Minus,
    SquareDashed,
    Trash2,
  } from 'lucide-svelte';
  import {
    bringToFront,
    sendToBack,
    moveUp,
    moveDown,
    moveItemsToIndex,
    toggleLocked,
    filterUnlocked,
    isLocked,
    reorderLayers,
    bringLayerToFront,
    sendLayerToBack,
    moveLayerUp,
    moveLayerDown,
  } from '@/lib/studio';

  let {
    components: providedComponents = null,
    items = null,
    selectedIds: providedSelectedIds = new Set(),
    primaryId = null,
    builderCatalog = {},
    onSelect = () => {},
    onCommit = () => {},
    onReorder = null,
    onToggleLock = null,
    onDelete = () => {},
    onAnnounce = () => {},
  } = $props();

  let components = $derived(providedComponents ?? items ?? []);
  let selectedIds = $derived(
    providedSelectedIds instanceof Set
      ? providedSelectedIds
      : new Set(providedSelectedIds ?? []),
  );
  let legacyMode = $derived(items !== null);

  let draggedIndex = $state(null);
  let dragOverIndex = $state(null);
  let dragOverPosition = $state('above');
  let touchDraggedIndex = $state(null);
  let touchTargetIndex = $state(null);
  let touchPosition = $state('above');


  function componentIcon(type) {
    return (
      {
        header: FileText,
        identity: UserRound,
        exam_details: CalendarClock,
        field: Brackets,
        score_table: Table2,
        overall: BadgePercent,
        recommendation: Sparkles,
        signature: PenLine,
        note: StickyNote,
        footer: FileText,
        text: AlignLeft,
        image: ImageIcon,
        divider: Minus,
        spacer: SquareDashed,
      }[type] ?? FileText
    );
  }

  function componentLabel(component) {
    if (legacyMode && component.name) {
      return String(component.name);
    }

    const catalogItem = builderCatalog[component.type];
    const typeLabel = catalogItem?.label ?? String(component.type).replace(/_/g, ' ');
    if (component.type === 'field' && component.props?.binding) {
      return `${typeLabel}: ${component.props.binding}`;
    }
    if (component.type === 'signature' && component.props?.label) {
      return `${typeLabel}: ${component.props.label}`;
    }
    if (component.type === 'header' && component.props?.title) {
      return `${typeLabel}: ${component.props.title}`;
    }
    if (['text', 'note', 'footer'].includes(component.type) && component.props?.text) {
      const preview = component.props.text.slice(0, 18);
      return `${typeLabel}: "${preview}${component.props.text.length > 18 ? '…' : ''}"`;
    }
    return typeLabel;
  }

  const selectedArray = $derived(Array.from(selectedIds));
  const hasSelection = $derived(selectedArray.length > 0);
  const unlockedSelected = $derived(filterUnlocked(components, selectedArray));
  const hasUnlockedSelection = $derived(unlockedSelected.length > 0);

  // Layers in visual top-to-bottom order (highest array index at top)
  const visualLayers = $derived(
    components
      .map((component, index) => ({
        component,
        index,
        isSelected: selectedIds.has(component.id),
        isPrimary: primaryId === component.id,
        isLocked: isLocked(component),
      }))
      .reverse()
  );

  function commitChanges(updated, message) {
    if (legacyMode && typeof onReorder === 'function') {
      onReorder(updated);

      return;
    }

    onCommit(updated, message);
  }

  function handleLayerClick(event, componentId) {
    event.stopPropagation();
    onSelect(componentId, { additive: event.shiftKey || event.ctrlKey || event.metaKey });
  }

  function isNestedInteractiveTarget(event) {
    const target = event.target;
    return Boolean(
      target &&
        typeof target.closest === 'function' &&
        target.closest('button, a, input, select, textarea, [role="button"], [role="link"], [contenteditable="true"]')
    );
  }

  function handleLayerKeydown(event, componentId) {
    if (legacyMode && event.altKey && event.key.toLowerCase() === 'l') {
      event.preventDefault();

      if (typeof onToggleLock === 'function') {
        onToggleLock(componentId);
      }

      return;
    }

    if (legacyMode && event.altKey) {
      const updated = event.shiftKey || event.key === 'Home' || event.key === 'End'
        ? (event.key === 'ArrowUp' || event.key === 'Home'
          ? bringLayerToFront(components, componentId)
          : sendLayerToBack(components, componentId))
        : (event.key === 'ArrowUp'
          ? moveLayerUp(components, componentId)
          : event.key === 'ArrowDown'
            ? moveLayerDown(components, componentId)
            : components);

      if (updated !== components) {
        event.preventDefault();
        commitChanges(updated, 'Reorder layers');
      }

      return;
    }

    if (isNestedInteractiveTarget(event)) return;
    if (event.key !== 'Enter' && event.key !== ' ' && event.code !== 'Space') return;

    event.preventDefault();
    handleLayerClick(event, componentId);
  }

  function handleToggleLock(event, componentId) {
    event.stopPropagation();

    if (legacyMode && typeof onToggleLock === 'function') {
      onToggleLock(componentId);

      return;
    }

    const updated = toggleLocked(components, [componentId]);
    const target = updated.find((c) => c.id === componentId);
    commitChanges(updated, `Toggle lock ${componentId}`);
    onAnnounce(target?.locked ? 'Component locked' : 'Component unlocked');
  }

  function handleBatchToggleLock() {
    if (!hasSelection) return;
    const allLocked = selectedArray.every((id) => {
      const c = components.find((comp) => comp.id === id);
      return c?.locked;
    });
    const nextLocked = !allLocked;
    const updated = components.map((c) =>
      selectedIds.has(c.id) ? { ...c, locked: nextLocked } : c
    );
    commitChanges(updated, `${nextLocked ? 'Lock' : 'Unlock'} selected layers`);
    onAnnounce(`${selectedArray.length} layer(s) ${nextLocked ? 'locked' : 'unlocked'}`);
  }

  function handleBringToFront() {
    if (!hasUnlockedSelection) return;
    const updated = bringToFront(components, unlockedSelected);
    commitChanges(updated, 'Bring layers to front');
    onAnnounce('Brought selected layer(s) to front');
  }

  function handleSendToBack() {
    if (!hasUnlockedSelection) return;
    const updated = sendToBack(components, unlockedSelected);
    commitChanges(updated, 'Send layers to back');
    onAnnounce('Sent selected layer(s) to back');
  }

  function handleMoveUp() {
    if (!hasUnlockedSelection) return;
    const updated = moveUp(components, unlockedSelected);
    commitChanges(updated, 'Move layers forward');
    onAnnounce('Moved selected layer(s) forward');
  }

  function handleMoveDown() {
    if (!hasUnlockedSelection) return;
    const updated = moveDown(components, unlockedSelected);
    commitChanges(updated, 'Move layers backward');
    onAnnounce('Moved selected layer(s) backward');
  }

  // HTML5 Drag and Drop for stable layer reordering
  function handleDragStart(event, originalIndex) {
    const component = components[originalIndex];
    if (!component || component.locked) {
      event.preventDefault();
      return;
    }
    draggedIndex = originalIndex;
    if (event.dataTransfer) {
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', String(originalIndex));
    }
  }

  function handleDragOver(event, targetIndex) {
    event.preventDefault();
    if (event.dataTransfer) {
      event.dataTransfer.dropEffect = 'move';
    }
    dragOverIndex = targetIndex;

    if (legacyMode) {
      const target = event.currentTarget;
      const rect = target?.getBoundingClientRect?.();
      const midpoint = rect ? rect.top + rect.height / 2 : 0;
      dragOverPosition = event.clientY > midpoint ? 'below' : 'above';
    }
  }

  function handleDragLeave() {
    dragOverIndex = null;
    dragOverPosition = 'above';
  }

  function handleDrop(event, targetIndex) {
    event.preventDefault();
    if (draggedIndex === null || draggedIndex === targetIndex) {
      draggedIndex = null;
      dragOverIndex = null;
      dragOverPosition = 'above';

      return;
    }

    const draggedComponent = components[draggedIndex];
    if (!draggedComponent || draggedComponent.locked) {
      draggedIndex = null;
      dragOverIndex = null;
      dragOverPosition = 'above';

      return;
    }

    const idsToMove = selectedIds.has(draggedComponent.id)
      ? filterUnlocked(components, selectedArray)
      : [draggedComponent.id];

    if (idsToMove.length === 0) {
      draggedIndex = null;
      dragOverIndex = null;
      dragOverPosition = 'above';

      return;
    }

    const target = components[targetIndex];
    const updated = legacyMode && target
      ? reorderLayers(components, idsToMove, target.id, dragOverPosition)
      : moveItemsToIndex(components, idsToMove, targetIndex);

    draggedIndex = null;
    dragOverIndex = null;
    dragOverPosition = 'above';
    commitChanges(updated, 'Reorder layers');
    onAnnounce('Layers reordered');
  }

  function handleDragEnd() {
    draggedIndex = null;
    dragOverIndex = null;
    dragOverPosition = 'above';
  }
  function handleTouchStart(event, originalIndex) {
    if (!legacyMode) {
      return;
    }

    const component = components[originalIndex];
    if (!component || component.locked) {
      return;
    }

    touchDraggedIndex = originalIndex;
    touchTargetIndex = originalIndex;
    touchPosition = 'above';
  }

  function handleTouchMove(event) {
    if (!legacyMode || touchDraggedIndex === null) {
      return;
    }

    event.preventDefault();
    const touch = event.touches?.[0];
    if (!touch) {
      return;
    }

    const target = document.elementFromPoint(touch.clientX, touch.clientY)?.closest?.('[data-layer-id]');
    const targetId = target?.getAttribute('data-layer-id');
    const targetIndex = components.findIndex((component) => component.id === targetId);

    if (targetIndex < 0) {
      return;
    }

    const rect = target.getBoundingClientRect();
    touchTargetIndex = targetIndex;
    touchPosition = touch.clientY > rect.top + rect.height / 2 ? 'below' : 'above';
  }

  function handleTouchEnd() {
    if (!legacyMode || touchDraggedIndex === null || touchTargetIndex === null) {
      return;
    }

    const dragged = components[touchDraggedIndex];
    const target = components[touchTargetIndex];

    if (dragged && target && dragged.id !== target.id && !dragged.locked) {
      const updated = reorderLayers(components, dragged.id, target.id, touchPosition);
      if (updated !== components) {
        commitChanges(updated, 'Reorder layers');
      }
    }

    touchDraggedIndex = null;
    touchTargetIndex = null;
    touchPosition = 'above';
  }

</script>

<div class="flex flex-col space-y-2 text-xs">
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-1.5 font-bold uppercase tracking-wider text-studio-panel-foreground">
      <Layers class="size-3.5 text-studio-panel-muted-foreground" />
      <span>Layers</span>
      <span
        class="rounded-full bg-studio-panel-muted px-1.5 py-0.5 text-[10px] font-semibold text-studio-panel-muted-foreground"
        aria-label={`${components.length} placed components`}
      >
        {components.length} placed
      </span>
    </div>

    <!-- Stacking Actions -->
    <div class="flex items-center gap-0.5">
      <button
        type="button"
        class="rounded p-1 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground disabled:opacity-30 disabled:pointer-events-none"
        title="Bring to front (Ctrl+Shift+])"
        aria-label={legacyMode ? 'Bring selected layers to front' : 'Bring to front'}
        disabled={!hasUnlockedSelection}
        onclick={handleBringToFront}
      >
        <ChevronsUp class="size-3.5" />
      </button>
      <button
        type="button"
        class="rounded p-1 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground disabled:opacity-30 disabled:pointer-events-none"
        title="Bring forward (Ctrl+])"
        aria-label={legacyMode ? 'Move selected layers up' : 'Bring forward'}
        disabled={!hasUnlockedSelection}
        onclick={handleMoveUp}
      >
        <ArrowUp class="size-3.5" />
      </button>
      <button
        type="button"
        class="rounded p-1 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground disabled:opacity-30 disabled:pointer-events-none"
        title="Send backward (Ctrl+[)"
        aria-label={legacyMode ? 'Move selected layers down' : 'Send backward'}
        disabled={!hasUnlockedSelection}
        onclick={handleMoveDown}
      >
        <ArrowDown class="size-3.5" />
      </button>
      <button
        type="button"
        class="rounded p-1 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground disabled:opacity-30 disabled:pointer-events-none"
        title="Send to back (Ctrl+Shift+[)"
        aria-label={legacyMode ? 'Send selected layers to back' : 'Send to back'}
        disabled={!hasUnlockedSelection}
        onclick={handleSendToBack}
      >
        <ChevronsDown class="size-3.5" />
      </button>
      <button
        type="button"
        class="rounded p-1 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground disabled:opacity-30 disabled:pointer-events-none"
        title="Toggle lock (Ctrl+L)"
        aria-label="Toggle lock for selected layers"
        disabled={!hasSelection}
        onclick={handleBatchToggleLock}
      >
        <Lock class="size-3.5" />
      </button>
      {#if hasSelection}
        <button
          type="button"
          class="rounded p-1 text-destructive hover:bg-destructive/10 hover:text-destructive"
          title="Delete selected (Delete)"
          aria-label="Delete selected layers"
          onclick={onDelete}
        >
          <Trash2 class="size-3.5" />
        </button>
      {/if}
    </div>
  </div>

  {#if components.length === 0}
    <div class="rounded-lg border border-dashed border-studio-border p-4 text-center text-[11px] text-studio-panel-muted-foreground">
      No layers yet. Add components from the Add components panel.
    </div>
  {:else}
    <div
      class="max-h-60 space-y-1 overflow-y-auto rounded-lg border border-studio-border bg-studio-panel-muted p-1"
      role="listbox"
      aria-label="Document layers"
    >
      {#each visualLayers as item (item.component.id)}
        {@const Icon = componentIcon(item.component.type)}
        {@const isDropTarget = dragOverIndex === item.index}
        <div
          role="option"
          tabindex="0"
          data-result-sheet-layer={item.component.id}
          data-layer-id={legacyMode ? item.component.id : undefined}
          data-result-sheet-selected={item.isSelected}
          aria-selected={item.isSelected}
          aria-label={`${componentLabel(item.component)} layer${item.isSelected ? ' (selected)' : ''}`}
          class={`group flex items-center gap-1.5 rounded-md border px-2 py-1.5 text-left transition-all ${
            item.isSelected
              ? 'border-studio-focus bg-studio-selection text-studio-selection-foreground shadow-sm'
              : 'border-transparent bg-studio-panel hover:border-studio-border hover:bg-studio-panel-muted text-studio-panel-foreground'
          } ${item.isPrimary ? 'ring-1 ring-studio-focus' : ''} ${
            isDropTarget ? 'border-t-2 border-t-studio-focus bg-studio-selection/60' : ''
          } ${item.isLocked ? 'opacity-80' : ''}`}
          draggable={!item.isLocked}
          ondragstart={(event) => handleDragStart(event, item.index)}
          ondragover={(event) => handleDragOver(event, item.index)}
          ondragleave={handleDragLeave}
          ondrop={(event) => handleDrop(event, item.index)}
          ondragend={handleDragEnd}
          onclick={(event) => handleLayerClick(event, item.component.id)}
          onkeydown={(event) => handleLayerKeydown(event, item.component.id)}
          ontouchstart={(event) => handleTouchStart(event, item.index)}
          ontouchmove={handleTouchMove}
          ontouchend={handleTouchEnd}
        >
          <GripVertical
            class={`size-3 shrink-0 text-studio-panel-muted-foreground group-hover:text-studio-panel-foreground ${
              item.isLocked ? 'cursor-not-allowed opacity-30' : 'cursor-grab'
            }`}
          />
          <Icon
            class={`size-3.5 shrink-0 ${
              item.isSelected ? 'text-studio-selection-foreground' : 'text-studio-panel-muted-foreground group-hover:text-studio-panel-foreground'
            }`}
          />
          <span class="min-w-0 flex-1 truncate text-[11px] font-medium leading-tight">
            {componentLabel(item.component)}
          </span>

          <span class="font-mono text-[9px] text-studio-panel-muted-foreground shrink-0">
            #{item.index + 1}
          </span>

          <button
            type="button"
            class={`rounded p-0.5 text-studio-panel-muted-foreground transition hover:bg-studio-panel-muted hover:text-studio-panel-foreground ${
              item.isLocked ? 'text-studio-warning-foreground' : ''
            }`}
            title={item.isLocked ? 'Unlock layer' : 'Lock layer'}
            aria-label={legacyMode
              ? `${item.isLocked ? 'Unlock' : 'Lock'} ${componentLabel(item.component)}`
              : (item.isLocked ? 'Unlock layer' : 'Lock layer')}
            onclick={(event) => handleToggleLock(event, item.component.id)}
          >
            {#if item.isLocked}
              <Lock class="size-3" />
            {:else}
              <Unlock class="size-3 opacity-0 group-hover:opacity-100" />
            {/if}
          </button>
        </div>
      {/each}
    </div>
  {/if}
</div>
