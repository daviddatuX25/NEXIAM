<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm, page } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { FileSpreadsheet, Check, X, AlertTriangle, Save, ChevronDown, ChevronUp } from 'lucide-svelte';

  let {
    records = [],
    totalCount = 0,
    validCount = 0,
    academicYearId = 0,
    academicYears = [],
  } = $props();

  const breadcrumbs = [
    { label: 'Applications', href: '/admin/applications' },
    { label: 'Import', href: '/admin/applications/import' },
    { label: 'Preview' },
  ];

  const form = useForm({
    selected_ids: [],
    select_all: true,
  });

  let selectAll = $state(true);
  let selectedIds = $state(new Set());
  let showErrorDetails = $state(new Set());
  let showOnlyInvalid = $state(false);

  // Initialize with all valid records selected
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
    selectAll = false;
  }

  function toggleAll() {
    if (selectAll) {
      selectedIds = new Set();
      selectAll = false;
    } else {
      selectedIds = new Set(records.filter(r => r.is_valid).map(r => r.id));
      selectAll = true;
    }
  }

  function toggleErrorDetail(id) {
    const newSet = new Set(showErrorDetails);
    if (newSet.has(id)) {
      newSet.delete(id);
    } else {
      newSet.add(id);
    }
    showErrorDetails = newSet;
  }

  function submitForm(e) {
    e.preventDefault();
    $form.transform((data) => ({
      ...data,
      selected_ids: Array.from(selectedIds),
    }));
    $form.post('/admin/applications/import/confirm', { forceFormData: true });
  }

  let message = $state('');
  let error = $state('');

  $effect(() => {
    message = $page.props.flash?.message || '';
    error = $page.props.flash?.error || '';
  });

  const invalidCount = $derived(totalCount - validCount);

  // Filtered records for display
  let displayRecords = $derived(
    showOnlyInvalid ? records.filter(r => !r.is_valid) : records
  );

  // Paginate: show max 100 at a time
  let displayLimit = $state(100);
  let visibleRecords = $derived(displayRecords.slice(0, displayLimit));

  // Find the academic year label
  let academicYearLabel = $derived(
    academicYears.find(y => y.id === academicYearId)
  );
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6">

    <div>
      <h1 class="text-2xl font-semibold">Preview Import Data</h1>
      <p class="text-sm text-muted-foreground mt-1">
        Review parsed data before importing. Select records to import or deselect those you want to skip.
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

    <!-- Summary -->
    <div class="flex flex-wrap gap-3 text-sm">
      {#if academicYearLabel}
        <div class="rounded-md bg-muted px-3 py-2 flex items-center gap-2">
          <FileSpreadsheet class="size-4 text-primary" />
          <span class="font-medium">{academicYearLabel.academic_year} — Sem {academicYearLabel.semester}</span>
        </div>
      {/if}
      <div class="rounded-md bg-muted px-3 py-2">
        <span class="font-medium">{totalCount}</span> total rows
      </div>
      <div class="rounded-md bg-success/10 px-3 py-2 text-success font-semibold">
        <Check class="inline size-4 mr-1" />
        <span class="font-medium">{validCount}</span> valid
      </div>
      {#if invalidCount > 0}
        <div class="rounded-md bg-red-50 dark:bg-red-950/30 px-3 py-2 text-red-700 dark:text-red-400">
          <X class="inline size-4 mr-1" />
          <span class="font-medium">{invalidCount}</span> invalid
        </div>
      {/if}
      <div class="rounded-md bg-primary/10 px-3 py-2 text-primary">
        <span class="font-medium">{selectedIds.size}</span> selected to import
      </div>
    </div>

    <!-- Filter toggle -->
    {#if invalidCount > 0}
      <div class="flex items-center gap-2">
        <label class="relative inline-flex items-center cursor-pointer">
          <input
            type="checkbox"
            bind:checked={showOnlyInvalid}
            class="sr-only peer"
          />
          <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-ring rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-600 peer-checked:bg-red-500"></div>
          <span class="ms-2 text-sm text-muted-foreground">Show only invalid rows</span>
        </label>
      </div>
    {/if}

    <form onsubmit={submitForm} class="space-y-4">
      <!-- Table -->
      <div class="rounded-lg border border-border overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-muted sticky top-0">
              <tr>
                <th class="px-3 py-2 text-left w-10">
                  <input
                    type="checkbox"
                    checked={selectAll}
                    onchange={toggleAll}
                    class="rounded"
                  />
                </th>
                <th class="px-3 py-2 text-left w-14 text-xs font-medium text-muted-foreground">Row</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-muted-foreground">First Name</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-muted-foreground">Last Name</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-muted-foreground">Email</th>
                <th class="px-3 py-2 text-left text-xs font-medium text-muted-foreground min-w-[120px]">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              {#each visibleRecords as record}
                <tr
                  class="transition-colors {record.is_valid ? 'hover:bg-muted/30' : 'bg-red-50/50 dark:bg-red-950/20 hover:bg-red-50 dark:hover:bg-red-950/30'}"
                >
                  <td class="px-3 py-2">
                    {#if record.is_valid}
                      <input
                        type="checkbox"
                        checked={selectedIds.has(record.id)}
                        onchange={() => toggleRow(record.id)}
                        class="rounded"
                      />
                    {:else}
                      <X class="size-4 text-red-400" />
                    {/if}
                  </td>
                  <td class="px-3 py-2 text-muted-foreground text-xs tabular-nums">{record.row}</td>
                  <td class="px-3 py-2 font-medium">{record.data.first_name || '—'}</td>
                  <td class="px-3 py-2">{record.data.last_name || '—'}</td>
                  <td class="px-3 py-2 text-muted-foreground">{record.data.email || '—'}</td>
                  <td class="px-3 py-2">
                    {#if record.is_valid}
                      <span class="inline-flex items-center rounded-full bg-success/15 px-2 py-0.5 text-xs font-semibold text-success">
                        <Check class="size-3 mr-1" /> Valid
                      </span>
                    {:else}
                      <button
                        type="button"
                        onclick={() => toggleErrorDetail(record.id)}
                        class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900/30 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-400 hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors cursor-pointer"
                      >
                        <AlertTriangle class="size-3 mr-1" />
                        {record.errors.length} error{record.errors.length !== 1 ? 's' : ''}
                        {#if showErrorDetails.has(record.id)}
                          <ChevronUp class="size-3 ml-1" />
                        {:else}
                          <ChevronDown class="size-3 ml-1" />
                        {/if}
                      </button>
                    {/if}
                  </td>
                </tr>
                {#if !record.is_valid && showErrorDetails.has(record.id)}
                  <tr class="bg-red-50/30 dark:bg-red-950/10">
                    <td colspan="6" class="px-6 py-2">
                      <ul class="list-disc pl-4 space-y-0.5 text-xs text-red-600 dark:text-red-400">
                        {#each record.errors as err}
                          <li>{err}</li>
                        {/each}
                      </ul>
                    </td>
                  </tr>
                {/if}
              {/each}
            </tbody>
          </table>
        </div>

        {#if displayRecords.length > displayLimit}
          <div class="px-3 py-2 text-center bg-muted/50 border-t border-border">
            <button
              type="button"
              onclick={() => displayLimit += 100}
              class="text-sm text-primary hover:underline"
            >
              Show more ({displayRecords.length - displayLimit} remaining)
            </button>
          </div>
        {/if}

        {#if displayRecords.length === 0}
          <div class="px-3 py-8 text-center text-muted-foreground text-sm">
            {showOnlyInvalid ? 'No invalid rows found!' : 'No data rows found.'}
          </div>
        {/if}
      </div>

      <div class="flex justify-end items-center gap-3">
        <Button type="submit" disabled={$form.processing || selectedIds.size === 0} class="min-h-[44px]">
          <Save class="mr-2 size-4" />
          {$form.processing ? 'Importing...' : `Import ${selectedIds.size} Selected`}
        </Button>
        <Link href="/admin/applications/import">
          <Button type="button" variant="outline" class="min-h-[44px]">Cancel</Button>
        </Link>

        {#if selectedIds.size > 0 && invalidCount > 0}
          <p class="text-xs text-muted-foreground order-first">
            {invalidCount} invalid row{invalidCount !== 1 ? 's' : ''} will be skipped
          </p>
        {/if}
      </div>
    </form>
  </div>
</AuthenticatedLayout>