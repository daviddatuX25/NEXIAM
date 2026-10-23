<script>
    import {
        inlineTypographyStyle,
        normalizeComponentProps,
        renderComponentTemplate,
        sampleValue,
    } from '@/lib/resultSheetStyles';

    let {
        component = {},
        componentTemplate = '',
        sampleContext = {},
        noticeDocumentImagePreview = false,
        noticeDocumentImageAssets = [],
    } = $props();
    const safeNoticeImageSrc = $derived.by(() => {
        if (typeof window === 'undefined' || !noticeDocumentImagePreview) return null;
        const verified = noticeDocumentImageAssets.find((asset) => asset.asset_id === component.props?.asset_id);
        if (!verified || typeof verified.src !== 'string') return null;
        try {
            const candidate = new URL(verified.src, window.location.origin);
            return candidate.origin === window.location.origin && !candidate.username && !candidate.password && ['http:', 'https:'].includes(candidate.protocol) ? candidate.href : null;
        } catch {
            return null;
        }
    });

    const props = $derived(
        normalizeComponentProps(component.type, component.props ?? {}),
    );
    const typography = $derived(props.typography);
    const textStyles = $derived(props.text_styles ?? {});
    const fallbackScoreRows = [
        { domain: 'Verbal', raw: '82', converted: 'Good' },
        { domain: 'Quantitative', raw: '91', converted: 'Excellent' },
        { domain: 'Spatial', raw: '76', converted: 'Good' },
    ];
    const scoreRows = $derived(
        Array.isArray(sampleContext?.scores_rows) &&
            sampleContext.scores_rows.length > 0
            ? sampleContext.scores_rows
            : fallbackScoreRows,
    );
    const defaultKpiMetrics = [
        { label: 'Total Examinees', binding: 'total_examinees' },
        { label: 'Total Passed', binding: 'total_passed' },
        { label: 'Passing Rate', binding: 'passing_rate' },
    ];
    const defaultTableColumns = [
        { label: 'Row #', binding: 'row_no' },
        { label: 'Reference', binding: 'reference_code' },
        { label: 'Applicant Name', binding: 'applicant_name' },
        { label: 'Program', binding: 'program_code' },
        { label: 'Score', binding: 'composite_score' },
        { label: 'Remarks', binding: 'remarks' },
    ];
    const placeholderTableRows = [
        {
            row_no: 1,
            reference_code: 'AY26-00001',
            applicant_name: 'Sample Applicant',
            program_code: 'Sample Program',
            composite_score: '—',
            remarks: 'Pending',
        },
        {
            row_no: 2,
            reference_code: 'AY26-00002',
            applicant_name: 'Sample Applicant',
            program_code: 'Sample Program',
            composite_score: '—',
            remarks: 'Pending',
        },
        {
            row_no: 3,
            reference_code: 'AY26-00003',
            applicant_name: 'Sample Applicant',
            program_code: 'Sample Program',
            composite_score: '—',
            remarks: 'Pending',
        },
    ];
    const kpiMetrics = $derived(
        (
            Array.isArray(props.metrics) && props.metrics.length
                ? props.metrics
                : defaultKpiMetrics
        ).filter((item) => item?.checked !== false),
    );
    const metadataItems = $derived(
        component.props?.items ?? [
            { label: 'Reference', value: '{{reference_code}}' },
            { label: 'Cycle', value: '{{academic_cycle_name}}' },
        ],
    );
    const summaryMetrics = $derived(
        Array.isArray(component.props?.items ?? component.props?.metrics)
            ? (component.props?.items ?? component.props?.metrics).filter(
                  (item) => item?.checked !== false,
              )
            : [],
    );
    const displayMetrics = $derived(
        component.type === 'summary_metrics' ? summaryMetrics : kpiMetrics,
    );
    const tableColumns = $derived(
        (
            Array.isArray(props.columns) && props.columns.length
                ? props.columns
                : defaultTableColumns
        ).filter((item) => item?.checked !== false),
    );
    const tableRows = $derived(
        Array.isArray(sampleContext?.[props.repeat_key || 'passers']) &&
            sampleContext[props.repeat_key || 'passers'].length
            ? sampleContext[props.repeat_key || 'passers']
            : placeholderTableRows,
    );
    function columnLabel(column) {
        return typeof column === 'string'
            ? column
            : (column?.label ?? column?.title ?? column?.key ?? '');
    }

    function columnBinding(column) {
        if (typeof column !== 'string') {
            return column?.binding ?? column?.key ?? column?.field ?? '';
        }

        return (
            {
                'Row #': 'row_no',
                Reference: 'reference_code',
                'Applicant Name': 'applicant_name',
                Program: 'program_code',
                Score: 'composite_score',
                Remarks: 'remarks',
            }[column] ?? column
        );
    }

    function rowValue(row, column) {
        const binding = columnBinding(column);
        const key = binding
            .split('.')
            .reduce((current, part) => current?.[part], row);
        return key ?? '';
    }
    function metadataValue(item) {
        const rawValue =
            typeof item === 'string'
                ? item
                : (item?.value ?? item?.binding ?? '—');
        const macroBinding =
            typeof rawValue === 'string'
                ? rawValue.match(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/)?.[1]
                : null;
        const binding =
            typeof item === 'object' && item !== null
                ? (item.binding ?? macroBinding)
                : macroBinding;

        return binding
            ? value(binding, rawValue)
            : replaceMacros(rawValue);
    }
    const appearance = $derived(props.appearance);
    const cellStyle = $derived(
        `border:1px solid ${appearance.border_color};padding:1.5mm;`,
    );

    function value(binding, fallback = 'Sample value') {
        if (
            sampleContext &&
            typeof sampleContext === 'object' &&
            binding in sampleContext
        ) {
            return sampleContext[binding];
        }
        return sampleValue(binding, fallback);
    }

    function replaceMacros(text) {
        return String(text ?? '').replace(
            /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g,
            (_, binding) => String(value(binding)),
        );
    }

    function partStyle(part, extra = '') {
        return `${inlineTypographyStyle(textStyles[part] ?? typography)}${extra}`;
    }
</script>

<div
    class="h-full w-full min-w-0 overflow-hidden"
    style={inlineTypographyStyle(typography)}
>
    {#if componentTemplate}
        {@html renderComponentTemplate(componentTemplate, component, props, replaceMacros)}
    {:else if component.type === 'header'}
        <div class="flex h-full min-w-0 flex-col justify-center">
            <div style={partStyle('title', 'margin:0;line-height:1.1;')}>
                {replaceMacros(props.title ?? '{{institution_name}}')}
            </div>
            <div style={partStyle('subtitle', 'margin-top:1.5mm;')}>
                {replaceMacros(props.subtitle ?? '{{institution_exam_name}}')}
            </div>
        </div>
    {:else if component.type === 'identity'}
        <table class="w-full border-collapse" style={partStyle('value')}>
            <tbody>
                <tr
                    ><td style={`${cellStyle}${partStyle('label')}`}
                        >Applicant No.</td
                    ><td style={`${cellStyle}${partStyle('value')}`}
                        >{value('applicant_reference')}</td
                    ><td style={`${cellStyle}${partStyle('label')}`}>Name</td
                    ><td style={`${cellStyle}${partStyle('value')}`}
                        >{value('family_name')}, {value('first_name')}</td
                    ></tr
                >
                <tr
                    ><td style={`${cellStyle}${partStyle('label')}`}
                        >Sex / GWA</td
                    ><td style={`${cellStyle}${partStyle('value')}`}
                        >{value('sex')} / {value('gwa')}</td
                    ><td style={`${cellStyle}${partStyle('label')}`}
                        >Course / Strand</td
                    ><td style={`${cellStyle}${partStyle('value')}`}
                        >{value('course_applied_code')} / {value('strand')}</td
                    ></tr
                >
            </tbody>
        </table>
    {:else if component.type === 'metadata_grid'}
        <div class="grid h-full min-w-0 grid-cols-2 content-center gap-2">
            {#each metadataItems as item}
                <div
                    class="flex min-w-0 flex-col justify-center rounded border px-2 py-1"
                    style={`border-color:${appearance.border_color};`}
                >
                    <div style={partStyle('label')}>
                        {typeof item === 'string'
                            ? item
                            : (item?.label ?? item?.title ?? 'Field')}
                    </div>
                    <div style={partStyle('value')}>{metadataValue(item)}</div>
                </div>
            {/each}
        </div>
    {:else if component.type === 'exam_details'}
        <div class="flex h-full min-w-0 items-center" style={partStyle('body')}>
            Date of Examination: {value('exam_date')} ({value('exam_time')}) ·
            Room: {value('room_name')} · Course: {value('course_applied_code')}
        </div>
    {:else if component.type === 'field'}
        <div class="flex h-full min-w-0 items-center gap-1">
            <span style={partStyle('label')}>{props.label ?? 'Field'}:</span
            ><span style={partStyle('value')}
                >{value(props.binding, 'Sample value')}</span
            >
        </div>
    {:else if component.type === 'kpi_grid' || component.type === 'summary_metrics'}
        {#if displayMetrics.length}
            <div class="flex h-full min-w-0 items-stretch gap-2">
                {#each displayMetrics as metric}
                    {@const label =
                        typeof metric === 'string'
                            ? metric
                            : (metric.label ?? metric.title ?? 'Metric')}
                    {@const binding =
                        typeof metric === 'string'
                            ? metric
                            : (metric.binding ?? metric.key ?? '')}
                    <div
                        class="flex min-w-0 flex-1 flex-col justify-center rounded border px-2 py-1"
                        style={`border-color:${appearance.border_color};`}
                    >
                        <div style={partStyle('label')}>{label}</div>
                        <div style={partStyle('value')}>
                            {binding
                                ? value(binding, metric.value ?? '—')
                                : (metric.value ?? '—')}
                        </div>
                    </div>
                {/each}
            </div>
        {:else}
            <div class="flex h-full items-center justify-center text-xs opacity-60">No metrics enabled</div>
        {/if}
    {:else if component.type === 'data_table'}
        {#if props.title}
            <div style={partStyle('title')}>{props.title}</div>
        {/if}
        {#if tableColumns.length}
            <table class="w-full border-collapse" style={partStyle('body')}>
                <thead>
                    <tr>
                        {#each tableColumns as column}
                            <th
                                class="text-left"
                                style={`${cellStyle}${partStyle('heading')}`}
                                >{columnLabel(column)}</th
                            >
                        {/each}
                    </tr>
                </thead>
                <tbody>
                    {#each tableRows as row, rowIndex}
                        <tr>
                            {#each tableColumns as column}
                                <td style={`${cellStyle}${partStyle('body')}`}
                                    >{rowValue(row, column) ||
                                        (columnBinding(column) === 'row_no'
                                            ? rowIndex + 1
                                            : '')}</td
                                >
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </table>
        {:else}
            <div class="flex h-full items-center justify-center text-xs opacity-60">No columns enabled</div>
        {/if}
    {:else if component.type === 'score_table'}
        <div style={partStyle('title')}>
            {props.title ?? 'Record of Scores'}
        </div>
        <table class="mt-2 w-full border-collapse">
            <thead
                ><tr
                    ><th
                        class="text-left"
                        style={`${cellStyle}${partStyle('heading')}`}
                        >Aptitude Factor</th
                    ><th
                        class="text-left"
                        style={`${cellStyle}${partStyle('heading')}`}
                        >{value('score_table_raw_heading', 'Raw Score')}</th
                    ><th
                        class="text-left"
                        style={`${cellStyle}${partStyle('heading')}`}
                        >{value('score_table_final_heading', 'Descriptive Rating')}</th
                    ></tr
                ></thead
            >
            <tbody>
                {#each scoreRows as row}
                    <tr>
                        <td style={`${cellStyle}${partStyle('body')}`}
                            >{row.domain ?? 'Aptitude'}</td
                        ><td style={`${cellStyle}${partStyle('body')}`}
                            >{row.raw ?? ''}</td
                        ><td style={`${cellStyle}${partStyle('body')}`}
                            >{row.converted ?? ''}</td
                        >
                    </tr>
                {/each}
            </tbody>
        </table>
    {:else if component.type === 'overall'}
        <div class="flex h-full min-w-0 items-center gap-2">
            <span style={partStyle('label')}>{#if value('overall_heading', 'Overall Score')}{value('overall_heading', 'Overall Score')}:{/if}</span><span
                style={partStyle('value')}>{value('overall_pct')}</span
            >
        </div>
    {:else if component.type === 'recommendation'}
        <div style={partStyle('heading')}>RECOMMENDED PROGRAM</div>
        <div class="mt-1" style={partStyle('body')}>
            {value('recommended_course')}
        </div>
        <div class="mt-2" style={partStyle('note')}>
            {value('counselor_comments')}
        </div>
    {:else if component.type === 'signature'}
        <div
            class="flex h-full min-w-0 flex-col items-center justify-center text-center"
        >
            <div class="mb-1 h-[8mm] w-[70%] border-b border-slate-400"></div>
            <div style={partStyle('name')}>
                {value(props.binding, 'Guidance Counselor')}
            </div>
            <div style={partStyle('role')}>{props.label ?? 'Counselor'}</div>
        </div>
    {:else if component.type === 'note'}
        <div style={partStyle('body', 'font-style:italic;')}>
            {replaceMacros(props.text ?? 'Policy or operational note.')}
        </div>
    {:else if component.type === 'footer'}
        <div
            class="flex h-full min-w-0 items-center justify-center"
            style={partStyle('body', 'opacity:.7;')}
        >
            {replaceMacros(
                props.text ??
                    'Generated from the NEXIAM result-sheet template.',
            )}
        </div>
    {:else if ['text', 'paragraph'].includes(component.type)}
        <div class="flex h-full min-w-0 items-center" style={partStyle('body')}>
            {replaceMacros(props.text ?? 'Add text')}
        </div>
    {:else if component.type === 'divider'}
        <div
            class="mt-1 w-full border-t"
            style={`border-color:${appearance.border_color};border-top-width:${Math.max(1, appearance.border_width_px)}px;border-top-style:${appearance.border_style};`}
        ></div>
    {:else if component.type === 'spacer'}
        <div class="h-full"></div>
    {:else if component.type === 'dynamic_image'}
        <div
            class="flex h-full flex-col items-center justify-center border-2 border-dashed border-slate-300 bg-slate-50 text-[10px] font-medium text-slate-500"
        >
            <span>2x2 Photo</span>
            <span class="text-[8px] opacity-75"
                >{component.props?.binding ?? 'applicant_photo_2x2'}</span
            >
        </div>
    {:else if component.type === 'dynamic_qr'}
        <div
            class="flex h-full flex-col items-center justify-center border border-slate-300 bg-white p-1 font-mono text-[10px] text-slate-700"
        >
            <div
                class="flex size-6 items-center justify-center border-2 border-black text-[7px] font-bold"
            >
                QR
            </div>
            <span class="mt-0.5 max-w-full truncate text-[8px] opacity-75"
                >{component.props?.binding ?? 'verification_qr'}</span
            >
        </div>
    {:else if component.type === 'image'}
        {#if noticeDocumentImagePreview && safeNoticeImageSrc}
            <img src={safeNoticeImageSrc} alt={component.props?.alt ?? 'Document image'} class="h-full w-full" style={`object-fit:${props.fit ?? 'contain'};`} />
        {:else}
            <div class="flex h-full items-center justify-center border border-dashed border-slate-300 text-xs opacity-60">
                {noticeDocumentImagePreview ? 'Verified image unavailable' : 'Approved image'}
            </div>
        {/if}
    {:else}
        <div>{component.type}</div>
    {/if}
</div>
