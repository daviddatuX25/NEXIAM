<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm, page } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { FileUpload } from '@/Components/ui/file-upload';
  import { Upload, Download, ArrowRight } from 'lucide-svelte';
  import { GuidePanel, GuideSection, CopyableGroup, GuideNote } from '@/Components/Guide';
  import ImportFileAnalyzer from '@/Components/ImportFileAnalyzer.svelte';

  let {
    academicYears = [],
    courses = [],
    requiredColumns = ['first_name', 'last_name', 'email'],
    optionalColumns = [],
  } = $props();

  const breadcrumbs = [
    { label: 'Applications', href: '/admin/applications' },
    { label: 'Import' },
  ];

  const columnLabels = {
    first_name: 'first_name',
    last_name: 'last_name',
    email: 'email',
    applicant_number: 'applicant_number',
    middle_name: 'middle_name',
    suffix: 'suffix',
    birthdate: 'birthdate',
    sex: 'sex',
    phone: 'phone',
    address_line: 'address_line',
    city: 'city',
    province: 'province',
    zip_code: 'zip_code',
    course_preference_1: 'course_preference_1',
    course_preference_2: 'course_preference_2',
    course_preference_3: 'course_preference_3',
    gwa: 'gwa',
  };

  const requiredItems = $derived(requiredColumns.map((c) => ({ value: c, label: columnLabels[c] ?? c })));
  const optionalItems = $derived(optionalColumns.map((c) => ({ value: c, label: columnLabels[c] ?? c })));
  const courseItems = $derived(
    courses.map((c) => ({ value: c.code, label: `${c.code} — ${c.name}` })),
  );

  const form = useForm({
    file: null,
    academic_year_id: '',
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

  function submitForm(e) {
    e.preventDefault();
    if (!selectedFile || !fileReady) return;

    const actualFile = Array.isArray(selectedFile) ? selectedFile[0] : selectedFile;
    if (!actualFile) return;

    $form.file = actualFile;
    $form.post('/admin/applications/import/preview', { forceFormData: true });
  }

  let message = $state('');
  let error = $state('');

  $effect(() => {
    message = $page.props.flash?.message || '';
    error = $page.props.flash?.error || '';
  });
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-3xl space-y-6">

    <div>
      <h1 class="text-2xl font-semibold">Bulk Import Applicants</h1>
      <p class="text-sm text-muted-foreground mt-1">
        Import applicant records via spreadsheet upload. Headers are auto-matched — spaces, dashes, and capitalization are handled automatically.
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
      <GuideSection title="Required Columns">
        <CopyableGroup items={requiredItems} />
      </GuideSection>

      <GuideSection title="Optional Columns">
        <CopyableGroup items={optionalItems} />
      </GuideSection>

      {#if courseItems.length > 0}
        <GuideSection title="Course Codes">
          <CopyableGroup items={courseItems} subtitle="Use these codes in course_preference columns" />
        </GuideSection>
      {/if}

      <GuideNote variant="tip" title="Tips">
        <ul class="list-disc pl-4 space-y-1 text-xs text-muted-foreground">
          <li>First row must contain column headers — spaces, dashes, and capitalization are handled automatically</li>
          <li>Supports CSV, XLSX, and XLS files up to 10MB</li>
          <li>Email addresses must be unique per academic year</li>
          <li>Course preferences accept course codes (e.g. BSCS) or numeric IDs</li>
          <li>Unrecognized columns are safely ignored (e.g. "Notes" is skipped)</li>
          <li><strong>Alternate column names are auto-detected</strong> — e.g. "Family Name" → last name, "Middle Initial" / "MI" → middle name, "Course Applied" → 1st preference, "Strand/Prev Course" → strand, "Gender" → sex</li>
          <li>Sex accepts: <code>male</code>, <code>female</code>, <code>m</code>, <code>f</code></li>
          <li>Birthdate, Sex, GWA, and Course preference are all optional — missing values are safely skipped</li>
        </ul>
      </GuideNote>
    </GuidePanel>

    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="academic_year" class="text-sm font-medium leading-none">Academic Year</label>
        <select
          id="academic_year"
          bind:value={$form.academic_year_id}
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          required
        >
          <option value="">Select academic year...</option>
          {#each academicYears as year}
            <option value={year.id}>{year.academic_year} — Semester {year.semester}</option>
          {/each}
        </select>
        {#if $form.errors?.academic_year_id}
          <p class="text-sm text-destructive">{$form.errors.academic_year_id}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label for="file" class="text-sm font-medium leading-none">Spreadsheet File</label>
          <a
            href="/admin/applications/import/template"
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
        analyzeUrl="/admin/applications/import/analyze"
        onanalysis={onAnalysis}
      />

      <div class="flex justify-end gap-3 pt-2">
        <Button
          type="submit"
          disabled={$form.processing || !fileReady || !$form.academic_year_id}
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
        <Link href="/admin/applications">
          <Button type="button" variant="outline" class="min-h-[44px]">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>