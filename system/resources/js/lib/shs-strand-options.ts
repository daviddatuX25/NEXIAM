import type { ApplicantIntakeSuggestion } from './applicant-intake-suggestions';

export interface ShsStrandOption {
    key: string;
    code: string;
    label: string;
}

const fallbackOptions = [
    ['STEM', 'STEM — Science, Technology, Engineering & Mathematics'],
    ['ABM', 'ABM — Accountancy, Business & Management'],
    ['HUMSS', 'HUMSS — Humanities & Social Sciences'],
    ['GAS', 'GAS — General Academic Strand'],
    ['TVL-ICT', 'TVL-ICT — Information & Communications Technology'],
    ['TVL-HE', 'TVL-HE — Home Economics'],
    ['TVL-IA', 'TVL-IA — Industrial Arts'],
    ['TVL-AFA', 'TVL-AFA — Agri-Fishery Arts'],
    ['SPORTS', 'SPORTS — Sports Track'],
    ['ARTS', 'ARTS — Arts & Design Track'],
] as const;

export function buildShsStrandOptions(
    suggestions: readonly ApplicantIntakeSuggestion[] | null | undefined,
): ShsStrandOption[] {
    if (!Array.isArray(suggestions) || suggestions.length === 0) {
        return fallbackOptions.map(([code, label]) => ({ key: code, code, label }));
    }

    return suggestions.map((entry) => {
        const alias = Array.isArray(entry.aliases)
            ? entry.aliases.find((value) => value && value.length <= 12 && value.toUpperCase() === value) ?? entry.aliases[0]
            : null;
        const code = (alias || entry.label).trim();
        const label = alias && alias !== entry.label
            ? `${alias} — ${entry.label}`
            : entry.label;

        return { key: entry.key, code, label };
    });
}
