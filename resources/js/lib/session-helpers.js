/**
 * Shared helpers for session time-window and status logic.
 * Used by MySessions, TestAdmin/Index, and SessionRoster.
 */

/**
 * Whether a session has at least one applicant.
 * @param {object} session - Session object with applicants_count (list views) or stats.total (roster view)
 * @param {object} [stats] - Optional stats object (roster view uses stats.total instead of applicants_count)
 */
export function hasApplicants(session, stats) {
  if (stats) return (stats.total ?? 0) > 0;
  return (session.applicants_count ?? 0) > 0;
}

/**
 * Can the session be started right now?
 * Works for both list-view (policy-based can_start) and proctor view (role-based can_override_schedule).
 */
export function canStartSession(session, stats) {
  if (!hasApplicants(session, stats)) return false;
  if (session.status !== 'published') return false;
  return session.is_within_start_window || session.can_override_schedule;
}

/**
 * Classify the time-window state for display purposes.
 * Returns: 'no-applicants' | 'past-date' | 'within-window' | 'outside-window'
 */
export function timeWindowLabel(session, stats) {
  if (!hasApplicants(session, stats) && session.status === 'published') return 'no-applicants';
  if (session.is_past_date) return 'past-date';
  if (session.is_within_start_window) return 'within-window';
  return 'outside-window';
}

/**
 * Whether the session is outside the start window AND the user cannot override.
 */
export function isOutsideStartWindow(session, stats) {
  return session.status === 'published'
    && hasApplicants(session, stats)
    && !session.is_within_start_window
    && !session.can_override_schedule;
}

/**
 * Whether the session is outside the start window AND the user can override.
 */
export function canOverrideStartWindow(session, stats) {
  return session.status === 'published'
    && hasApplicants(session, stats)
    && !session.is_within_start_window
    && session.can_override_schedule;
}