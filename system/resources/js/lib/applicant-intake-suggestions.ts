export interface ApplicantIntakeSuggestion {
    key: string;
    label: string;
    aliases: string[];
}

const ignoredInitialWords: Record<string, true> = {
    and: true,
    for: true,
    in: true,
    of: true,
    the: true,
    inc: true,
    incorporated: true,
    corp: true,
    corporation: true,
};

const compoundSchoolInitials: Record<string, true> = {
    es: true,
    ces: true,
    ps: true,
    hs: true,
    jhs: true,
    shs: true,
    nhs: true,
    nchs: true,
    nshs: true,
    ihs: true,
    is: true,
};

export function filterApplicantIntakeSuggestions(
    suggestions: readonly ApplicantIntakeSuggestion[],
    query: string,
    limit = 8,
): ApplicantIntakeSuggestion[] {
    const normalizedQuery = normalizeSuggestionText(query);
    const compactQuery = normalizedQuery.replace(/[\p{P}\s]/gu, '');
    const initialQuery = /^[a-z0-9]{2,16}$/.test(compactQuery)
        ? compactQuery
        : null;
    const maximumResults = Math.max(0, Math.trunc(limit));

    if (maximumResults === 0) {
        return [];
    }

    const matches: { suggestion: ApplicantIntakeSuggestion; score: number }[] =
        [];

    for (const suggestion of suggestions) {
        const score = matchScore(suggestion, normalizedQuery, initialQuery);

        if (score !== null) {
            matches.push({ suggestion, score });
        }
    }

    matches.sort((left, right) => {
        if (left.score !== right.score) {
            return left.score - right.score;
        }

        return left.suggestion.label.localeCompare(right.suggestion.label);
    });
    matches.length = Math.min(matches.length, maximumResults);

    return matches.map((result) => result.suggestion);
}

function matchScore(
    suggestion: ApplicantIntakeSuggestion,
    normalizedQuery: string,
    initialQuery: string | null,
): number | null {
    if (normalizedQuery === '') {
        return 0;
    }

    let bestScore = scoreSearchTerm(suggestion.label, normalizedQuery);

    for (const alias of suggestion.aliases) {
        const score = scoreSearchTerm(alias, normalizedQuery);

        if (score !== null && (bestScore === null || score < bestScore)) {
            bestScore = score;
        }

        if (bestScore === 0) {
            return bestScore;
        }
    }

    return (
        bestScore ??
        (initialQuery === null
            ? null
            : scoreInitials(suggestion.label, initialQuery))
    );
}

function scoreSearchTerm(
    value: string,
    normalizedQuery: string,
): number | null {
    const normalizedValue = normalizeSuggestionText(value);

    if (normalizedValue === normalizedQuery) {
        return 0;
    }

    if (normalizedValue.startsWith(normalizedQuery)) {
        return 1;
    }

    if (normalizedValue.includes(` ${normalizedQuery}`)) {
        return 2;
    }

    if (normalizedValue.includes(normalizedQuery)) {
        return 3;
    }

    return null;
}

function scoreInitials(value: string, query: string): number | null {
    const words =
        normalizeSuggestionText(
            value.replace(/[’']s\b/gi, '').replace(/\([^)]*\)/g, ' '),
        ).match(/[a-z0-9]+/g) ?? [];
    let initials = '';
    let expandedInitials = '';
    for (const word of words) {
        if (ignoredInitialWords[word] === true) {
            continue;
        }
        initials += word[0];
        expandedInitials +=
            compoundSchoolInitials[word] === true ? word : word[0];
    }
    if (initials === query || expandedInitials === query) {
        return 4;
    }
    if (initials.startsWith(query) || expandedInitials.startsWith(query)) {
        return 5;
    }
    return null;
}

function normalizeSuggestionText(value: string): string {
    return value
        .normalize('NFKD')
        .replace(/\p{M}/gu, '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, ' ');
}
