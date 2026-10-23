/**
 * FIXTURE / DEMO ONLY — not production authority.
 * OpenSpec ai-assistant-foundation: page context comes from server PageCapabilityPack
 * via XiamSessionView. Do not use this module to decide mode, tools, or capabilities.
 *

 * Page-aware Xiam context (presentational).
 * Real retrieval/authz stays server-side under GF-08/21/22 — this only labels the shell.
 *
 * Context families from GF-06 §10:
 * - policy: approved institutional policy citations + link to governing page
 * - own_data: actor-scoped released/authorized data only
 * - staff_task: propose typed UI-equivalent commands with confirm
 * - page_help: orientation without domain mutation
 */

/**
 * @typedef {'policy'|'staff_task'|'page_help'|'own_data'} XiamContextKind
 * @typedef {{
 *   key: string,
 *   title: string,
 *   kind: XiamContextKind,
 *   summary: string,
 *   assistive_hint: string,
 * }} XiamPageContext
 */

/**
 * @param {string} url
 * @returns {XiamPageContext}
 */
export function resolveXiamContext(url = '/') {
  const path = String(url).split('?')[0].split('#')[0] || '/';

  if (path.startsWith('/ui-kit')) {
    return {
      key: 'ui_kit',
      title: 'UI kit gallery',
      kind: 'page_help',
      summary: 'I can orient you to kit patterns and demo gated Apply previews. I will not invent domain commands here.',
      assistive_hint: 'Try filters, bulk bar, then open Xiam for typed Apply chips.',
    };
  }

  if (path === '/staff' || path.startsWith('/staff/')) {
    return {
      key: 'staff_home',
      title: 'Staff home',
      kind: 'staff_task',
      summary: 'Capability-scoped workspace. Apply chips propose the same commands as page controls — never a back door.',
      assistive_hint: 'Ask what to do next, or preview an allowlisted Apply action.',
    };
  }

  if (path.startsWith('/login')) {
    return {
      key: 'login',
      title: 'Staff login',
      kind: 'page_help',
      summary: 'Sign-in help only. No staff data is available here.',
      assistive_hint: 'Use your institutional credentials to continue.',
    };
  }

  return {
    key: 'staff_generic',
    title: 'Staff console',
    kind: 'staff_task',
    summary: 'Page-aware tips and allowlisted Apply previews. Mutations need confirmation and GF-21 checks.',
    assistive_hint: 'Use Guide chips for orientation; Apply chips for gated previews.',
  };
}

/**
 * @param {XiamContextKind} kind
 */
export function kindLabel(kind) {
  if (kind === 'policy') return 'Policy';
  if (kind === 'own_data') return 'Own data';
  if (kind === 'staff_task') return 'Task assist';
  return 'Page help';
}

const XIAM_PACK_TITLES = {
  'staff.workspace.home@1': 'Staff Workspace',
  'staff.setup.readiness@1': 'Institutional Setup',
  'staff.knowledge.sources@1': 'Knowledge Governance',
  'staff.exams.sessions.index@1': 'Exam Sessions',
  'staff.exams.sessions.show@1': 'Exam Session Detail',
  'applicant.schedule@1': 'Exam Slot Schedule',
};

/**
 * @param {string | null | undefined} packKey
 * @param {string} [fallbackTitle]
 * @returns {string}
 */
export function formatXiamPackTitle(packKey, fallbackTitle = 'Workspace') {
  if (typeof packKey !== 'string' || !packKey) return fallbackTitle;
  if (Object.hasOwn(XIAM_PACK_TITLES, packKey)) return XIAM_PACK_TITLES[packKey];
  if (!packKey.includes('@')) return fallbackTitle;

  return packKey
    .split('@', 1)[0]
    .split(/[._-]+/)
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ') || fallbackTitle;
}
