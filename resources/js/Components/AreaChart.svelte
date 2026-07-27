<script>
  let { title, subtitle, labels = [], values = [], height = 180, trendDelta = null, trendLabel = '' } = $props();

  const safeValues = $derived(Array.isArray(values) ? values.map((v) => Number(v) || 0) : []);
  const safeLabels = $derived(Array.isArray(labels) ? labels : []);
  const min = $derived(safeValues.length ? Math.min(...safeValues) : 0);
  const max = $derived(safeValues.length ? Math.max(...safeValues) : 0);
  const range = $derived(max - min || 1);
  const n = $derived(safeValues.length);

  const padTop = 16;
  const padBottom = 24; 
  const padLeft = 8;
  const padRight = 8;
  const W = 400;

  function points(w, h) {
    if (n <= 1) return '';
    const innerW = w - padLeft - padRight;
    const innerH = h - padTop - padBottom;
    return safeValues
      .map((v, i) => {
        const x = padLeft + (innerW * i) / (n - 1);
        const y = padTop + innerH - ((v - min) / range) * innerH;
        return `${x},${y}`;
      })
      .join(' ');
  }

  const lastValue = $derived(safeValues[n - 1] ?? 0);
  
  let hoveredIndex = $state(null);

  function getX(i, w) {
    if (n <= 1) return 0;
    return padLeft + ((w - padLeft - padRight) * i) / (n - 1);
  }
  
  function getY(v, h) {
    const innerH = h - padTop - padBottom;
    return padTop + innerH - ((v - min) / range) * innerH;
  }
</script>

<div class="space-y-4">
  <div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
      {#if title}<h3 class="font-semibold text-foreground truncate">{title}</h3>{/if}
      {#if subtitle}<div class="text-sm text-muted-foreground truncate">{subtitle}</div>{/if}
      
      {#if trendDelta !== null}
         <div class="flex items-center gap-1 text-xs mt-1 {trendDelta > 0 ? 'text-emerald-500' : trendDelta < 0 ? 'text-rose-500' : 'text-muted-foreground'}">
            <span class="font-medium">{trendDelta > 0 ? '+' : ''}{trendDelta}</span>
            <span class="opacity-80 ml-1">{trendLabel}</span>
         </div>
      {/if}
    </div>
    <div class="shrink-0 text-2xl font-bold tabular-nums text-foreground/90">
      {lastValue}
    </div>
  </div>

  {#if n > 1}
    <div class="relative w-full" style="height: {height}px" role="img" aria-label="Area chart">
      <svg viewBox={`0 0 ${W} ${height}`} class="w-full h-full overflow-visible" preserveAspectRatio="none"
         onpointerleave={() => hoveredIndex = null}
      >
        <defs>
          <linearGradient id="areaFill-{title?.replace(/\s+/g,'-') || 'chart'}" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="var(--color-primary, rgb(59 130 246))" stop-opacity="0.3" />
            <stop offset="100%" stop-color="var(--color-primary, rgb(59 130 246))" stop-opacity="0.0" />
          </linearGradient>
        </defs>

        <!-- Grid lines Y -->
        <line x1={padLeft} y1={padTop} x2={W-padRight} y2={padTop} stroke="currentColor" class="text-border/40" stroke-dasharray="4,4" />
        <line x1={padLeft} y1={padTop + (height - padTop - padBottom)/2} x2={W-padRight} y2={padTop + (height - padTop - padBottom)/2} stroke="currentColor" class="text-border/40" stroke-dasharray="4,4" />
        <line x1={padLeft} y1={height - padBottom} x2={W-padRight} y2={height - padBottom} stroke="currentColor" class="text-border/40" />
        
        <!-- Area -->
        <polyline
          points={`${points(W, height)} ${W-padRight},${height - padBottom} ${padLeft},${height - padBottom}`}
          fill="url(#areaFill-{title?.replace(/\s+/g,'-') || 'chart'})"
        />
        <!-- Line -->
        <polyline
          points={points(W, height)}
          fill="none"
          stroke="var(--color-primary, rgb(59 130 246))"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        
        <!-- Y axis Labels -->
        <text x="0" y={padTop + 4} fill="currentColor" class="text-[10px] text-muted-foreground/60">{max}</text>
        <text x="0" y={height - padBottom - 2} fill="currentColor" class="text-[10px] text-muted-foreground/60">{min}</text>
        
        <!-- X axis Labels -->
        <text x={padLeft} y={height - 8} fill="currentColor" class="text-[10px] text-muted-foreground">{safeLabels[0] || ''}</text>
        <text x={W - padRight} y={height - 8} text-anchor="end" fill="currentColor" class="text-[10px] text-muted-foreground">{safeLabels[n-1] || ''}</text>

        <!-- Interactive hover points -->
        {#each safeValues as v, i}
          <g transform="translate({getX(i, W)}, {getY(v, height)})">
             <!-- Increased hover area -->
             <!-- svelte-ignore a11y_no_static_element_interactions -->
             <!-- svelte-ignore a11y_mouse_events_have_key_events -->
             <circle 
                r="16" 
                fill="transparent" 
                class="cursor-crosshair opacity-0"
                onpointerenter={() => hoveredIndex = i}
             />
             {#if hoveredIndex === i}
                <!-- Line down to axis -->
                <line x1="0" y1="0" x2="0" y2={height - padBottom - getY(v, height)} stroke="var(--color-primary, rgb(59 130 246))" stroke-width="1" stroke-dasharray="2,2" class="opacity-50" />
                
                <circle r="4" fill="var(--color-primary, rgb(59 130 246))" stroke="currentColor" class="text-background" stroke-width="2" />
                
                <!-- Tooltip positioning magic to avoid clipping -->
                <g transform="translate({Math.max(30, Math.min(W - padLeft - padRight - 30, getX(i, W))) - getX(i, W)}, {getY(v, height) < 50 ? 20 : -20})">
                    <rect x="-30" y="-14" width="60" height="24" rx="4" fill="currentColor" class="text-foreground dark:text-card" />
                    <text x="0" y="2" text-anchor="middle" fill="currentColor" class="text-[10px] font-bold text-background dark:text-foreground">{v}</text>
                    {#if safeLabels[i]}
                        <rect x="-35" y="12" width="70" height="16" rx="2" fill="currentColor" class="text-muted/80" />
                        <text x="0" y="23" text-anchor="middle" fill="currentColor" class="text-[9px] text-foreground font-medium">{safeLabels[i]}</text>
                    {/if}
                </g>
             {/if}
          </g>
        {/each}
      </svg>
    </div>
  {:else}
    <div class="flex items-center justify-center p-8 border border-dashed border-border/50 rounded-xl h-[${height}px]">
      <div class="text-sm text-muted-foreground">Not enough data to graph</div>
    </div>
  {/if}
</div>
