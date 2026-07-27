/**
 * Shared helpers for applicant pipeline status display.
 * Used by Applications/Index, Applications/Show, and related pages.
 *
 * Pipeline order:
 *   f2f:     pending → accepted → draft_scheduled → scheduled → printed → attended → submitted → graded → released → dismissed
 *   direct:  pending → accepted → scored → graded → released → dismissed
 */

const PIPELINE_ORDER = {
  pending: 0,
  accepted: 1,
  draft_scheduled: 2,
  scheduled: 3,
  printed: 4,
  attended: 5,
  submitted: 6,
  scored: 7,
  graded: 8,
  released: 9,
  dismissed: 10,
};

const PIPELINE_LABELS = {
  pending: 'Pending',
  accepted: 'Accepted',
  draft_scheduled: 'Draft Scheduled',
  scheduled: 'Scheduled',
  printed: 'Printed',
  attended: 'Attended',
  submitted: 'Submitted',
  scored: 'Scored',
  graded: 'Graded',
  released: 'Released',
  dismissed: 'Dismissed',
};

const PIPELINE_VARIANTS = {
  pending: 'warning',
  accepted: 'success',
  draft_scheduled: 'muted',
  scheduled: 'outline',
  printed: 'secondary',
  attended: 'info',
  submitted: 'info',
  scored: 'info',
  graded: 'success',
  released: 'success',
  dismissed: 'danger',
};

const PIPELINE_ICONS = {
  pending: 'clock',
  accepted: 'check-circle',
  draft_scheduled: 'file-edit',
  scheduled: 'calendar',
  printed: 'printer',
  attended: 'user-check',
  submitted: 'send',
  scored: 'clipboard-check',
  graded: 'award',
  released: 'unlock',
  dismissed: 'x-circle',
};

/**
 * Returns the badge variant (color) for a pipeline status.
 * Maps to shadcn-svelte Badge variant names.
 */
export function pipelineBadgeVariant(status) {
  return PIPELINE_VARIANTS[status] ?? 'muted';
}

/**
 * Returns the human-readable label for a pipeline status.
 */
export function pipelineStatusLabel(status) {
  return PIPELINE_LABELS[status] ?? status;
}

/**
 * Returns the sort order number for a pipeline status.
 * Lower = earlier in pipeline.
 */
export function pipelineOrder(status) {
  return PIPELINE_ORDER[status] ?? 99;
}

/**
 * Returns the full list of pipeline statuses for filter dropdowns.
 */
export function pipelineStatusOptions() {
  return Object.entries(PIPELINE_LABELS).map(([value, label]) => ({ value, label }));
}

/**
 * Returns visible milestones for the progress bar, filtered by context.
 * - isF2f: show Printed milestone (f2f/scheduled sessions only)
 * - isDirect: show direct assessment pipeline (scored instead of draft/scheduled/attended/submitted)
 */
export function pipelineMilestones({ isF2f = false, isDirect = false } = {}) {
  // Direct assessment pipeline: simpler flow
  if (isDirect) {
    return [
      { key: 'pending', label: 'Pending' },
      { key: 'accepted', label: 'Accepted' },
      { key: 'scored', label: 'Scored' },
      { key: 'graded', label: 'Graded' },
      { key: 'released', label: 'Released' },
    ];
  }

  // Scheduled (f2f) pipeline: full flow
  const milestones = [
    { key: 'pending', label: 'Pending' },
    { key: 'accepted', label: 'Accepted' },
    { key: 'draft_scheduled', label: 'Draft' },
    { key: 'scheduled', label: 'Scheduled' },
    { key: 'printed', label: 'Printed', f2fOnly: true },
    { key: 'attended', label: 'Attended' },
    { key: 'submitted', label: 'Submitted' },
    { key: 'graded', label: 'Graded' },
    { key: 'released', label: 'Released' },
  ];

  return milestones.filter((m) => !m.f2fOnly || isF2f);
}