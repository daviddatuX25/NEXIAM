export type RangeValue = number | null;
export type RangePair = [RangeValue, RangeValue];
export type RangeBand = 'honors' | 'regular' | 'cutoff';
export type TabFmPreparation = {
    version: 1;
    common_range: [number, number];
    before: Record<RangeBand, RangePair | null>;
    applied_values: Record<RangeBand, number>;
    reviewed: true;
};
export type CommonRangeConstraints = { min_value?: number | null; max_value?: number | null };
export type CommonRangeProposal = {
    featureKey: string;
    pair: [number, number];
    constraints: CommonRangeConstraints;
    appliedValues: Record<RangeBand, number>;
    before: Record<RangeBand, RangePair | null>;
    rows: Array<{ band: RangeBand; level: string; before: unknown; after: number }>;
};
export type TabFmContent = {
    tiers: Record<string, { values: Record<string, unknown> }>;
    authoring?: {
        version: 1 | 2;
        ranges: Record<RangeBand, Record<string, RangePair>>;
        preferred_strands: string[] | null;
        interests: string[] | null;
        interest_activities?: string[];
        activities_reviewed?: boolean;
        preparation?: Record<string, TabFmPreparation>;
        profile_activity_mapping?: Record<string, { reviewed: true; activity_ids: string[] }>;
    };
};

const levels: Record<RangeBand, string> = { honors: '1', regular: '2', cutoff: '3' };
const bands: RangeBand[] = ['honors', 'regular', 'cutoff'];
function toCents(value: number): number {
    return Math.sign(value) * Math.round(Math.abs(value) * 100 + Number.EPSILON * 1000);
}

function roundedMidpoint(from: number, to: number): number {
    const cents = toCents(from) + toCents(to);
    return Math.sign(cents) * Math.floor(Math.abs(cents) / 2 + 0.5) / 100;
}

type AuthoringInput = { version?: unknown; ranges?: unknown; preferred_strands?: unknown; interests?: unknown; preparation?: unknown; interest_activities?: unknown; activities_reviewed?: unknown; profile_activity_mapping?: unknown };

function normalizeRangeValue(value: unknown): RangeValue {
    if (value === null || value === undefined) return null;
    const numeric = typeof value === 'number' ? value : typeof value === 'string' && value.trim() !== '' ? Number(value) : NaN;
    return Number.isFinite(numeric) ? numeric : null;
}

function cloneAuthoring(source: AuthoringInput): NonNullable<TabFmContent['authoring']> {
    const sourceRanges = source.ranges as Partial<Record<RangeBand, Record<string, unknown>>> | undefined;
    const ranges = Object.fromEntries(bands.map((band) => [
        band,
        Object.fromEntries(Object.entries(sourceRanges?.[band] ?? {}).map(([key, pair]) => [
            key,
            Array.isArray(pair) ? [normalizeRangeValue(pair[0]), normalizeRangeValue(pair[1])] : [null, null],
        ])),
    ])) as Record<RangeBand, Record<string, RangePair>>;
    return {
        version: source.version === 2 ? 2 : 1,
        ranges,
        preferred_strands: Array.isArray(source.preferred_strands) ? [...source.preferred_strands] : null,
        interests: Array.isArray(source.interests) ? [...source.interests] : null,
        ...(Array.isArray(source.interest_activities) ? { interest_activities: [...source.interest_activities] as string[] } : {}),
        ...(typeof source.activities_reviewed === 'boolean' ? { activities_reviewed: source.activities_reviewed } : {}),
        ...(isPreparationMap(source.preparation) ? { preparation: clonePreparationMap(source.preparation) } : {}),
        ...(isProfileActivityMapping(source.profile_activity_mapping) ? { profile_activity_mapping: cloneProfileActivityMapping(source.profile_activity_mapping) } : {}),
    };
}

function isPreparationMap(value: unknown): value is Record<string, TabFmPreparation> {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
    return Object.values(value).every((entry) => {
        if (!entry || typeof entry !== 'object') return false;
        const preparation = entry as Partial<TabFmPreparation>;
        const validPair = (pair: unknown) => Array.isArray(pair) && pair.length === 2
            && pair.every((item) => item === null || typeof item === 'number' && Number.isFinite(item));
        return preparation.version === 1
            && Array.isArray(preparation.common_range)
            && preparation.common_range.length === 2
            && preparation.common_range.every((item) => typeof item === 'number' && Number.isFinite(item))
            && preparation.reviewed === true
            && !!preparation.before
            && bands.every((band) => preparation.before?.[band] === null || validPair(preparation.before?.[band]))
            && !!preparation.applied_values
            && bands.every((band) => typeof preparation.applied_values?.[band] === 'number' && Number.isFinite(preparation.applied_values[band]));
    });
}

function clonePreparationMap(value: Record<string, TabFmPreparation>): Record<string, TabFmPreparation> {
    return Object.fromEntries(Object.entries(value).map(([key, preparation]) => [key, {
        version: 1,
        common_range: [...preparation.common_range] as [number, number],
        before: Object.fromEntries(bands.map((band) => [
            band,
            preparation.before[band] ? [...preparation.before[band]!] as RangePair : null,
        ])) as Record<RangeBand, RangePair | null>,
        applied_values: { ...preparation.applied_values },
        reviewed: true,
    }]));
}
function isProfileActivityMapping(value: unknown): value is Record<string, { reviewed: true; activity_ids: string[] }> {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
    return Object.values(value).every((entry) => {
        if (!entry || typeof entry !== 'object' || !('reviewed' in entry) || !('activity_ids' in entry)) return false;
        const mapping = entry as { reviewed: unknown; activity_ids: unknown };
        return mapping.reviewed === true && Array.isArray(mapping.activity_ids)
            && mapping.activity_ids.every((id) => typeof id === 'string')
            && new Set(mapping.activity_ids).size === mapping.activity_ids.length;
    });
}

function cloneProfileActivityMapping(value: Record<string, { reviewed: true; activity_ids: string[] }>): Record<string, { reviewed: true; activity_ids: string[] }> {
    return Object.fromEntries(Object.entries(value).map(([profile, mapping]) => [profile, {
        reviewed: true,
        activity_ids: [...mapping.activity_ids],
    }]));
}

export function proposeCommonRange(content: TabFmContent, featureKey: string, pair: RangePair, constraints: CommonRangeConstraints = {}): CommonRangeProposal | null {
    const [from, to] = pair;
    if (from === null || to === null || authoredRangeError(pair)) return null;
    if (constraints.min_value != null && (from < constraints.min_value || to < constraints.min_value)) return null;
    if (constraints.max_value != null && (from > constraints.max_value || to > constraints.max_value)) return null;
    const appliedValues = { honors: to, regular: roundedMidpoint(from, to), cutoff: from };
    const before = Object.fromEntries(bands.map((band) => [
        band,
        content.authoring?.ranges?.[band]?.[featureKey] ? [...content.authoring.ranges[band][featureKey]] as RangePair : null,
    ])) as Record<RangeBand, RangePair | null>;
    return {
        featureKey,
        pair: [from, to],
        constraints: { ...constraints },
        appliedValues,
        before,
        rows: bands.map((band) => ({
            band,
            level: levels[band],
            before: content.tiers[levels[band]]?.values?.[featureKey] ?? null,
            after: appliedValues[band],
        })),
    };
}

export function applyCommonRange(content: TabFmContent, proposal: CommonRangeProposal): TabFmContent {
    let next = cloneTabFmContent(content)!;
    for (const band of bands) {
        next = setAuthoredRange(next, band, proposal.featureKey, 0, String(proposal.pair[0]));
        next = setAuthoredRange(next, band, proposal.featureKey, 1, String(proposal.pair[1]));
    }
    const authoring = withAuthoring(next);
    authoring.version = 2;
    authoring.preparation = {
        ...(authoring.preparation ?? {}),
        [proposal.featureKey]: {
            version: 1,
            common_range: [...proposal.pair],
            before: proposal.before,
            applied_values: proposal.appliedValues,
            reviewed: true,
        },
    };
    next.authoring = authoring;
    return next;
}


export function cloneTabFmContent(value: unknown): TabFmContent | null {
    if (!value || typeof value !== 'object' || !('tiers' in value)) return null;
    const source = value as Record<string, unknown>;
    const sourceTiers = (source.tiers ?? {}) as Record<string, { values?: Record<string, unknown> }>;
    const sourceAuthoring = source.authoring as AuthoringInput | undefined;
    const result: TabFmContent = {
        tiers: Object.fromEntries(['1', '2', '3', '4', '5'].map((level) => [level, { values: { ...(sourceTiers[level]?.values ?? {}) } }])),
    };
    if ((sourceAuthoring?.version === 1 || sourceAuthoring?.version === 2) && sourceAuthoring.ranges && typeof sourceAuthoring.ranges === 'object') {
        result.authoring = cloneAuthoring(sourceAuthoring);
    }
    return result;
}

function withAuthoring(content: TabFmContent): NonNullable<TabFmContent['authoring']> {
    return content.authoring ?? {
        version: 1,
        ranges: { honors: {}, regular: {}, cutoff: {} },
        preferred_strands: null,
        interests: null,
    };
}
export function hasIncompleteAuthoredRange(content: TabFmContent | null): boolean {
    return bands.some((band) => (Object.values(content?.authoring?.ranges?.[band] ?? {}) as RangePair[]).some(([from, to]) => (from === null) !== (to === null)));
}

export function setAuthoredRange(content: TabFmContent, band: RangeBand, key: string, endpoint: 0 | 1, raw: string): TabFmContent {
    const next = cloneTabFmContent(content)!;
    const authoring = withAuthoring(next);
    const pair: RangePair = [...(authoring.ranges[band][key] ?? [null, null])];
    const value = raw.trim() === '' ? null : Number(raw);
    pair[endpoint] = value !== null && Number.isFinite(value) ? value : null;
    authoring.ranges[band][key] = pair;
    next.authoring = authoring;
    const [from, to] = pair;
    next.tiers[levels[band]].values[key] = from === null || to === null
        ? null
        : band === 'honors' ? to
            : band === 'regular' ? roundedMidpoint(from, to)
                : from;
    return next;
}

export function setPreference(content: TabFmContent, preference: 'preferred_strands' | 'interests', value: string, checked: boolean): TabFmContent {
    const next = cloneTabFmContent(content)!;
    const authoring = withAuthoring(next);
    const selected = new Set(authoring[preference] ?? []);
    if (checked) selected.add(value);
    else selected.delete(value);
    authoring[preference] = [...selected];
    next.authoring = authoring;
    return next;
}

export function authoredRangeError(pair: RangePair | undefined): string | null {
    if (!pair) return null;
    const [from, to] = pair;
    for (const [label, value] of [['From', from], ['To', to]] as const) {
        if (value === null) continue;
        if (!Number.isFinite(value) || value < -9999.99 || value > 9999.99) return `${label} must be between -9999.99 and 9999.99.`;
        if (Math.abs(value * 100 - Math.round(value * 100)) > 1e-8) return `${label} can have up to 2 decimal places.`;
    }
    if (from !== null && to !== null && from > to) return 'From must not be greater than To.';
    return null;
}
export type BenchmarkStatusProjection = {
    state: string;
    missing_required_count?: number | null;
    partial_range_count?: number | null;
    negative_anchor_missing?: boolean | null;
};

export function benchmarkStatusLabel(projection: BenchmarkStatusProjection): string {
    if (projection.state === 'ready') return 'Selected benchmark content is complete for review.';
    if (projection.state === 'incomplete') {
        const facts = [];
        if (typeof projection.missing_required_count === 'number') facts.push(`${projection.missing_required_count} required values missing`);
        if (typeof projection.partial_range_count === 'number' && projection.partial_range_count > 0) facts.push(`${projection.partial_range_count} partial ranges`);
        if (projection.negative_anchor_missing === true) facts.push('negative anchor needs review');
        return facts.length ? `${facts.join(' · ')}.` : 'Required benchmark values need review.';
    }
    if (projection.state === 'recovery_required') return 'Historical content is available to review.';
    if (projection.state === 'schema_conflict') return 'The benchmark schema changed. Review the current programme before editing.';
    if (projection.state === 'schema_unavailable') return 'The benchmark feature catalogue is unavailable for this programme and academic year.';
    if (projection.state === 'integrity_failure') return 'Benchmark integrity needs administrator review.';
    if (projection.state === 'no_content') return 'No benchmark content is saved yet.';
    return 'Benchmark status is unavailable.';
}
