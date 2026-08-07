/**
 * Password strength scoring and checklist logic.
 * Mirrors backend rules: min(8), letters(), mixedCase(), numbers()
 * Per PortalSetupRequest.php
 */

const MIN_LENGTH = 8;
const STRONG_LENGTH = 12;

/**
 * @param {string} password
 * @returns {{ minLength: boolean, hasUppercase: boolean, hasLowercase: boolean, hasNumber: boolean }}
 */
export function getRequirements(password) {
  const p = password ?? '';
  return {
    minLength: p.length >= MIN_LENGTH,
    hasUppercase: /[A-Z]/.test(p),
    hasLowercase: /[a-z]/.test(p),
    hasNumber: /\d/.test(p),
  };
}

/**
 * Check if all requirements for backend validation are met.
 * @param {string} password
 * @returns {boolean}
 */
export function allRequirementsMet(password) {
  const r = getRequirements(password);
  return r.minLength && r.hasUppercase && r.hasLowercase && r.hasNumber;
}

/**
 * Strength score 0–4: very weak, weak, fair, strong, excellent
 * @param {string} password
 * @returns {number}
 */
export function getStrengthScore(password) {
  const p = password ?? '';
  if (p.length === 0) return 0;

  let score = 0;

  // Length: 8+ = 1, 10+ = 2, 12+ = 3
  if (p.length >= MIN_LENGTH) score += 1;
  if (p.length >= 10) score += 1;
  if (p.length >= STRONG_LENGTH) score += 1;

  // Character variety: each type adds 1 (max 3 from variety)
  const variety = [
    /[A-Z]/.test(p),
    /[a-z]/.test(p),
    /\d/.test(p),
    /[^A-Za-z0-9]/.test(p),
  ].filter(Boolean).length;
  score += Math.min(variety, 2);

  return Math.min(score, 4);
}

/**
 * @param {number} score 0–4
 * @returns {'very-weak'|'weak'|'fair'|'strong'|'excellent'}
 */
export function getStrengthLabel(score) {
  switch (score) {
    case 0:
      return 'very-weak';
    case 1:
      return 'weak';
    case 2:
      return 'fair';
    case 3:
      return 'strong';
    case 4:
    default:
      return 'excellent';
  }
}

/**
 * Check if password and confirmation match.
 * @param {string} password
 * @param {string} confirmation
 * @returns {boolean}
 */
export function passwordsMatch(password, confirmation) {
  if (!confirmation || confirmation.length === 0) return false;
  return password === confirmation;
}
