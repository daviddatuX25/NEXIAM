/**
 * Finite typed-action catalog stubs for Nexi (GF master §8.5 / GF-22 / GF-06 §10).
 *
 * UI may *propose* a typed UI-equivalent request. Execution requires a later
 * allowlisted application command + GF-21 CapabilityAuthorizer + confirm + audit.
 * No generic “do anything” facility.
 */

/**
 * @typedef {'guide'|'apply'} NexiActionKind
 *
 * @typedef {{
 *   action_key: string,
 *   label: string,
 *   description: string,
 *   contexts: string[],
 *   kind: NexiActionKind,
 *   capability?: string|null,
 *   needs_confirm: boolean,
 *   command_stub: string,
 *   equivalent_ui: string,
 * }} NexiTypedAction
 */

/** @type {NexiTypedAction[]} */
export const NEXI_TYPED_ACTIONS = [
  {
    action_key: 'explain_page',
    label: 'Explain this page',
    description: 'Summarize what this screen is for and what you can safely do here.',
    contexts: ['*'],
    kind: 'guide',
    capability: null,
    needs_confirm: false,
    command_stub: 'AssistExplainPage',
    equivalent_ui: 'Guide / page header help',
  },
  {
    action_key: 'orient_filters',
    label: 'Orient filters',
    description: 'Walk through search, status, season, and date filters without changing them.',
    contexts: ['ui_kit', 'staff_generic', 'staff_home'],
    kind: 'guide',
    capability: null,
    needs_confirm: false,
    command_stub: 'AssistOrientFilters',
    equivalent_ui: 'Filter toolbar orientation',
  },
  {
    action_key: 'cite_policy',
    label: 'Cite related policy',
    description: 'Point to the governing policy page. Policy text is never an executable instruction.',
    contexts: ['*'],
    kind: 'guide',
    capability: null,
    needs_confirm: false,
    command_stub: 'AssistCitePolicy',
    equivalent_ui: 'Open policy document link',
  },
  {
    action_key: 'preview_bulk_accept',
    label: 'Apply: preview bulk accept',
    description:
      'Propose Accept on the current selection — same command contract as the bulk bar. No mutation until you confirm the real command.',
    contexts: ['ui_kit', 'staff_generic'],
    kind: 'apply',
    capability: 'applications.accept',
    needs_confirm: true,
    command_stub: 'AcceptApplications',
    equivalent_ui: 'Bulk action → Accept',
  },
  {
    action_key: 'preview_open_reservations',
    label: 'Apply: open reservations',
    description:
      'Propose opening reservation windows for scoped dates — typed UI-equivalent of the reservations setup control.',
    contexts: ['staff_home', 'staff_generic'],
    kind: 'apply',
    capability: 'reservations.manage',
    needs_confirm: true,
    command_stub: 'OpenReservations',
    equivalent_ui: 'Reservations → Open window',
  },
  {
    action_key: 'preview_schedule_accepted',
    label: 'Apply: schedule accepted',
    description:
      'Propose scheduling accepted applicants in scope. Server still enforces capability, window, and state.',
    contexts: ['staff_home', 'staff_generic'],
    kind: 'apply',
    capability: 'scheduling.manage',
    needs_confirm: true,
    command_stub: 'ScheduleAcceptedApplicants',
    equivalent_ui: 'Scheduling → Schedule accepted',
  },
  {
    action_key: 'draft_export_hint',
    label: 'Apply: export checklist',
    description: 'List what an export would include and which capability gates it before any file is produced.',
    contexts: ['ui_kit', 'staff_generic', 'staff_home'],
    kind: 'apply',
    capability: 'reports.export',
    needs_confirm: true,
    command_stub: 'ExportReport',
    equivalent_ui: 'Export / download control',
  },
  {
    action_key: 'escalate_human',
    label: 'Hand off to human',
    description: 'Stop assistant help and point to the manual page path / staff contact.',
    contexts: ['*'],
    kind: 'guide',
    capability: null,
    needs_confirm: false,
    command_stub: 'AssistEscalateHuman',
    equivalent_ui: 'Support / manual fallback',
  },
];

/**
 * @param {string} contextKey
 * @param {string[]} [grants]
 * @returns {(NexiTypedAction & { locked: boolean })[]}
 */
export function actionsForContext(contextKey, grants = []) {
  const grantSet = new Set(grants);
  const hasGrants = grants.length > 0;

  return NEXI_TYPED_ACTIONS.filter((action) => {
    return action.contexts.includes('*') || action.contexts.includes(contextKey);
  }).map((action) => {
    const locked = Boolean(action.capability && hasGrants && !grantSet.has(action.capability));
    return { ...action, locked };
  });
}
