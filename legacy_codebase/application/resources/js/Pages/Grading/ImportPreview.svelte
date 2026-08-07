<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm, page } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Checkbox } from '@/Components/ui/checkbox';
  import { FileSpreadsheet, Check, X, AlertTriangle, Save } from 'lucide-svelte';

  let {
    records = [],
    totalCount = 0,
    validCount = 0,
    enableNormalizedScores = false,
    aptitudeAreaCodes = [],
    confirmUrl = '/admin/grading/import/confirm',
  } = $props();

  const breadcrumbs = [
    { label: 'Grading', href: '/admin/grading' },
    { label: 'Import Scores', href: '/admin/grading/import' },
    { label: 'Preview' },
  ];

  const form = useForm({
    selected_ids: [],
  });

  let selectAll = $state(true);
  let selectedIds = $state(new Set());

  $effect(() => {
    if (selectAll) {
      selectedIds = new Set(records.filter(r => r.is_valid).map(r => r.id));
    }
  });

  function toggleRow(id) {
    const newSet = new Set(selectedIds);
    if (newSet.has(id)) {
      newSet.delete(id);
    } else {
      newSet.add(id);
    }
    selectedIds = newSet;
  }

  function toggleAll(v) {
    if (!v) {
      selectedIds = new Set();
      selectAll = false;
    } else {
      selectedIds = new Set(records.filter(r => r.is_valid).map(r => r.id));
      selectAll = true;
    }
  }

  function submitForm(e) {
    e.preventDefault();
    $form.transform((data) => ({
      ...data,
      selected_ids: Array.from(selectedIds),
    }));
    $form.post(confirmUrl, { forceFormData: true });
  }

  let message = $state('');
  let error = $state('');

  $effect(() => {
    message = $page.props.flash?.message || '';
    error = $page.props.flash?.error || '';
  });

  const invalidCount = totalCount - validCount;
  const scoreSuffix = enableNormalizedScores ? '(raw)' : '(normalized)';
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">Preview Score Import</h1>
      <p class="text-sm text-muted-foreground mt-1">
        Review parsed data before importing. Invalid rows are highlighted.
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

    <div class="flex gap-4 text-sm">
      <div class="rounded bg-muted px-3 py-2">
        <span class="font-medium">{totalCount}</span> total rows
      </div>
      <div class="rounded bg-success/10 px-3 py-2 text-success font-semibold">
        <Check class="inline size-4 mr-1" />
        <span class="font-medium">{validCount}</span> valid
      </div>
      <div class="rounded bg-red-50 dark:bg-red-950/30 px-3 py-2 text-red-700 dark:text-red-400">
        <X class="inline size-4 mr-1" />
        <span class="font-medium">{invalidCount}</span> invalid
      </div>
    </div>

    <form onsubmit={submitForm} class="space-y-4">
      <div class="rounded-lg border border-border overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[700px]">
          <thead class="bg-muted">
            <tr>
              <th class="px-3 py-2 text-left w-10">
                <Checkbox checked={selectAll} onCheckedChange={toggleAll} />
              </th>
              <th class="px-3 py-2 text-left w-16">Row</th>
              <th class="px-3 py-2 text-left">Applicant #</th>
              <th class="px-3 py-2 text-left">Applicant</th>
              <th class="px-3 py-2 text-left">Session</th>
              {#each aptitudeAreaCodes as code}
                <th class="px-3 py-2 text-left">{code} <span class="text-muted-foreground font-normal">{scoreSuffix}</span></th>
              {/each}
              <th class="px-3 py-2 text-left min-w-[200px]">Status</th>
            </tr>
          </thead>
          <tbody>
            {#each records.slice(0, 50) as record}
              <tr class={!record.is_valid ? 'bg-red-50 dark:bg-red-950/20' : ''}>
                <td class="px-3 py-2">
                  {#if record.is_valid}
                    <Checkbox checked={selectedIds.has(record.id)} onCheckedChange={() => toggleRow(record.id)} />
                  {:else}
                    <X class="size-4 text-red-500" />
                  {/if}
                </td>
                <td class="px-3 py-2 text-muted-foreground">{record.row}</td>
                <td class="px-3 py-2">{record.applicant_number || '—'}</td>
                <td class="px-3 py-2">{record.applicant_name || '—'}</td>
                <td class="px-3 py-2">{record.grading_session_label || '—'}</td>
                {#each aptitudeAreaCodes as code}
                  <td class="px-3 py-2">
                    {record.scores.find(s => s.area_code === code)?.score || '—'}
                  </td>
                {/each}
                <td class="px-3 py-2 min-w-[200px]">
                  {#if record.is_valid}
                    <span class="inline-flex items-center rounded-full bg-success/15 px-2 py-1 text-xs font-semibold text-success whitespace-nowrap">
                      <Check class="size-3 mr-1" /> Valid
                    </span>
                  {:else}
                    <span class="inline-flex items-start rounded-md bg-red-100 dark:bg-red-900/30 px-2 py-1.5 text-xs font-medium text-red-700 dark:text-red-400 max-w-[280px]">
                      <AlertTriangle class="size-3 mr-1 mt-0.5 shrink-0" />
                      <span class="break-words">{record.errors[0]}</span>
                    </span>
                  {/if}
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
        </div>
        {#if records.length > 50}
          <div class="px-3 py-2 text-sm text-muted-foreground bg-muted">
            Showing 50 of {records.length} rows. Invalid rows will be skipped.
          </div>
        {/if}
      </div>

      <div class="flex justify-end gap-3">
        <Button type="submit" disabled={$form.processing || selectedIds.size === 0} class="min-h-[44px]">
          <Save class="mr-2 size-4" />
          {$form.processing ? 'Importing...' : `Import ${selectedIds.size} Selected`}
        </Button>
        <Link href="/admin/grading/import">
          <Button type="button" variant="outline" class="min-h-[44px]">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
