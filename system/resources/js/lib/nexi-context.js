/**
 * Page-aware Nexi context (presentational).
 * Real retrieval/authz stays server-side under GF-08/21/22 — this only labels the shell.
 *
 * Context families from GF-06 §10:
 * - policy: approved institutional policy citations + link to governing page
 * - own_data: actor-scoped released/authorized data only
 * - staff_task: propose typed UI-equivalent commands with confirm
 * - page_help: orientation without domain mutation
 */

/**
 * @typedef {'policy'|'staff_task'|'page_help'|'own_data'} NexiContextKind
 * @typedef {{
 *   key: string,
 *   title: string,
 *   kind: NexiContextKind,
 *   summary: string,
 *   assistive_hint: string,
 * }} NexiPageContext
 */

/**
 * @param {string} url
 * @returns {NexiPageContext}
 */
export function resolveNexiContext(url = '/') {
  const path = String(url).split('?')[0].split('#')[0] || '/';

  if (path.startsWith('/ui-kit')) {
    return {
      key: 'ui_kit',
      title: 'UI kit gallery',
      kind: 'page_help',
      summary: 'I can orient you to kit patterns and demo gated Apply previews. I will not invent domain commands here.',
      assistive_hint: 'Try filters, bulk bar, then open Nexi for typed Apply chips.',
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
 * @param {NexiContextKind} kind
 */
export function kindLabel(kind) {
  if (kind === 'policy') return 'Policy';
  if (kind === 'own_data') return 'Own data';
  if (kind === 'staff_task') return 'Task assist';
  return 'Page help';
}
