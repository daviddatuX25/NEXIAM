<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm, page } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { FileUpload } from '@/Components/ui/file-upload';
  import { Upload, Download, ArrowRight } from 'lucide-svelte';
  import { GuidePanel, GuideSection, CopyableGroup, GuideNote } from '@/Components/Guide';
  import ImportFileAnalyzer from '@/Components/ImportFileAnalyzer.svelte';

  let {
    enableNormalizedScores = false,
    aptitudeAreaCodes = [],
    requiredColumns = ['applicant_number'],
    optionalColumns = [],
    previewUrl = '/admin/grading/import/preview',
  } = $props();

  const breadcrumbs = [
    { label: 'Grading', href: '/admin/grading' },
    { label: 'Import Scores' },
  ];

  const form = useForm({
    file: null,
  });

  let selectedFile = $state(null);
  let fileAnalysis = $state(null);

  function onAnalysis(result) {
    fileAnalysis = result;
  }

  // Whether the file passes all checks (no failures)
  let fileReady = $derived(
    fileAnalysis?.checks?.length > 0 && fileAnalysis.checks.every(c => c.status !== 'fail')
  );

  function submitPreview(e) {
    e.preventDefault();
    if (!selectedFile || !fileReady) return;

    const actualFile = Array.isArray(selectedFile) ? selectedFile[0] : selectedFile;
    if (!actualFile) return;

    $form.file = actualFile;
    $form.post(previewUrl, { forceFormData: true });
  }

  let message = $state('');
  let error = $state('');

  $effect(() => {
    message = $page.props.flash?.message || '';
    error = $page.props.flash?.error || '';
  });

  const scoreSuffix = enableNormalizedScores ? '(raw)' : '(normalized)';
  const requiredItems = requiredColumns.map((c) => ({ value: c, label: c }));
  const aptitudeAreaItems = aptitudeAreaCodes.map((code) => ({ value: code }));
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-3xl space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">Bulk Import Scores</h1>
      <p class="text-sm text-muted-foreground mt-1">
        Import applicant scores via spreadsheet upload. Columns use aptitude area codes.
        Headers are auto-matched — spaces, dashes, and capitalization are handled automatically.
      </p>
    </div>

    {#if message}
      <div class="rounded-md bg-success/10 border border-success/30 p-4 text-foreground">
        <pre class="whitespace-pre-wrap text-sm">{message}</pre>
      </div>
    {/if}

    {#if error}
      <div class="rounded-md bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 p-4 text-red-700 dark:text-red-400">
        <pre class="whitespace-pre-wrap text-sm">{error}</pre>
      </div>
    {/if}

    <GuidePanel title="Import Guide">
      <GuideSection title="Required Column">
        <CopyableGroup items={requiredItems} />
      </GuideSection>

      <GuideSection title="Score Columns" visible={aptitudeAreaCodes.length > 0}>
        <CopyableGroup
          items={aptitudeAreaItems}
          subtitle="Use these aptitude area codes as column headers. Values are {scoreSuffix}."
        />
      </GuideSection>

      <GuideNote variant={enableNormalizedScores ? 'info' : 'warning'} title={enableNormalizedScores ? 'Auto-Compute Mode' : 'Manual Entry Mode'}>
        {#if enableNormalizedScores}
          Scores will be auto-calculated. Enter the raw scores (items correct) in your spreadsheet; the system will automatically compute normalized scores or look up percentile grades.
        {:else}
          Scores must be pre-converted. Enter the final normalized scores or percentile grade strings directly in your spreadsheet. No automated calculations will be applied.
        {/if}
      </GuideNote>

      <GuideNote variant="tip" title="Tips">
        <ul class="list-disc pl-4 space-y-1 text-xs text-muted-foreground">
          <li>First row must contain column headers — spaces are fine (e.g. "applicant number" works)</li>
          <li>Supports CSV, XLSX, and XLS files up to 10MB</li>
          <li>Each applicant must have a completed exam session with an open grading session</li>
          <li>Duplicate scores for the same aptitude area in the same academic year will be rejected</li>
          <li>Unrecognized columns are safely ignored</li>
        </ul>
      </GuideNote>
    </GuidePanel>

    <form onsubmit={submitPreview} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label for="file" class="text-sm font-medium leading-none">Spreadsheet File</label>
          <a
            href="/admin/grading/import/template"
            class="text-xs text-primary hover:underline inline-flex items-center gap-1"
          >
            <Download class="size-3" />
            Download Template
          </a>
        </div>
        <FileUpload
          label="Upload file"
          accept=".csv,.xlsx,.xls,.txt"
          maxSize="10MB"
          bind:files={selectedFile}
        />
        <p class="text-xs text-muted-foreground">Supports CSV, XLSX, XLS. First row is used as headers.</p>
        {#if $form.errors?.file}
          <p class="text-sm text-destructive">{$form.errors.file}</p>
        {/if}
      </div>

      <!-- File Analysis -->
      <ImportFileAnalyzer
        file={selectedFile}
        analyzeUrl="/admin/grading/import/analyze"
        onanalysis={onAnalysis}
      />

      <div class="flex justify-end gap-3 pt-2">
        <Button
          type="submit"
          disabled={$form.processing || !fileReady}
          class="min-h-[44px]"
        >
          {#if $form.processing}
            <Upload class="mr-2 size-4 animate-spin" />
            Processing...
          {:else}
            <ArrowRight class="mr-2 size-4" />
            Preview & Import
          {/if}
        </Button>
        <Link href="/admin/grading">
          <Button type="button" variant="outline" class="min-h-[44px]">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>