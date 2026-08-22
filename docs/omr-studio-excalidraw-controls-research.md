# OMR Studio Excalidraw-style controls research

Date: 2026-08-22

## Primary-source findings

- Excalidraw models undo/redo as a dedicated `History` module with separate undo and redo stacks. It records non-empty durable deltas, clears redo on durable local changes, and applies history entries without re-recording them: <https://github.com/excalidraw/excalidraw/blob/master/packages/excalidraw/history.ts>.
- Excalidraw treats duplication and deletion as explicit actions, not side effects hidden inside rendering. Its duplicate action creates new element identities, offsets the duplicate, selects the duplicate, and binds the keyboard shortcut to Ctrl/Cmd+D: <https://github.com/excalidraw/excalidraw/blob/master/packages/excalidraw/actions/actionDuplicateSelection.tsx>.
- Excalidraw's delete action operates on the complete current selection and exposes Delete/Backspace behavior through one action path: <https://github.com/excalidraw/excalidraw/blob/master/packages/excalidraw/actions/actionDeleteSelected.tsx>.
- Excalidraw exposes layer movement as separate actions: send backward, bring forward, send to back, and bring to front. Its shortcuts use Ctrl/Cmd plus bracket keys, with platform-specific modifiers for send-to-back/front: <https://github.com/excalidraw/excalidraw/blob/master/packages/excalidraw/actions/actionZindex.tsx>.
- Excalidraw normalizes shortcut labels through one helper that maps Ctrl/Cmd, Alt/Option, Shift, Delete, and other keys to platform-aware labels: <https://github.com/excalidraw/excalidraw/blob/master/packages/excalidraw/shortcut.ts>.

## Adaptation contract for NEXIAM

Current NEXIAM has one `selectedComponentId`, direct pointer dragging, a persisted component array, a layer list, and bounded local snapshot history. The smallest Excalidraw-like extension is a selection set plus explicit commands.

### Selection

```ts
selectedComponentIds: string[]
```

- Plain click selects one component.
- Shift-click toggles a component in the selection.
- Clicking blank canvas clears the selection.
- Layer-list click selects one row; Shift-click toggles rows.
- The inspector remains single-target until a multi-selection inspector is needed; multi-selection still supports delete, duplicate, move, and z-order.

Do not add grouping, frames, or deep-select semantics yet. They are not required by the OMR sheet contract.

### Delete

- Delete and Backspace remove the selected components.
- Ignore the shortcut while focus is inside input, textarea, select, or contenteditable controls.
- Use the existing component removal command so history and autosave see one durable transaction.
- Keep the visible Remove control as the accessible fallback.

### Duplicate

- Ctrl/Cmd+D duplicates the current selection.
- Alt/Option-drag duplicates on pointer-down, then moves the duplicate.
- New IDs are required for every duplicate; preserve all component configuration and z-order, then offset by a small grid step.
- The duplicate becomes the active selection.
- Do not duplicate locked components unless the selected source is explicitly unlocked.

### Layer movement

Adopt the same four semantic commands as Excalidraw:

- Bring forward.
- Send backward.
- Bring to front.
- Send to back.

Keep the current layer-list raise/lower buttons. Add keyboard shortcuts only after the command path exists:

- Ctrl/Cmd+]
- Ctrl/Cmd+[
- Ctrl/Cmd+Shift+]
- Ctrl/Cmd+Shift+[

The array order plus persisted `zIndex` remains the single layer authority.

### History integration

Route selection-changing durable commands through the existing snapshot history, but do not create history entries for selection-only changes. Record one entry for:

- multi-delete;
- duplicate;
- alt-drag duplicate + move;
- layer movement;
- group movement of a multi-selection;
- input/geometry edits with existing coalescing.

## Deferred controls

- Group/ungroup.
- Frames and nested containers.
- Deep selection inside groups.
- Box-select/lasso select until multi-selection is stable.
- Collaboration-aware history or CRDTs.
- Full Excalidraw tool mode parity.

## Implementation plan

1. Extract selection helpers and add `selectedComponentIds`, preserving `selectedComponentId` as the inspector primary target.
2. Add command functions: `select`, `toggleSelection`, `clearSelection`, `deleteSelection`, `duplicateSelection`, `moveSelection`, and four layer commands.
3. Add Delete/Backspace and Ctrl/Cmd+D keyboard handlers with text-control guards.
4. Add Alt/Option-drag duplication in `StudioCanvas` using a pre-drag duplicate transaction.
5. Update canvas and layer list visual states for multi-selection and copy-drag feedback.
6. Route commands into snapshot history and autosave exactly once per durable transaction.
 
7. Add unit/browser coverage for Shift-click, Delete, Ctrl/Cmd+D, Alt-drag, layer shortcuts, locked elements, and text-input shortcut guards.

## Implemented in Sheet Designer

- `selectedComponentIds` is the durable UI selection set; `selectedComponentId` remains the inspector primary target.
- Plain click selects one component; Shift-click toggles canvas or layer-list membership; clicking the blank canvas clears selection.
- Delete/Backspace removes the complete selection; Ctrl/Cmd+D duplicates unlocked selected components with collision-safe IDs and an 8 mm offset.
- Alt/Option-drag duplicates the clicked unlocked component or selected group, then moves the copies with a copy cursor and blue drag ring.
- Dragging a selected component preserves the group selection; locked components remain selectable but do not move or duplicate.
- Ctrl/Cmd bracket shortcuts retain the existing four layer actions; grouping, lasso, frames, and deep selection remain deferred.

- The single QR reservation now participates in the shared canvas selection and layer list; it can be selected, locked, moved with a group, and deleted, but duplicate commands skip it because the server contract has one `blank_instance_qr` reservation.
- Shift-drag locks movement to the dominant axis after a small pointer threshold; Alt+Shift-drag duplicates selected components and applies the same axis lock to the copy drag.

## Examinee matrix footprint parity

- The Stage-1 matrix border previously used a bubble-only height formula while its DOM added title/write-in rows and a fixed CSS gap; row 8/9 overflow was therefore deterministic.
- The corrected contract shares the full title/write-in/padding/ten-row footprint and uses the configured Row Pitch for both row spacing and bounds.

## Matrix width and pitch constraints

- Stage-1 matrix canvas width now uses the measured label/bubble footprint rather than the persisted legacy width, so movement bounds do not reserve dead space.
- The rendered pitch floor is 5.2 mm (bubble diameter plus the 1.75 px canvas gap); inputs normalize below-floor values and expose explicit maxima.
- Question-grid normalization reduces pitch/row count and clamps placement when a requested geometry cannot fit the printable page.

## Canonical aptitude scaffold

- The canonical workflow filters the backend catalog to six compatibility codes and orders them as General Ability, Verbal Aptitude, Numerical Aptitude, Spatial Aptitude, Perceptual Aptitude, Manual Dexterity.
- The Sheet Designer scaffold deliberately creates 25 questions per area, yielding contiguous Q1–Q150 regardless of catalog `max_items` values used by legacy scoring fixtures.
