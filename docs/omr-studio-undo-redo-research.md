# OMR Studio undo/redo research

Date: 2026-08-22

## Current code boundary

`OmrTemplateStudio.svelte` owns the editable studio state as several Svelte 5 `$state` values:

- `components`: Sheet Designer components.
- `sections`: Stage 3 / Upload Document bubble sections.
- Overlay and layout values: margins, marker pattern, QR reservation, printable overlays, and custom text.
- `htmlCode`: derived from `components` by `renderFullSheetHtml()`.
- Autosave: `triggerAutosave()` debounces the atomic studio PUT; the server remains authoritative through `lock_version`.

`StudioCanvas.svelte`, `StudioOverlayPanel.svelte`, and `StudioBubblePanel.svelte` mutate bindable state directly. Dragging and resizing mutate values continuously during pointer movement, then call the parent callback on pointer-up.

## Options

### Option A — bounded JSON snapshots

Keep a local ring buffer of plain editor-state snapshots:

```text
past[]  <- current ->  future[]
```

A committed edit pushes the previous snapshot to `past`, clears `future`, and applies the new snapshot. Undo moves the current snapshot to `future` and restores the last `past` snapshot. Redo performs the inverse operation.

Why it fits this codebase:

- The studio aggregate is already JSON-shaped and persisted as one `studio` payload.
- Current components and overlay values are small compared with raster assets.
- It does not require replacing direct mutable Svelte code in the first cut; a single snapshot/apply seam can wrap existing mutations.
- `structuredClone()` is a platform deep-copy primitive; MDN documents that it returns a deep copy and throws `DataCloneError` for unsupported values: <https://developer.mozilla.org/en-US/docs/Web/API/Window/structuredClone>.
- Svelte documents that arrays and simple objects in `$state` become deeply reactive proxies: <https://svelte.dev/docs/svelte/$state>. Use `$state.snapshot()` at the history boundary before cloning/serializing reactive state.

Costs:

- Each entry stores a complete snapshot.
- Every mutation must eventually call the history seam or it will be invisible to undo.

### Option B — Immer inverse patches

Use `produceWithPatches()` to produce `[nextState, patches, inversePatches]`, store inverse patches for undo and patches for redo, then use `applyPatches()` to restore state.

Immer explicitly documents patches as useful for undo/redo, debugging, and replay, and documents `produceWithPatches()` returning the next state plus patches and inverse patches: <https://immerjs.github.io/immer/patches/>.

Advantages:

- Smaller history entries for geometry and field edits.
- Precise operation-level history.
- A future path to replay/sync if collaboration is later required.

Costs:

- Immer is not currently installed in `system/package.json`.
- The current code mutates Svelte proxies directly; patch capture requires routing all meaningful edits through Immer producers or an equivalent controlled mutation adapter.
- Patches are not guaranteed to be minimal according to the official docs, so patch storage still needs bounds/coalescing.

### Option C — CRDT / Yjs

Yjs is a high-performance CRDT for collaborative applications. Its official documentation describes shared types that merge concurrent changes without conflicts and a network-agnostic provider boundary: <https://docs.yjs.dev/>.

This is not the right first cut. NEXIAM currently has one staff editor, one atomic server save, and optimistic `lock_version` conflict handling. A CRDT would introduce a new document model, provider, persistence strategy, and conflict semantics before collaboration is a requirement.

### Option D — browser History API

The browser History API navigates the tab's session history through `back()`, `forward()`, and `go()`: <https://developer.mozilla.org/en-US/docs/Web/API/History_API>.

It should not own editor undo. It changes navigation state and URL semantics, not the in-canvas document. It can be considered later for route-level state, but not for component edits.

## Recommendation

Implement **Option A: a bounded snapshot history module** first. It has the smallest seam and no dependency cost. Keep the implementation local to the studio and keep the server payload unchanged.

Recommended interface:

```ts
type StudioEditorState = {
  components: OmrComponent[];
  sections: SectionData[];
  layout: {
    marginMm: number;
    markerPattern: 'square' | 'target' | 'aruco';
    showQrOverlay: boolean;
    qrX: number;
    qrY: number;
    qrW: number;
    qrH: number;
    // printable overlay values and custom text items
  };
};

type HistoryEntry = {
  label: string;
  state: StudioEditorState;
  coalesceKey?: string;
};

interface StudioHistory {
  canUndo: boolean;
  canRedo: boolean;
  transact(label: string, update: () => void, options?: { coalesceKey?: string }): void;
  undo(): void;
  redo(): void;
  reset(state: StudioEditorState): void;
  clear(): void;
}
```

The implementation should expose a small interface and hide stack management. Do not expose the entire Svelte state object or the persistence client through the history module.

## Snapshot boundary

History should include durable authoring state only:

- components and their geometry/typography;
- independent Upload Document sections;
- margins, marker pattern, QR visibility/position/size;
- printable overlay values and custom text items.

History should exclude transient view or transport state:

- current stage and stepper navigation;
- selected component / selected overlay;
- active section index;
- pan and zoom;
- save status, lock version, upload progress, conversion errors;
- raster URLs and queued source-ingestion state.

`htmlCode` should remain derived, not stored in every history entry. After restoring a snapshot, recompute HTML and derived sections through the existing synchronization seam.

## Transaction rules

1. **Discrete edits** — palette add/remove, preset/scaffold, marker choice, QR toggle, mode reset: one history entry immediately.
2. **Pointer edits** — capture the pre-drag snapshot on pointer-down; update live during movement; commit exactly one entry on pointer-up. Escape restores the pre-drag snapshot without adding an entry.
3. **Text/number/range inputs** — update the UI live, but coalesce changes by field key and component id. Commit on blur/change or after a short idle window (roughly 300–500 ms). This prevents one slider movement or typed label from producing dozens of entries.
4. **Branching** — any new edit after undo clears the redo stack.
5. **Mode/source reset** — clear the history stack when changing authoring paths or replacing the uploaded source; those operations change the document domain rather than edit one document value.
6. **Remote conflict** — do not silently replay local history over a newer server draft. On `concurrency_conflict`, reload/rebase explicitly and either clear history or offer a deliberate discard/reload path.

## Persistence rules

Undo and redo are local editing operations. Do not PUT every history frame or add an undo table in the first cut.

After undo or redo:

1. Restore the durable editor snapshot.
2. Recompute `htmlCode` and derived `sections`.
3. Run the existing debounced atomic save.
4. Preserve normal `lock_version` and conflict handling.

A later offline/recovery feature may persist a bounded local draft history, but that is separate from server authoring state and should not be mixed into `authoring_json.studio` without an explicit retention contract.

## UX and accessibility

- Add Undo and Redo buttons near Save with `aria-label`, tooltip text, disabled state, and optional counts.
- Handle `Ctrl/Cmd+Z` for undo and `Ctrl/Cmd+Shift+Z` / `Ctrl/Cmd+Y` for redo on the builder surface.
- Ignore shortcuts while focus is in `input`, `textarea`, `select`, or `contenteditable`, so native text-field undo remains intact.
- Ignore key repeats for a single shortcut action.
- Use `keydown`; MDN documents `KeyboardEvent.key`, `ctrlKey`, `metaKey`, and `shiftKey` as the relevant modifier/key fields: <https://developer.mozilla.org/en-US/docs/Web/API/KeyboardEvent>.
- Announce the result through an existing status/live region, for example “Undid move Question Grid” or “Redid change QR size”.

## Bounds and correctness

- Start with 100 entries or a memory ceiling (for example 5 MB), whichever is reached first; evict the oldest entries.
- Clone snapshots at the boundary. Never store a live Svelte proxy or a mutable object reference in history.
- Add pure unit tests for push, undo, redo, branching, coalescing, reset, max-size eviction, and snapshot isolation.
- Add browser tests for add/remove, drag, resize, inspector input, QR resize, mode reset, and save-after-undo.

## Phased rollout

1. Extract a pure `studioHistory` module and test it with plain JSON fixtures.
2. Add `readEditorState()` / `applyEditorState()` in `OmrTemplateStudio.svelte`; route existing structural and overlay commands through `transact()`.
3. Add pointer transaction boundaries in `StudioCanvas` and coalesced input boundaries in inspectors/panels.
4. Add toolbar buttons and keyboard shortcuts with focus-safe handling.
5. Verify autosave, lock conflicts, mode reset, and upload replacement behavior.
6. Reconsider Immer patches only if snapshot size or future collaboration/replay requirements justify the added dependency and mutation refactor.
