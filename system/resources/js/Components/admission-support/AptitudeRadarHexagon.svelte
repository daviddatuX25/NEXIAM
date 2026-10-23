<script>
    let { radarComparison = null, selectedProgram = null } = $props();

    const AXES = [
        { key: 'logic', label: 'Logic', sourceKey: 'LOGIC' },
        { key: 'verbal', label: 'Verbal', sourceKey: 'PROFILE_VERBAL' },
        { key: 'numerical', label: 'Numerical', sourceKey: 'PROFILE_QUANT' },
        { key: 'spatial', label: 'Spatial', sourceKey: 'SPATIAL' },
        { key: 'memory', label: 'Memory', sourceKey: 'MEMORY' },
        { key: 'language', label: 'Language', sourceKey: 'LANGUAGE' },
    ];
    const SERIES = [
        { key: 'student', label: 'Student', color: '#4f46e5', dash: null },
        { key: 'cutoff', label: 'Cutoff', color: '#b45309', dash: '7 3' },
        { key: 'regular', label: 'Regular', color: '#047857', dash: '3 2' },
        { key: 'honors', label: 'Honors', color: '#0369a1', dash: '10 3 2 3' },
    ];
    const CX = 180;
    const CY = 155;
    const RADIUS = 91;
    const LEVELS = [25, 50, 75, 100];

    function getAngle(index) {
        return index * (Math.PI / 3) - Math.PI / 2;
    }

    function isValidValues(values) {
        return values !== null
            && typeof values === 'object'
            && !Array.isArray(values)
            && AXES.every(({ key }) => Object.hasOwn(values, key)
                && (values[key] === null || (typeof values[key] === 'number' && Number.isFinite(values[key]) && values[key] >= 0 && values[key] <= 100)))
            && Object.keys(values).every((key) => AXES.some((axis) => axis.key === key));
    }

    function isValidContract(contract) {
        return contract !== null
            && typeof contract === 'object'
            && contract.version === 1
            && contract.scale?.kind === 'percentile'
            && contract.scale.min === 0
            && contract.scale.max === 100
            && Array.isArray(contract.axes)
            && contract.axes.length === AXES.length
            && AXES.every((axis, index) => {
                const supplied = contract.axes[index];
                return supplied?.key === axis.key
                    && supplied?.label === axis.label
                    && supplied?.source_key === axis.sourceKey;
            })
            && isValidValues(contract.student?.values)
            && contract.profiles?.cutoff?.tier === 3
            && isValidValues(contract.profiles.cutoff.values)
            && contract.profiles?.regular?.tier === 2
            && isValidValues(contract.profiles.regular.values)
            && contract.profiles?.honors?.tier === 1
            && isValidValues(contract.profiles.honors.values);
    }

    const isAvailable = $derived(isValidContract(radarComparison));
    const spokes = $derived(AXES.map((axis, index) => {
        const angle = getAngle(index);
        const cos = Math.cos(angle);
        const sin = Math.sin(angle);
        const labelRadius = RADIUS + 16;
        return {
            ...axis,
            x2: CX + RADIUS * cos,
            y2: CY + RADIUS * sin,
            labelX: CX + labelRadius * cos,
            labelY: CY + labelRadius * sin,
            anchor: Math.abs(cos) < 0.2 ? 'middle' : cos > 0 ? 'start' : 'end',
        };
    }));

    function pointsFor(values) {
        return AXES.map((axis, index) => {
            const value = values[axis.key];
            if (value === null) {
                return null;
            }
            const angle = getAngle(index);
            const radius = (value / 100) * RADIUS;
            return {
                key: axis.key,
                value,
                x: CX + radius * Math.cos(angle),
                y: CY + radius * Math.sin(angle),
            };
        });
    }

    const seriesData = $derived(isAvailable
        ? SERIES.map((series) => {
            const values = series.key === 'student'
                ? radarComparison.student.values
                : radarComparison.profiles[series.key].values;
            const points = pointsFor(values);
            const complete = points.every((point) => point !== null);
            return {
                ...series,
                points,
                complete,
                polygon: complete ? points.map((point) => `${point.x},${point.y}`).join(' ') : '',
            };
        })
        : []);

    const rows = $derived(isAvailable
        ? AXES.map((axis) => ({
            ...axis,
            values: SERIES.map((series) => series.key === 'student'
                ? radarComparison.student.values[axis.key]
                : radarComparison.profiles[series.key].values[axis.key]),
        }))
        : []);

    function displayValue(value) {
        return value === null ? '—' : String(value);
    }
</script>

{#if isAvailable}
    <section class="min-w-0 rounded-lg border border-border/50 bg-background/50 p-3" aria-label="Test subject comparison">
        <div class="border-b border-border/40 pb-2">
            <h3 class="text-sm font-semibold text-foreground">
                {#if selectedProgram}
                    Aptitude Profile: {selectedProgram.program_name} ({selectedProgram.program_code})
                {:else}
                    Aptitude Profile Comparison
                {/if}
            </h3>
            <p class="mt-1 text-xs text-muted-foreground">
                {#if selectedProgram}
                    Comparing student percentiles against {selectedProgram.program_code} Cutoff, Regular, and Honors benchmarks.
                {:else}
                    Recorded student percentiles compared with program benchmark profiles.
                {/if}
            </p>
        </div>

        <div class="mx-auto w-full max-w-[360px]">
            <svg viewBox="0 0 360 300" class="block h-auto w-full" role="img" aria-label="Radar comparison of student, cutoff, regular, and honors percentiles across six test subjects.">
                {#each LEVELS as level (level)}
                    <polygon
                        points={AXES.map((_, index) => {
                            const angle = getAngle(index);
                            const radius = (level / 100) * RADIUS;
                            return `${CX + radius * Math.cos(angle)},${CY + radius * Math.sin(angle)}`;
                        }).join(' ')}
                        fill="none"
                        class="stroke-border/70"
                        stroke-width={level === 100 ? '1.5' : '1'}
                        stroke-dasharray={level === 100 ? 'none' : '3 3'}
                    />
                    <text x={CX} y={CY - (level / 100) * RADIUS + 8} text-anchor="middle" class="fill-muted-foreground text-[9px] font-mono">{level}</text>
                {/each}
                {#each spokes as spoke (spoke.key)}
                    <line x1={CX} y1={CY} x2={spoke.x2} y2={spoke.y2} class="stroke-border/80" stroke-width="1" />
                    <text x={spoke.labelX} y={spoke.labelY} text-anchor={spoke.anchor} dominant-baseline="central" class="fill-foreground text-[11px] font-medium">{spoke.label}</text>
                {/each}

                {#each seriesData as series (series.key)}
                    {#if series.complete}
                        <polygon
                            points={series.polygon}
                            fill="none"
                            stroke={series.color}
                            stroke-width={series.key === 'student' ? '2.5' : '2'}
                            stroke-dasharray={series.dash ?? 'none'}
                            stroke-linejoin="round"
                        />
                    {/if}
                    {#each series.points as point, index (`${series.key}-${index}`)}
                        {#if point}
                            <circle cx={point.x} cy={point.y} r={series.key === 'student' ? '3.5' : '3'} fill={series.color}>
                                <title>{series.label}, {AXES[index].label}: {point.value}</title>
                            </circle>
                        {/if}
                    {/each}
                {/each}
            </svg>
        </div>

        {#if seriesData.some((series) => !series.complete)}
            <p class="border-t border-border/30 py-2 text-xs text-muted-foreground">Partial profiles show recorded points only; incomplete profiles are not connected or filled.</p>
        {/if}

        <ul class="flex flex-wrap gap-x-4 gap-y-2 border-t border-border/30 py-2 text-xs" aria-label="Radar series legend">
            {#each SERIES as series (series.key)}
                <li class="inline-flex items-center gap-2 font-medium text-foreground">
                    <svg width="25" height="10" aria-hidden="true" class="shrink-0">
                        <line x1="1" y1="5" x2="24" y2="5" stroke={series.color} stroke-width="2.5" stroke-dasharray={series.dash ?? 'none'} />
                    </svg>
                    {series.label}
                </li>
            {/each}
        </ul>

        <div class="mt-2 border-t border-border/30 pt-2">
            <table class="w-full border-collapse text-left text-xs" aria-label="Test subject percentile values">
                <thead>
                    <tr class="text-muted-foreground">
                        <th scope="col" class="px-1.5 py-1.5 font-semibold">Subject</th>
                        {#each SERIES as series (series.key)}
                            <th scope="col" class="px-1.5 py-1.5 text-right font-semibold">{series.label}</th>
                        {/each}
                    </tr>
                </thead>
                <tbody>
                    {#each rows as row (row.key)}
                        <tr class="border-t border-border/30 text-foreground">
                            <th scope="row" class="px-1.5 py-1.5 font-medium">{row.label}</th>
                            {#each row.values as value, index (`${row.key}-${index}`)}
                                <td class="px-1.5 py-1.5 text-right font-mono tabular-nums">{displayValue(value)}</td>
                            {/each}
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </section>
{:else}
    <section class="rounded-lg border border-border/50 bg-background/50 p-3" aria-label="Test subject comparison unavailable" role="status">
        <h3 class="text-sm font-semibold text-foreground">Test subject comparison unavailable</h3>
        <p class="mt-1 text-xs text-muted-foreground">Compatible percentile-scale source data are not available for this stored result.</p>
    </section>
{/if}
