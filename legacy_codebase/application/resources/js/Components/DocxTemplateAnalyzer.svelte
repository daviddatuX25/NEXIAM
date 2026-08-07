<script>
  import { CheckCircle2, XCircle, AlertTriangle, FileText } from 'lucide-svelte';

  let {
    result = null,
    loading = false,
  } = $props();

  function statusIcon(status) {
    return { pass: CheckCircle2, fail: XCircle, warn: AlertTriangle }[status] || AlertTriangle;
  }

  function statusColor(status) {
    return {
      pass: 'text-success',
      fail: 'text-destructive',
      warn: 'text-warning-foreground',
    }[status] || 'text-muted-foreground';
  }

  function statusBg(status) {
    return {
      pass: 'bg-success/10 border-success/30',
      fail: 'bg-destructive/10 border-destructive/30',
      warn: 'bg-warning/20 border-warning/40',
    }[status] || 'bg-muted border-border';
  }

  function phStatusColor(status) {
    return {
      found: 'bg-success/15 text-foreground',
      missing: 'bg-destructive/15 text-destructive',
      optional: 'bg-muted text-muted-foreground',
      unknown: 'bg-warning/20 text-warning-foreground',
    }[status] || 'bg-muted text-muted-foreground';
  }

  function fmt(ph) {
    return '{{' + ph + '}}';
  }

  let allChecksPassed = $derived(
    result?.checks?.every(c => c.status !== 'fail') ?? false
  );

  let hasFailedChecks = $derived(
    result?.checks?.some(c => c.status === 'fail') ?? false
  );
</script>

{#if loading}
  <div class="rounded-lg border border-border bg-card p-6 flex items-center gap-3 text-muted-foreground">
    <svg class="size-5 animate-spin text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
    <span class="text-sm font-medium">Validating template...</span>
  </div>
{/if}

{#if result && !loading}
  <div class="rounded-lg border border-border bg-card overflow-hidden">
    <!-- Header -->
    <div class="px-4 py-3 bg-muted/50 border-b border-border flex items-center justify-between">
      <div class="flex items-center gap-2">
        <FileText class="size-4 text-primary" />
        <span class="text-sm font-semibold">Template Analysis</span>
      </div>
    </div>

    <div class="p-4 space-y-4">
      <!-- Pre-upload checks -->
      {#if result.checks?.length}
        <div class="space-y-2">
          <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pre-Upload Checks</h4>
          <div class="grid gap-1.5">
            {#each result.checks as check}
              <div class="flex items-center gap-2.5 px-3 py-2 rounded-md border {statusBg(check.status)}">
                <svelte:component this={statusIcon(check.status)} class="size-4 shrink-0 {statusColor(check.status)}" />
                <span class="text-sm font-medium flex-1">{check.label}</span>
                <span class="text-xs font-semibold {statusColor(check.status)}">{check.detail}</span>
              </div>
            {/each}
          </div>
        </div>
      {/if}

      <!-- Placeholder mapping table -->
      {#if (result.found?.length || 0) + (result.missing?.length || 0) + (result.extra?.length || 0) > 0}
        <div class="space-y-2">
          <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Placeholder Mapping
            <span class="font-normal">({(result.found?.length || 0) + (result.missing?.length || 0) + (result.extra?.length || 0)} placeholders)</span>
          </h4>
          <div class="rounded-md border border-border overflow-hidden max-h-64 overflow-y-auto">
            <table class="w-full text-sm">
              <thead class="bg-muted/60 sticky top-0">
                <tr>
                  <th class="px-3 py-1.5 text-left text-xs font-medium text-muted-foreground">Placeholder</th>
                  <th class="px-3 py-1.5 text-right text-xs font-medium text-muted-foreground">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                {#each result.found || [] as ph}
                  <tr class="hover:bg-muted/30 transition-colors">
                    <td class="px-3 py-1.5">
                      <code class="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">{fmt(ph)}</code>
                    </td>
                    <td class="px-3 py-1.5 text-right">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {phStatusColor('found')}">
                        Found
                      </span>
                    </td>
                  </tr>
                {/each}
                {#each result.missing || [] as ph}
                  <tr class="hover:bg-muted/30 transition-colors">
                    <td class="px-3 py-1.5">
                      <code class="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">{fmt(ph)}</code>
                    </td>
                    <td class="px-3 py-1.5 text-right">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {phStatusColor('missing')}">
                        Missing
                      </span>
                    </td>
                  </tr>
                {/each}
                {#each result.extra || [] as ph}
                  <tr class="hover:bg-muted/30 transition-colors">
                    <td class="px-3 py-1.5">
                      <code class="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">{fmt(ph)}</code>
                    </td>
                    <td class="px-3 py-1.5 text-right">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {phStatusColor('unknown')}">
                        Unknown
                      </span>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
      {/if}

      <!-- Missing placeholders detail (collapsible) -->
      {#if result.missingOptional?.length}
        <details class="text-xs text-muted-foreground">
          <summary class="cursor-pointer">{result.missingOptional.length} optional placeholder(s) not used</summary>
          <div class="flex flex-wrap gap-1 mt-1">
            {#each result.missingOptional as ph}
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-muted text-muted-foreground">{fmt(ph)}</span>
            {/each}
          </div>
        </details>
      {/if}

      <!-- Summary bar -->
      <div class="flex items-center gap-3 pt-1">
        <div class="text-xs text-muted-foreground">
          <span class="font-semibold text-foreground">{(result.found?.length || 0) + (result.extra?.length || 0)}</span> placeholder{((result.found?.length || 0) + (result.extra?.length || 0)) !== 1 ? 's' : ''} found
        </div>

        <div class="ml-auto">
          {#if hasFailedChecks}
            <span class="text-xs font-medium text-red-600 dark:text-red-400 flex items-center gap-1">
              <XCircle class="size-3.5" />
              Fix issues above
            </span>
          {:else if allChecksPassed}
            <span class="text-xs font-semibold text-success flex items-center gap-1">
              <CheckCircle2 class="size-3.5" />
              Template ready
            </span>
          {/if}
        </div>
      </div>
    </div>
  </div>
{/if}