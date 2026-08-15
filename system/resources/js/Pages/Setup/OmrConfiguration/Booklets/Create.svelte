<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import { router } from '@inertiajs/svelte';
  import { success as toastSuccess, error as toastError } from '@/lib/toast.js';

  let { draft = null, areas = [], capabilities = {}, errors = {} } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: 'Author booklet' },
  ];

  let code = $state(draft?.code ?? '');
  let name = $state(draft?.name ?? '');
  let questions = $state(
    (draft?.questions ?? []).map((q) => ({
      area_id: q.area_id,
      correct_option_code: q.correct_option_code ?? 'A',
    })),
  );
  let bulk = $state('');
  let saving = $state(false);

  const canSubmit = $derived(
    Boolean(code.trim()) &&
      Boolean(name.trim()) &&
      (questions.length > 0 || bulk.trim() !== '') &&
      questions.every((q) => q.area_id),
  );

  // Toast server-side OMR errors once each (fires on mount for the initial props).
  let toastedErrors = new Set();
  $effect(() => {
    for (const [key, message] of Object.entries(errors)) {
      if (key.startsWith('omr_') && message && !toastedErrors.has(key)) {
        toastedErrors.add(key);
        toastError(message);
      }
    }
  });

  function addQuestion() {
    questions = [...questions, { area_id: areas[0]?.id ?? null, correct_option_code: 'A' }];
  }

  function removeQuestion(index) {
    questions = questions.filter((_, i) => i !== index);
  }

  function submit() {
    if (!canSubmit || saving) return;
    saving = true;
    const bulkText = bulk.trim();
    router.post(
      '/setup/omr/booklets',
      {
        code: code.trim(),
        name: name.trim(),
        questions:
          bulkText === ''
            ? questions.map((q, i) => ({
                area_id: q.area_id,
                position: i + 1,
                correct_option_code: q.correct_option_code,
              }))
            : [],
        bulk: bulkText,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          toastSuccess(`Answer booklet ${code.trim()} saved.`);
          router.visit('/setup/omr/booklets');
        },
        onError: (errs) => {
          toastError(errs.omr_booklet ?? Object.values(errs)[0] ?? 'The answer booklet could not be saved.');
        },
        onFinish: () => {
          saving = false;
        },
      },
    );
  }

  const selectClass =
    'h-8 w-full rounded-md border border-input bg-transparent px-2.5 py-1 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<StaffLayout {breadcrumbs}>
  <PageHeaderBar
    title="Author booklet"
    description="Define the code, name, and answer key. Questions are numbered automatically from 1, in row order."
  />

  <section class="space-y-6 p-6">
    {#if errors.omr_booklet}
      <p class="rounded bg-red-50 p-3 text-sm text-red-800">{errors.omr_booklet}</p>
    {/if}

    <div class="max-w-4xl rounded-lg border p-4">
      <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <Label for="booklet_code">Code</Label>
          <Input
            id="booklet_code"
            bind:value={code}
            placeholder="e.g. BKLT-2026-01"
            class="uppercase"
            aria-invalid={Boolean(errors.omr_booklet)}
          />
        </div>
        <div class="space-y-2">
          <Label for="booklet_name">Name</Label>
          <Input id="booklet_name" bind:value={name} placeholder="e.g. Entrance Exam Booklet A" />
        </div>
      </div>

      {#if draft}
        <p class="mt-3 text-xs text-muted-foreground">
          Editing a draft copied from an existing version — saving creates a new booklet version.
        </p>
      {/if}

      <div class="mt-5">
        <div class="mb-2 flex items-center justify-between">
          <h3 class="text-sm font-semibold">
            Answer key ({questions.length} question{questions.length === 1 ? '' : 's'})
          </h3>
          <Button size="sm" variant="outline" onclick={addQuestion}>Add question</Button>
        </div>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-muted-foreground">
              <th class="py-1">Position</th>
              <th>Area</th>
              <th>Correct option</th>
              <th class="text-right"></th>
            </tr>
          </thead>
          <tbody>
            {#each questions as question, i (i)}
              <tr class="border-t">
                <td class="w-20 py-1 align-middle font-mono">{i + 1}</td>
                <td class="py-1 pr-2 align-middle">
                  <select
                    class={selectClass}
                    value={question.area_id ?? ''}
                    onchange={(e) => {
                      question.area_id = Number(e.currentTarget.value) || null;
                    }}
                  >
                    <option value="" disabled>Select area…</option>
                    {#each areas as area (area.id)}
                      <option value={area.id}>{area.code} — {area.name}</option>
                    {/each}
                  </select>
                </td>
                <td class="w-32 py-1 align-middle">
                  <select
                    class={selectClass}
                    value={question.correct_option_code}
                    onchange={(e) => {
                      question.correct_option_code = e.currentTarget.value;
                    }}
                  >
                    {#each ['A', 'B', 'C', 'D'] as option (option)}
                      <option value={option}>{option}</option>
                    {/each}
                  </select>
                </td>
                <td class="w-24 py-1 text-right align-middle">
                  <Button size="sm" variant="outline" onclick={() => removeQuestion(i)}>Remove</Button>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
        {#if questions.length === 0}
          <p class="mt-2 text-sm text-muted-foreground">Add questions above or paste a bulk key below.</p>
        {/if}
      </div>

      <div class="mt-5 space-y-2">
        <Label for="booklet_bulk">Or paste the key (position area_code option per line, # comments)</Label>
        <Textarea
          id="booklet_bulk"
          bind:value={bulk}
          rows="6"
          class="font-mono text-xs"
          placeholder={'1 MATH01 A\n2 MATH01 C\n# science section\n10 SCI02 B'}
        />
        <p class="text-xs text-muted-foreground">Bulk rows replace the table.</p>
      </div>

      <div class="mt-5 flex gap-3">
        <Button onclick={submit} disabled={!canSubmit || saving}>
          {saving ? 'Saving…' : 'Save booklet'}
        </Button>
      </div>
    </div>
  </section>
</StaffLayout>
