<script>
  /**
   * ImportFileAnalyzer — Reusable file analysis component for all import pages.
   *
   * After a file is selected, this component uploads it to an `analyzeUrl` endpoint
   * and displays structured feedback: column mapping, data checks, and row count.
   *
   * Usage:
   *   <ImportFileAnalyzer
   *     file={selectedFile}
   *     analyzeUrl="/admin/applications/import/analyze"
   *     onanalysis={(result) => analysis = result}
   *   />
   */
  import { CheckCircle2, XCircle, AlertTriangle, Loader2, FileSpreadsheet, ArrowRight } from 'lucide-svelte';

  let {
    file = null,
    analyzeUrl = '',
    onanalysis = null,
  } = $props();

  let analyzing = $state(false);
  let analysis = $state(null);
  let analyzeError = $state('');

  // Watch file changes and trigger analysis
  $effect(() => {
    if (file && file.length > 0 && analyzeUrl) {
      runAnalysis(file);
    } else {
      analysis = null;
      analyzeError = '';
    }
  });

  async function runAnalysis(fileData) {
    analyzing = true;
    analyzeError = '';
    analysis = null;

    try {
      const formData = new FormData();
      // FileUpload returns an array
      const actualFile = Array.isArray(fileData) ? fileData[0] : fileData;
      if (!actualFile) return;
      formData.append('file', actualFile);

      // Read XSRF-TOKEN from cookie (Laravel's default CSRF mechanism for SPAs)
      const xsrfToken = decodeURIComponent(
        document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''
      );
      const response = await fetch(analyzeUrl, {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json',
          'X-XSRF-TOKEN': xsrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });

      // Handle CSRF token expiry — page needs refresh
      if (response.status === 419) {
        analyzeError = 'Session expired. Please refresh the page and try again.';
        onanalysis?.(null);
        return;
      }

      // Safely parse JSON (server might return HTML on error)
      let data;
      const contentType = response.headers.get('content-type') || '';
      if (contentType.includes('application/json')) {
        data = await response.json();
      } else {
        analyzeError = 'Unexpected server response. Please refresh the page and try again.';
        onanalysis?.(null);
        return;
      }

      if (!response.ok) {
        analyzeError = data.error || 'Failed to analyze file';
        analysis = data.checks ? { checks: data.checks } : null;
        onanalysis?.(null);
        return;
      }

      analysis = data;
      onanalysis?.(data);
    } catch (e) {
      analyzeError = 'Unable to analyze file. Please refresh the page and try again.';
      onanalysis?.(null);
    } finally {
      analyzing = false;
    }
  }

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

  function columnStatusLabel(status) {
    return {
      required: 'Required',
      optional: 'Optional',
      fuzzy: 'Fuzzy match',
      unknown: 'Ignored',
    }[status] || status;
  }

  function columnStatusColor(status) {
    return {
      required: 'bg-success/15 text-foreground',
      optional: 'bg-info/15 text-foreground',
      fuzzy: 'bg-warning/20 text-foreground',
      unknown: 'bg-muted text-muted-foreground',
    }[status] || 'bg-muted text-muted-foreground';
  }

  let allChecksPassed = $derived(
    analysis?.checks?.every(c => c.status !== 'fail') ?? false
  );

  let hasFailedChecks = $derived(
    analysis?.checks?.some(c => c.status === 'fail') ?? false
  );
</script>

{#if analyzing}
  <div class="rounded-lg border border-border bg-card p-6 flex items-center gap-3 text-muted-foreground">
    <Loader2 class="size-5 animate-spin text-primary" />
    <span class="text-sm font-medium">Analyzing file...</span>
  </div>
{/if}

{#if analyzeError && !analysis}
  <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30 p-4">
    <div class="flex items-start gap-2">
      <XCircle class="size-5 text-red-600 dark:text-red-400 mt-0.5 shrink-0" />
      <div>
        <p class="text-sm font-medium text-red-700 dark:text-red-400">File Analysis Failed</p>
        <p class="text-sm text-red-600 dark:text-red-400 mt-1">{analyzeError}</p>
      </div>
    </div>
  </div>
{/if}

{#if analysis && !analyzing}
  <div class="rounded-lg border border-border bg-card overflow-hidden">
    <!-- Header -->
    <div class="px-4 py-3 bg-muted/50 border-b border-border flex items-center justify-between">
      <div class="flex items-center gap-2">
        <FileSpreadsheet class="size-4 text-primary" />
        <span class="text-sm font-semibold">File Analysis</span>
      </div>
      {#if analysis.file_name}
        <span class="text-xs text-muted-foreground truncate max-w-[200px]">{analysis.file_name}</span>
      {/if}
    </div>

    <div class="p-4 space-y-4">
      <!-- Pre-upload checks -->
      {#if analysis.checks?.length}
        <div class="space-y-2">
          <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pre-Upload Checks</h4>
          <div class="grid gap-1.5">
            {#each analysis.checks as check}
              <div class="flex items-center gap-2.5 px-3 py-2 rounded-md border {statusBg(check.status)}">
                <svelte:component this={statusIcon(check.status)} class="size-4 shrink-0 {statusColor(check.status)}" />
                <span class="text-sm font-medium flex-1">{check.label}</span>
                <span class="text-xs font-semibold {statusColor(check.status)}">{check.detail}</span>
              </div>
            {/each}
          </div>
        </div>
      {/if}

      <!-- Column mapping -->
      {#if analysis.column_analysis?.length}
        <div class="space-y-2">
          <h4 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Column Mapping
            <span class="font-normal">({analysis.column_analysis.length} columns detected)</span>
          </h4>
          <div class="rounded-md border border-border overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-muted/60">
                <tr>
                  <th class="px-3 py-1.5 text-left text-xs font-medium text-muted-foreground">Your Header</th>
                  <th class="px-3 py-1.5 text-center text-xs font-medium text-muted-foreground"></th>
                  <th class="px-3 py-1.5 text-left text-xs font-medium text-muted-foreground">Mapped To</th>
                  <th class="px-3 py-1.5 text-right text-xs font-medium text-muted-foreground">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border">
                {#each analysis.column_analysis as col}
                  <tr class="hover:bg-muted/30 transition-colors">
                    <td class="px-3 py-1.5">
                      <code class="text-xs bg-muted px-1.5 py-0.5 rounded font-mono">{col.raw}</code>
                    </td>
                    <td class="px-1 py-1.5 text-center">
                      <ArrowRight class="size-3 text-muted-foreground mx-auto" />
                    </td>
                    <td class="px-3 py-1.5">
                      {#if col.matched_to}
                        <code class="text-xs px-1.5 py-0.5 rounded font-mono bg-success/15 text-foreground font-semibold">{col.matched_to}</code>
                      {:else}
                        <span class="text-xs text-muted-foreground italic">—</span>
                      {/if}
                    </td>
                    <td class="px-3 py-1.5 text-right">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium {columnStatusColor(col.status)}">
                        {columnStatusLabel(col.status)}
                      </span>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
      {/if}

      <!-- Summary bar -->
      <div class="flex items-center gap-3 pt-1">
        {#if analysis.row_count !== undefined}
          <div class="text-xs text-muted-foreground">
            <span class="font-semibold text-foreground">{analysis.row_count}</span> data row{analysis.row_count !== 1 ? 's' : ''} found
          </div>
        {/if}

        <div class="ml-auto">
          {#if hasFailedChecks}
            <span class="text-xs font-medium text-red-600 dark:text-red-400 flex items-center gap-1">
              <XCircle class="size-3.5" />
              Fix issues above before importing
            </span>
          {:else if allChecksPassed}
            <span class="text-xs font-semibold text-success flex items-center gap-1">
              <CheckCircle2 class="size-3.5" />
              File ready to import
            </span>
          {/if}
        </div>
      </div>
    </div>
  </div>
{/if}
