export type SubtestResponseMode = 'keyed_items' | 'recorded_measure';

export interface SubtestItem {
    id?: number | string;
    item_code: string;
    local_number: string;
    display_order: number;
    is_scored: boolean;
    option_tokens: string[];
}

export interface AnswerSheetConnection {
    sheet_title: string;
    version_number: number;
    pages_summary: string;
    mapped_items_count: number;
    total_items_count: number;
    status: 'mapped' | 'partial' | 'unmapped' | 'manual';
}

export interface SubtestSection {
    id: string;
    code: string;
    name: string;
    display_order: number;
    response_mode: SubtestResponseMode;
    items: SubtestItem[];
    min_raw_value?: string;
    max_raw_value?: string;
    connection?: AnswerSheetConnection;
}

export interface AptitudeAreaItem {
    id: string;
    code: string;
    name: string;
    type: 'direct' | 'composite';
    subtest_code?: string;
    input_codes: string[];
    construct_aptitude_area_id?: number | null;
    max_raw_value: number;
    formula_expression: string;
    status: 'configured' | 'needs_table' | 'needs_formula' | 'invalid';
}

export interface AptitudeAreaEditingSession<TMetric = unknown> {
    area: AptitudeAreaItem;
    metric: TMetric;
    intervals: NormConversionInterval[];
    activeMode: 'editor' | 'simulator' | 'csv';
}

export interface AptitudeFormula {
    id: string;
    code: string;
    name: string;
    type: 'direct' | 'composite';
    subtest_code?: string;
    input_codes: string[];
    construct_aptitude_area_id?: number | null;
    rule_kind: string;
    unit: string;
    precision_scale: number;
    min_raw_value: string;
    max_raw_value: string;
    is_required: boolean;
}

export interface NormConversionInterval {
    min_score: number | string;
    max_score: number | string;
    output_value: number | string;
}

export interface NormTableDraft {
    metric_code: string;
    output_scale: string;
    intervals: NormConversionInterval[];
}

export interface NormPackageDraft {
    id?: number;
    version_id?: number;
    code: string;
    display_name: string;
    tables: NormTableDraft[];
}
