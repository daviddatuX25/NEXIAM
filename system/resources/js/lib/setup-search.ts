/**
 * Setup Hub dynamic search and inner words/terms matching engine.
 *
 * Provides client-side, tokenized multi-term AND matching
 * across setup module cards, checks, descriptors, and domain aliases.
 */

export interface SetupCheck {
    label?: string | null;
    detail?: string | null;
    passed?: boolean;
    severity?: string | null;
    action_href?: string | null;
    action_label?: string | null;
}

export interface SetupCard {
    key: string;
    label?: string | null;
    description?: string | null;
    summary_metric?: string | null;
    icon?: string | null;
    href?: string | null;
    desk?: string | null;
    capability?: string | null;
    status?: string | null;
    in_navigation?: boolean;
    navigation_context?: string | null;
    checks?: SetupCheck[] | null;
    [key: string]: unknown;
}

export interface SetupStage {
    stage: number;
    title: string;
    cards?: SetupCard[];
    [key: string]: unknown;
}

export const DESK_NAMES: Record<string, string> = {
    all: 'All Desks',
    admin: 'Admin Desk',
    registrar: 'Registrar Desk',
    testing: 'Testing Unit',
    guidance: 'Guidance & Deans',
    system: 'System Ops',
};

export const DOMAIN_ALIASES: Record<string, string[]> = {
    academic_cycles: [
        'academic year',
        'calendar',
        'semester',
        'cycles',
        'terms',
        'sy',
        'admission windows',
        'intake windows',
    ],
    cycles: [
        'academic year',
        'calendar',
        'semester',
        'cycles',
        'terms',
        'sy',
        'admission windows',
        'intake windows',
    ],
    programs: [
        'degree programs',
        'curriculum',
        'courses',
        'majors',
        'departments',
        'strands',
        'colleges',
    ],
    departments: ['academic departments', 'units', 'divisions', 'colleges'],
    program_public_profiles: [
        'public details',
        'course catalog',
        'landing page',
        'program profiles',
        'brochure',
    ],
    requirements: [
        'intake requirements',
        'checklist',
        'documents',
        'tor',
        'birth certificate',
        'form 137',
        'clearance',
    ],
    intake_fields: [
        'application form fields',
        'intake policy',
        'custom questions',
        'form fields',
        'field policy',
    ],
    privacy: [
        'privacy policy',
        'consent',
        'data privacy',
        'dpa',
        'terms of service',
    ],
    admissions_notifications: [
        'admissions notices',
        'email templates',
        'sms notices',
        'intake notifications',
    ],
    rooms: [
        'venues',
        'campus',
        'facilities',
        'buildings',
        'classrooms',
        'door',
        'gps',
        'latitude',
        'longitude',
        'wayfinding',
        'directions',
        'location',
    ],
    seating_arrangements: [
        'seating',
        'seat layout',
        'room arrangements',
        'desk grid',
        'seat plan',
    ],
    examination_notifications: [
        'exam notices',
        'admit card',
        'testing reminders',
        'exam schedule notices',
    ],
    exam_materials: [
        'test kits',
        'test setup',
        'assessment profiles',
        'materials bundle',
        'master keys',
        'test instruments',
        'subtests',
    ],
    answer_sheets: [
        'omr',
        'optical mark recognition',
        'answer sheets',
        'bubbles',
        'paper layout',
        'studio',
        'calibration',
        'scanner',
        'scan',
    ],
    booklets: [
        'question booklets',
        'exam paper',
        'test questionnaire',
        'booklet keys',
        'subtests',
    ],
    scanner_diagnostics: [
        'omr scanner',
        'camera diagnostics',
        'capture verification',
        'diagnostic run',
    ],
    test_instruments: ['subtests', 'raw metrics', 'instruments', 'battery'],
    aptitude: [
        'aptitude areas',
        'scoring formula',
        'percentile conversion',
        'weights',
        'composite',
    ],
    norm_packages: [
        'norms',
        'norm tables',
        'percentile norms',
        'stanine conversion',
    ],
    result_templates: [
        'result sheet',
        'report card',
        'certificate',
        'templates',
        'score report',
    ],
    admission_support: [
        'tabular',
        'tabfm',
        'admission engine',
        'neural matcher',
        'benchmarks',
        'course match',
    ],
    quotas: [
        'program quotas',
        'capacity',
        'intake slots',
        'allocation',
        'target',
    ],
    consultation_policy: [
        'consultation schedule',
        'counseling policy',
        'advising windows',
        'dean review',
    ],
    results_notifications: [
        'result release notices',
        'score notification',
        'passer notices',
    ],
    ai_center: [
        'xiam',
        'ai governance',
        'assistant',
        'tokens',
        'budget',
        'emergency stop',
        'models',
        'copilot',
    ],
};

export function normalizeText(text: unknown): string {
    return String(text ?? '')
        .normalize('NFC')
        .toLowerCase()
        .trim();
}

/**
 * Tokenize a search query into normalized, punctuation-stripped search terms.
 * Preserves inner word terms and drops empty tokens.
 */
export function tokenizeQuery(query: string | null | undefined): string[] {
    const raw = normalizeText(query);
    if (!raw) return [];

    const parts = raw.split(/\s+/);
    const seen = new Set<string>();
    const tokens: string[] = [];

    for (const part of parts) {
        // Strip leading/trailing surrounding punctuation while preserving internal chars
        const cleaned = part.replace(
            /^[\s,.;:!?'"()[\]{}<>]+|[\s,.;:!?'"()[\]{}<>]+$/g,
            '',
        );
        if (cleaned && !seen.has(cleaned)) {
            seen.add(cleaned);
            tokens.push(cleaned);
        }
    }

    return tokens;
}

/**
 * Construct the unified searchable corpus for a card.
 */
export function buildCardCorpus(
    card: SetupCard,
    stageTitle = '',
    accessLabel = '',
): string {
    const deskName = card.desk ? (DESK_NAMES[card.desk] ?? card.desk) : '';
    const parts: string[] = [
        card.label ?? '',
        card.description ?? '',
        card.summary_metric ?? '',
        card.key ?? '',
        card.desk ?? '',
        deskName,
        card.capability ?? '',
        String(card.capability ?? '').replace(/[._-]/g, ' '),
        accessLabel,
        stageTitle,
        card.navigation_context ?? '',
    ];

    if (Array.isArray(card.checks)) {
        for (const check of card.checks) {
            if (check.label) parts.push(check.label);
            if (check.detail) parts.push(check.detail);
            if (check.action_label) parts.push(check.action_label);
        }
    }

    const aliases = DOMAIN_ALIASES[card.key];
    if (aliases) {
        parts.push(...aliases);
    }

    return normalizeText(parts.join(' '));
}

/**
 * Evaluates whether a card corpus satisfies all query tokens.
 * Inner words/terms matching: every token must be a substring of the card corpus.
 */
export function cardMatchesTokens(corpus: string, tokens: string[]): boolean {
    if (tokens.length === 0) return true;
    for (const token of tokens) {
        if (!corpus.includes(token)) {
            return false;
        }
    }
    return true;
}
