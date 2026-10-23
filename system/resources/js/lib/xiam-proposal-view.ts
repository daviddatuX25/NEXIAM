export const XIAM_PROPOSAL_VIEW_VERSION = 1 as const;
export const XIAM_PROPOSAL_DISPLAY_ZONE = 'Asia/Manila' as const;

export type XiamProposalOperation =
    'create' | 'update' | 'transition' | 'bulk' | 'send';
export type XiamProposalStatus =
    | 'pending'
    | 'executing'
    | 'executed'
    | 'conflicted'
    | 'failed'
    | 'invalidated'
    | 'cancelled'
    | 'expired'
    | 'retired';

export type XiamProposalInstant = {
    type: 'instant';
    value: string;
    precision: 'second';
    display_zone: typeof XIAM_PROPOSAL_DISPLAY_ZONE;
};

export type XiamProposalValue =
    | { type: 'missing' }
    | { type: 'null' }
    | { type: 'text'; value: string; multiline: boolean }
    | { type: 'code'; value: string }
    | {
          type: 'integer';
          value: number;
          semantic: 'count' | 'raw_score' | 'integer';
          unit: string | null;
      }
    | {
          type: 'boolean';
          value: boolean;
          true_label: string;
          false_label: string;
      }
    | { type: 'enum'; code: string; label: string }
    | { type: 'date'; value: string }
    | XiamProposalInstant
    | { type: 'interval'; start: XiamProposalInstant; end: XiamProposalInstant }
    | { type: 'duration'; value: number; unit: 'minute' }
    | { type: 'record'; label: string; context: string[] }
    | { type: 'list'; items: XiamProposalValue[]; empty_label: string };

export type XiamNavigationReference = {
    id: string;
    label: string;
    href: string;
    version: 1;
};

export type XiamProposalReceipt = {
    receipt_id: string;
    outcome_label: string;
    confirmed_at: XiamProposalInstant;
    references: XiamNavigationReference[];
};

export type XiamProposalField = {
    key: string;
    label: string;
    role: 'context' | 'choice' | 'effect';
    change: 'unchanged' | 'set' | 'clear';
    before: XiamProposalValue;
    after: XiamProposalValue;
};

export type XiamProposalPreflightCheck = {
    key: string;
    label: string;
    status: 'pass' | 'fail' | 'warn' | 'noop' | 'unknown';
    detail: string | null;
};

export type XiamProposalPreflight = {
    outcome: 'pass' | 'fail' | 'noop' | 'unknown';
    checks: XiamProposalPreflightCheck[];
};

export type XiamProposalSection =
    | {
          kind: 'fields';
          key: string;
          label: string;
          fields: XiamProposalField[];
      }
    | {
          kind: 'groups';
          key: string;
          label: string;
          groups: XiamProposalGroup[];
      };

export type XiamProposalGroup = {
    key: string;
    label: string;
    context: XiamProposalValue;
    fields: XiamProposalField[];
    preflight: XiamProposalPreflight;
};

export type XiamProposalSafety = {
    tier: 't1' | 't2' | 't3';
    confirmation_phrase: string | null;
    requires_reauth: boolean;
    expires_at: string;
};

export type XiamProposalPublicError = {
    code: string;
    category:
        | 'denied'
        | 'invalid'
        | 'stale'
        | 'conflict'
        | 'expired'
        | 'unavailable'
        | 'failed'
        | 'unsupported'
        | 'retired';
    message: string;
    remediation:
        | 'repropose'
        | 'reauthenticate'
        | 'refresh'
        | 'manual'
        | 'reconcile'
        | 'none';
};

export type XiamProposalViewV1 = {
    version: typeof XIAM_PROPOSAL_VIEW_VERSION;
    proposal_id: string;
    run_id: string;
    capability_id: string;
    operation: XiamProposalOperation;
    title: string;
    sections: XiamProposalSection[];
    preflight: XiamProposalPreflight;
    safety: XiamProposalSafety;
    status: XiamProposalStatus;
    confirmable: boolean;
    error: XiamProposalPublicError | null;
    receipt: XiamProposalReceipt | null;
};

const OBJECT = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);
const STRING = (value: unknown): value is string => typeof value === 'string';
const NON_EMPTY_STRING = (value: unknown): value is string =>
    STRING(value) && value.trim().length > 0;
const BOOLEAN = (value: unknown): value is boolean =>
    typeof value === 'boolean';
const hasOnlyKeys = (
    value: Record<string, unknown>,
    keys: readonly string[],
): boolean => Object.keys(value).every((key) => keys.includes(key));
const hasKeys = (
    value: Record<string, unknown>,
    keys: readonly string[],
): boolean => keys.every((key) => Object.hasOwn(value, key));
const enumIs = <T extends string>(
    value: unknown,
    values: readonly T[],
): value is T => typeof value === 'string' && values.includes(value as T);
const isStringArray = (value: unknown): value is string[] =>
    Array.isArray(value) && value.every(STRING);

const ISO_INSTANT =
    /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:Z|[+-](\d{2}):(\d{2}))$/;
const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

function isValidCalendarDate(
    year: number,
    month: number,
    day: number,
): boolean {
    if (year < 1) return false;
    const days = [
        31,
        year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0) ? 29 : 28,
        31,
        30,
        31,
        30,
        31,
        31,
        30,
        31,
        30,
        31,
    ];
    return month >= 1 && month <= 12 && day >= 1 && day <= days[month - 1];
}

function isValidInstantString(value: unknown): value is string {
    if (!STRING(value)) return false;
    const match = ISO_INSTANT.exec(value);
    if (!match) return false;
    const [, year, month, day, hour, minute, second, offsetHour, offsetMinute] =
        match;
    if (
        !isValidCalendarDate(Number(year), Number(month), Number(day)) ||
        Number(hour) > 23 ||
        Number(minute) > 59 ||
        Number(second) > 59 ||
        Number(offsetHour ?? 0) > 23 ||
        Number(offsetMinute ?? 0) > 59
    )
        return false;
    return Number.isFinite(new Date(value).getTime());
}

function isInstant(value: unknown): value is XiamProposalInstant {
    if (
        !OBJECT(value) ||
        !hasOnlyKeys(value, ['type', 'value', 'precision', 'display_zone']) ||
        !hasKeys(value, ['type', 'value', 'precision', 'display_zone'])
    )
        return false;
    return (
        value.type === 'instant' &&
        value.precision === 'second' &&
        value.display_zone === XIAM_PROPOSAL_DISPLAY_ZONE &&
        isValidInstantString(value.value)
    );
}

function isValue(value: unknown, depth = 0): value is XiamProposalValue {
    if (!OBJECT(value) || depth > 12 || !STRING(value.type)) return false;
    switch (value.type) {
        case 'missing':
        case 'null':
            return hasOnlyKeys(value, ['type']) && hasKeys(value, ['type']);
        case 'text':
            return (
                hasOnlyKeys(value, ['type', 'value', 'multiline']) &&
                hasKeys(value, ['type', 'value', 'multiline']) &&
                STRING(value.value) &&
                BOOLEAN(value.multiline)
            );
        case 'code':
            return (
                hasOnlyKeys(value, ['type', 'value']) &&
                hasKeys(value, ['type', 'value']) &&
                STRING(value.value)
            );
        case 'integer':
            return (
                hasOnlyKeys(value, ['type', 'value', 'semantic', 'unit']) &&
                hasKeys(value, ['type', 'value', 'semantic', 'unit']) &&
                Number.isSafeInteger(value.value) &&
                enumIs(value.semantic, ['count', 'integer', 'raw_score']) &&
                (value.unit === null || STRING(value.unit))
            );
        case 'boolean':
            return (
                hasOnlyKeys(value, [
                    'type',
                    'value',
                    'true_label',
                    'false_label',
                ]) &&
                hasKeys(value, [
                    'type',
                    'value',
                    'true_label',
                    'false_label',
                ]) &&
                BOOLEAN(value.value) &&
                STRING(value.true_label) &&
                STRING(value.false_label)
            );
        case 'enum':
            return (
                hasOnlyKeys(value, ['type', 'code', 'label']) &&
                hasKeys(value, ['type', 'code', 'label']) &&
                NON_EMPTY_STRING(value.code) &&
                NON_EMPTY_STRING(value.label)
            );
        case 'date':
            if (
                !hasOnlyKeys(value, ['type', 'value']) ||
                !hasKeys(value, ['type', 'value']) ||
                !STRING(value.value)
            )
                return false;
            const dateMatch = ISO_DATE.exec(value.value);
            return (
                dateMatch !== null &&
                isValidCalendarDate(
                    Number(dateMatch[1]),
                    Number(dateMatch[2]),
                    Number(dateMatch[3]),
                )
            );
        case 'instant':
            return isInstant(value);
        case 'interval':
            return (
                hasOnlyKeys(value, ['type', 'start', 'end']) &&
                hasKeys(value, ['type', 'start', 'end']) &&
                isInstant(value.start) &&
                isInstant(value.end) &&
                new Date(value.start.value).getTime() <=
                    new Date(value.end.value).getTime()
            );
        case 'duration':
            return (
                hasOnlyKeys(value, ['type', 'value', 'unit']) &&
                hasKeys(value, ['type', 'value', 'unit']) &&
                Number.isSafeInteger(value.value) &&
                Number(value.value) >= 0 &&
                value.unit === 'minute'
            );
        case 'record':
            return (
                hasOnlyKeys(value, ['type', 'label', 'context']) &&
                hasKeys(value, ['type', 'label', 'context']) &&
                NON_EMPTY_STRING(value.label) &&
                isStringArray(value.context)
            );
        case 'list':
            return (
                hasOnlyKeys(value, ['type', 'items', 'empty_label']) &&
                hasKeys(value, ['type', 'items', 'empty_label']) &&
                NON_EMPTY_STRING(value.empty_label) &&
                Array.isArray(value.items) &&
                value.items.every((item) => isValue(item, depth + 1))
            );
        default:
            return false;
    }
}

function isField(value: unknown): value is XiamProposalField {
    return (
        OBJECT(value) &&
        hasOnlyKeys(value, [
            'key',
            'label',
            'role',
            'change',
            'before',
            'after',
        ]) &&
        hasKeys(value, ['key', 'label', 'role', 'change', 'before', 'after']) &&
        NON_EMPTY_STRING(value.key) &&
        NON_EMPTY_STRING(value.label) &&
        enumIs(value.role, ['context', 'choice', 'effect']) &&
        enumIs(value.change, ['unchanged', 'set', 'clear']) &&
        isValue(value.before) &&
        isValue(value.after)
    );
}

function isPreflight(value: unknown): value is XiamProposalPreflight {
    return (
        OBJECT(value) &&
        hasOnlyKeys(value, ['outcome', 'checks']) &&
        hasKeys(value, ['outcome', 'checks']) &&
        enumIs(value.outcome, ['pass', 'fail', 'noop', 'unknown']) &&
        Array.isArray(value.checks) &&
        value.checks.every(
            (check) =>
                OBJECT(check) &&
                hasOnlyKeys(check, ['key', 'label', 'status', 'detail']) &&
                hasKeys(check, ['key', 'label', 'status', 'detail']) &&
                NON_EMPTY_STRING(check.key) &&
                NON_EMPTY_STRING(check.label) &&
                enumIs(check.status, [
                    'pass',
                    'fail',
                    'warn',
                    'noop',
                    'unknown',
                ]) &&
                (check.detail === null || STRING(check.detail)),
        )
    );
}

function isSection(value: unknown): value is XiamProposalSection {
    if (
        !OBJECT(value) ||
        !STRING(value.kind) ||
        !hasOnlyKeys(
            value,
            value.kind === 'fields'
                ? ['kind', 'key', 'label', 'fields']
                : ['kind', 'key', 'label', 'groups'],
        ) ||
        !hasKeys(value, ['kind', 'key', 'label']) ||
        !NON_EMPTY_STRING(value.key) ||
        !NON_EMPTY_STRING(value.label)
    )
        return false;
    if (value.kind === 'fields')
        return Array.isArray(value.fields) && value.fields.every(isField);
    if (
        value.kind !== 'groups' ||
        !hasKeys(value, ['groups']) ||
        !Array.isArray(value.groups)
    )
        return false;
    return value.groups.every(
        (group) =>
            OBJECT(group) &&
            hasOnlyKeys(group, [
                'key',
                'label',
                'context',
                'fields',
                'preflight',
            ]) &&
            hasKeys(group, [
                'key',
                'label',
                'context',
                'fields',
                'preflight',
            ]) &&
            NON_EMPTY_STRING(group.key) &&
            NON_EMPTY_STRING(group.label) &&
            isValue(group.context) &&
            Array.isArray(group.fields) &&
            group.fields.every(isField) &&
            isPreflight(group.preflight),
    );
}
function hasCompleteReview(sections: XiamProposalSection[]): boolean {
    return (
        sections.length > 0 &&
        sections.every((section) =>
            section.kind === 'fields'
                ? section.fields.length > 0
                : section.groups.length > 0 &&
                  section.groups.every(
                      (group) =>
                          group.fields.length > 0 &&
                          group.preflight.outcome === 'pass' &&
                          group.preflight.checks.every(
                              (check) =>
                                  check.status !== 'fail' &&
                                  check.status !== 'unknown',
                          ),
                  ),
        )
    );
}

function isSafety(value: unknown): value is XiamProposalSafety {
    return (
        OBJECT(value) &&
        hasOnlyKeys(value, [
            'tier',
            'confirmation_phrase',
            'requires_reauth',
            'expires_at',
        ]) &&
        hasKeys(value, [
            'tier',
            'confirmation_phrase',
            'requires_reauth',
            'expires_at',
        ]) &&
        enumIs(value.tier, ['t1', 't2', 't3']) &&
        (value.confirmation_phrase === null ||
            NON_EMPTY_STRING(value.confirmation_phrase)) &&
        BOOLEAN(value.requires_reauth) &&
        isValidInstantString(value.expires_at)
    );
}

function isPublicError(value: unknown): value is XiamProposalPublicError {
    return (
        OBJECT(value) &&
        hasOnlyKeys(value, ['code', 'category', 'message', 'remediation']) &&
        hasKeys(value, ['code', 'category', 'message', 'remediation']) &&
        NON_EMPTY_STRING(value.code) &&
        enumIs(value.category, [
            'denied',
            'invalid',
            'stale',
            'conflict',
            'expired',
            'unavailable',
            'failed',
            'unsupported',
            'retired',
        ]) &&
        NON_EMPTY_STRING(value.message) &&
        enumIs(value.remediation, [
            'repropose',
            'reauthenticate',
            'refresh',
            'manual',
            'reconcile',
            'none',
        ])
    );
}
function isNavigationReference(
    value: unknown,
): value is XiamNavigationReference {
    if (
        !OBJECT(value) ||
        !hasOnlyKeys(value, ['id', 'label', 'href', 'version']) ||
        !hasKeys(value, ['id', 'label', 'href', 'version']) ||
        !NON_EMPTY_STRING(value.id) ||
        !NON_EMPTY_STRING(value.label) ||
        value.version !== 1 ||
        !STRING(value.href) ||
        !value.href.startsWith('/') ||
        value.href.startsWith('//') ||
        value.href.includes('\\') ||
        /[\u0000-\u001f\u007f]/.test(value.href)
    ) {
        return false;
    }
    try {
        return (
            new URL(value.href, 'https://xiam.invalid').origin ===
            'https://xiam.invalid'
        );
    } catch {
        return false;
    }
}

function isReceipt(value: unknown): value is XiamProposalReceipt {
    if (
        !OBJECT(value) ||
        !hasOnlyKeys(value, [
            'receipt_id',
            'outcome_label',
            'confirmed_at',
            'references',
        ]) ||
        !hasKeys(value, [
            'receipt_id',
            'outcome_label',
            'confirmed_at',
            'references',
        ]) ||
        !NON_EMPTY_STRING(value.receipt_id) ||
        !NON_EMPTY_STRING(value.outcome_label) ||
        !isInstant(value.confirmed_at) ||
        !Array.isArray(value.references) ||
        !value.references.every(isNavigationReference) ||
        value.references.length > 12
    ) {
        return false;
    }

    const references = value.references;

    return references.every(
        (reference, index) =>
            references.findIndex(
                (candidate) => candidate.id === reference.id,
            ) === index,
    );
}

export function decodeXiamProposalView(
    value: unknown,
): XiamProposalViewV1 | null {
    if (
        !OBJECT(value) ||
        !hasOnlyKeys(value, [
            'version',
            'proposal_id',
            'run_id',
            'capability_id',
            'operation',
            'title',
            'sections',
            'preflight',
            'safety',
            'status',
            'confirmable',
            'error',
            'receipt',
        ]) ||
        !hasKeys(value, [
            'version',
            'proposal_id',
            'run_id',
            'capability_id',
            'operation',
            'title',
            'sections',
            'preflight',
            'safety',
            'status',
            'confirmable',
            'error',
            'receipt',
        ])
    )
        return null;
    if (
        value.version !== XIAM_PROPOSAL_VIEW_VERSION ||
        !NON_EMPTY_STRING(value.proposal_id) ||
        !NON_EMPTY_STRING(value.run_id) ||
        !NON_EMPTY_STRING(value.capability_id) ||
        !enumIs(value.operation, [
            'create',
            'update',
            'transition',
            'bulk',
            'send',
        ]) ||
        !NON_EMPTY_STRING(value.title) ||
        !Array.isArray(value.sections) ||
        !value.sections.every(isSection) ||
        !isPreflight(value.preflight) ||
        !isSafety(value.safety) ||
        !enumIs(value.status, [
            'pending',
            'executing',
            'executed',
            'conflicted',
            'failed',
            'invalidated',
            'cancelled',
            'expired',
            'retired',
        ]) ||
        !BOOLEAN(value.confirmable) ||
        !(value.error === null || isPublicError(value.error)) ||
        !(value.receipt === null || isReceipt(value.receipt))
    )
        return null;
    if (
        value.confirmable &&
        (value.status !== 'pending' ||
            value.preflight.outcome !== 'pass' ||
            value.preflight.checks.some(
                (check) =>
                    check.status === 'fail' || check.status === 'unknown',
            ) ||
            value.error !== null ||
            !hasCompleteReview(value.sections as XiamProposalSection[]))
    )
        return null;
    return value as XiamProposalViewV1;
}
export function canConfirmXiamProposal(
    view: XiamProposalViewV1,
    now: number = Date.now(),
): boolean {
    return (
        view.confirmable &&
        view.status === 'pending' &&
        view.preflight.outcome === 'pass' &&
        view.preflight.checks.every(
            (check) => check.status !== 'fail' && check.status !== 'unknown',
        ) &&
        view.error === null &&
        new Date(view.safety.expires_at).getTime() > now
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}

export function formatXiamProposalInstant(value: XiamProposalInstant): string {
    const date = new Date(value.value);
    const hasSeconds = date.getUTCSeconds() !== 0;
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: XIAM_PROPOSAL_DISPLAY_ZONE,
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        ...(hasSeconds ? { second: '2-digit' } : {}),
        hour12: true,
    }).format(date);
    return `${parts} (Asia/Manila, UTC+08:00)`;
}

export function formatXiamProposalValue(value: XiamProposalValue): string {
    switch (value.type) {
        case 'missing':
            return 'Not provided';
        case 'null':
            return 'None';
        case 'text':
            return value.value.length === 0 ? 'Empty' : value.value;
        case 'code':
            return value.value.length === 0 ? 'Empty' : value.value;
        case 'integer': {
            const formatted =
                value.semantic === 'count'
                    ? new Intl.NumberFormat('en-US').format(value.value)
                    : String(value.value);
            return value.unit ? `${formatted} ${value.unit}` : formatted;
        }
        case 'boolean':
            return value.value ? value.true_label : value.false_label;
        case 'enum':
            return value.label;
        case 'date':
            return formatDate(value.value);
        case 'instant':
            return formatXiamProposalInstant(value);
        case 'interval':
            return `${formatXiamProposalInstant(value.start)} – ${formatXiamProposalInstant(value.end)}`;
        case 'duration': {
            const hours = Math.floor(value.value / 60);
            const minutes = value.value % 60;
            if (hours === 0)
                return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`;
            if (minutes === 0)
                return `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
            return `${hours} ${hours === 1 ? 'hour' : 'hours'} ${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`;
        }
        case 'record':
            return value.context.length
                ? `${value.label} (${value.context.join(', ')})`
                : value.label;
        case 'list':
            return value.items.length
                ? value.items.map(formatXiamProposalValue).join('\n')
                : value.empty_label;
    }
}

export function formatXiamProposalFieldChange(
    field: XiamProposalField,
): string {
    if (field.change === 'unchanged') return 'No change';
    if (field.change === 'clear')
        return `Cleared (was ${formatXiamProposalValue(field.before)})`;
    return `${formatXiamProposalValue(field.before)} → ${formatXiamProposalValue(field.after)}`;
}
