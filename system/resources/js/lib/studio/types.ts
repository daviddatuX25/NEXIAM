/**
 * Pure domain-neutral types for the document studio core.
 * Contains zero references to OMR, applicant records, score tables, or result sheets.
 */

// ==========================================
// Geometry Types
// ==========================================
// Adapter Stage Chrome Types
// ==========================================

/**
 * Ordered opaque workflow stage metadata supplied by a studio adapter.
 *
 * The core chrome must never infer a stage's neighbours from numeric labels;
 * adapter-owned identifiers keep HTML, Word, and future workflows independent.
 */
export interface StudioStageDescriptor<StageId extends string = string> {
  readonly id: StageId;
  readonly label: string;
  readonly description?: string;
  readonly disabled?: boolean;
}

export interface StudioStageContext<StageId extends string = string> {
  readonly currentId: StageId;
  readonly stages: readonly StudioStageDescriptor<StageId>[];
}
/**
 * Adapter callback for moving to an opaque stage identifier.
 *
 * The shell passes the selected descriptor's id unchanged; adapters retain
 * ownership of stage ordering, guards, and transitions.
 */
export type StudioStageChangeHandler<StageId extends string = string> = (stageId: StageId) => void;
// ==========================================

export interface Point {
  readonly x: number;
  readonly y: number;
}

export interface Size {
  readonly width: number;
  readonly height: number;
}

export interface Rect {
  readonly x: number;
  readonly y: number;
  readonly width: number;
  readonly height: number;
}

export interface Bounds {
  readonly minX: number;
  readonly minY: number;
  readonly maxX: number;
  readonly maxY: number;
}

export interface Margins {
  readonly top: number;
  readonly right: number;
  readonly bottom: number;
  readonly left: number;
}

export interface IdentifiedRect extends Rect {
  readonly id: string;
}

export type Axis = 'x' | 'y';

export interface AxisLockResult {
  readonly dx: number;
  readonly dy: number;
}

export type AlignmentTarget = 'left' | 'center' | 'right' | 'top' | 'middle' | 'bottom';

export type DistributionAxis = 'horizontal' | 'vertical';

export interface SnapGuide {
  readonly axis: Axis;
  readonly position: number;
  readonly origin?: number;
  readonly target?: number;
}

export interface SnapResult {
  readonly snappedRect: Rect;
  readonly guides: readonly SnapGuide[];
}

// ==========================================
// Selection Types
// ==========================================

export interface SelectionState {
  readonly selectedIds: ReadonlySet<string>;
  readonly primaryId: string | null;
}

export interface SelectionSerializedState {
  readonly selectedIds: readonly string[];
  readonly primaryId: string | null;
}

export type MarqueeQueryMode = 'intersect' | 'contain';

// ==========================================
// Layer Types
// ==========================================

export interface LayerItem {
  readonly id: string;
  readonly locked?: boolean;
  readonly [key: string]: unknown;
}

export type LayerReorderAction = 'front' | 'back' | 'forward' | 'backward';

// ==========================================
// History Types
// ==========================================

export interface HistoryEntry<T> {
  readonly state: T;
  readonly label: string;
  readonly timestamp: number;
  readonly coalesceKey?: string;
}

export interface HistoryStatus {
  readonly canUndo: boolean;
  readonly canRedo: boolean;
  readonly undoLabel: string | null;
  readonly redoLabel: string | null;
  readonly pastCount: number;
  readonly futureCount: number;
  readonly isDirty: boolean;
}

export interface HistoryOptions<T = unknown> {
  readonly maxDepth?: number;
  readonly coalesceWindowMs?: number;
  readonly cloneFn?: (val: T) => T;
  readonly equalityFn?: (a: T, b: T) => boolean;
}

export interface GestureTransaction<T> {
  readonly label: string;
  readonly coalesceKey?: string;
  readonly initialState: T;
  readonly isCommitted: boolean;
  readonly isCancelled: boolean;
}

// ==========================================
// Keymap & Command Types
// ==========================================

export type StudioCommand =
  | 'undo'
  | 'redo'
  | 'delete'
  | 'duplicate'
  | 'selectAll'
  | 'deselect'
  | 'escape'
  | 'nudgeUp'
  | 'nudgeDown'
  | 'nudgeLeft'
  | 'nudgeRight'
  | 'nudgeUpLarge'
  | 'nudgeDownLarge'
  | 'nudgeLeftLarge'
  | 'nudgeRightLarge'
  | 'bringForward'
  | 'sendBackward'
  | 'bringToFront'
  | 'sendToBack'
  | 'lock'
  | 'unlock'
  | 'toggleLock'
  | 'help';

export interface KeyBinding {
  readonly key: string;
  readonly mod?: boolean;
  readonly shift?: boolean;
  readonly alt?: boolean;
  readonly command: StudioCommand;
  readonly allowRepeat?: boolean;
}

export type StudioKeyHandlers = Partial<
  Record<StudioCommand, (event: KeyboardEvent) => boolean | void>
>;

export interface StudioKeymapConfig {
  readonly bindings?: readonly KeyBinding[];
  readonly handlers?: StudioKeyHandlers;
}

// ==========================================
// Viewport Types
// ==========================================

export interface ViewportTransform {
  panX: number;
  panY: number;
  zoom: number;
}

export interface CursorZoomOptions {
  cursorX: number;
  cursorY: number;
  panX: number;
  panY: number;
  scaleOld: number;
  scaleNew: number;
  /** Current rendered paper-frame origin in the cursor coordinate space. */
  frameX?: number;
  frameY?: number;
  /** Rendered paper-frame origin after the scale/layout update. */
  nextFrameX?: number;
  nextFrameY?: number;
}

export interface CursorZoomResult {
  panX: number;
  panY: number;
  scale: number;
}

export interface FitViewportOptions {
  viewportWidth: number;
  viewportHeight: number;
  pageWidthPx: number;
  pageHeightPx: number;
  minZoom?: number;
  maxZoom?: number;
  gutter?: number;
}
