/**
 * Shared pipeline status configuration.
 *
 * Single source of truth for pipeline status colors, labels, and Chart.js palettes.
 * Consumed by Dashboard, Reports, and any component that visualizes pipeline data.
 *
 * All 11 pipeline statuses:
 *   pending, accepted, draft_scheduled, scheduled, printed,
 *   attended, submitted, scored, graded, released, dismissed
 */

// ─── Ordered list of all pipeline statuses ──────────────────────────────────

export const PIPELINE_STATUSES = [
  'pending',
  'accepted',
  'draft_scheduled',
  'scheduled',
  'printed',
  'attended',
  'submitted',
  'scored',
  'graded',
  'released',
  'dismissed',
];

// ─── Human-readable labels ──────────────────────────────────────────────────

export const PIPELINE_LABELS = {
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

// ─── Chart.js hex colors (for datasets, doughnuts, bars) ────────────────────

export const PIPELINE_CHART_COLORS = {
  pending: '#94a3b8',       // slate-400
  accepted: '#3b82f6',      // blue-500
  draft_scheduled: '#818cf8', // indigo-400
  scheduled: '#6366f1',     // indigo-500
  printed: '#8b5cf6',       // violet-500
  attended: '#a78bfa',      // violet-400
  submitted: '#7c3aed',     // violet-600
  scored: '#c084fc',        // purple-400
  graded: '#a855f7',        // purple-500
  released: '#22c55e',      // green-500
  dismissed: '#ef4444',     // red-500
};

// ─── Tailwind CSS class colors for bar fills ────────────────────────────────

export const PIPELINE_BAR_CLASSES = {
  pending: 'bg-slate-400',
  accepted: 'bg-blue-500',
  draft_scheduled: 'bg-indigo-400',
  scheduled: 'bg-indigo-500',
  printed: 'bg-violet-500',
  attended: 'bg-violet-400',
  submitted: 'bg-violet-600',
  scored: 'bg-purple-400',
  graded: 'bg-purple-500',
  released: 'bg-emerald-500',
  dismissed: 'bg-rose-500',
};

// ─── Tailwind CSS text colors for labels ────────────────────────────────────

export const PIPELINE_TEXT_CLASSES = {
  pending: 'text-slate-600 dark:text-slate-400',
  accepted: 'text-blue-600 dark:text-blue-400',
  draft_scheduled: 'text-indigo-600 dark:text-indigo-400',
  scheduled: 'text-indigo-600 dark:text-indigo-400',
  printed: 'text-violet-600 dark:text-violet-400',
  attended: 'text-violet-600 dark:text-violet-400',
  submitted: 'text-violet-600 dark:text-violet-400',
  scored: 'text-purple-600 dark:text-purple-400',
  graded: 'text-purple-600 dark:text-purple-400',
  released: 'text-emerald-600 dark:text-emerald-400',
  dismissed: 'text-rose-600 dark:text-rose-400',
};

// ─── Status groupings for KPI summary cards ─────────────────────────────────

export const PIPELINE_GROUPS = {
  active: ['pending', 'accepted', 'draft_scheduled', 'scheduled', 'printed', 'attended', 'submitted', 'scored'],
  completed: ['graded', 'released'],
  attention: ['dismissed'],
};

// ─── Helper: get ordered Chart.js arrays from a status→count map ────────────

/**
 * Given a { status: count } map, returns ordered arrays for Chart.js consumption.
 *
 * @param {Record<string, number>} statusCounts - e.g. { pending: 10, accepted: 5 }
 * @returns {{ labels: string[], data: number[], colors: string[] }}
 */
export function chartDataFromStatusCounts(statusCounts) {
  const labels = [];
  const data = [];
  const colors = [];

  for (const status of PIPELINE_STATUSES) {
    const count = statusCounts[status] ?? 0;
    if (count > 0 || true) { // include all for full funnel view
      labels.push(PIPELINE_LABELS[status] ?? status);
      data.push(count);
      colors.push(PIPELINE_CHART_COLORS[status] ?? '#94a3b8');
    }
  }

  return { labels, data, colors };
}
