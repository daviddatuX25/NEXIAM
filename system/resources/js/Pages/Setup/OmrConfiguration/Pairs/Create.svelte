<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Label } from '@/Components/ui/label';
  import { Link, router } from '@inertiajs/svelte';
  import { error as toastError, success as toastSuccess } from '@/lib/toast.js';

  let { draft_templates = [], draft_booklets = [], errors = {} } = $props();

  let templateVersionId = $state(draft_templates[0]?.id ?? null);
  let bookletVersionId = $state(draft_booklets[0]?.id ?? null);
  let saving = $state(false);
  let toastedError = false;

  const selectedTemplate = $derived(
    draft_templates.find((template) => template.id === templateVersionId) ?? null,
  );
  const selectedBooklet = $derived(
    draft_booklets.find((booklet) => booklet.id === bookletVersionId) ?? null,
  );

  const templateSummary = $derived(
    selectedTemplate
      ? `${selectedTemplate.page_count} pages · ${selectedTemplate.page_width}×${selectedTemplate.page_height} px`
      : '',
  );
  const bookletSummary = $derived(
    selectedBooklet ? `${selectedBooklet.questions_count} questions` : '',
  );

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: 'Activate pair' },
  ];

  // A failed activation arrives as redirected props; surface it once per page load.
  $effect(() => {
    if (errors.omr_pair && !toastedError) {
      toastError(errors.omr_pair);
      toastedError = true;
    }
  });

  function submit() {
    if (!templateVersionId || !bookletVersionId || saving) return;
    saving = true;
    router.post(
      '/setup/omr/pairs',
      {
        template_version_id: templateVersionId,
        booklet_version_id: bookletVersionId,
      },
      {
        onSuccess: () => {
          toastSuccess('Pair activated.');
          router.visit('/setup/omr');
        },
        onError: () => {
          saving = false;
        },
        onFinish: () => {
          saving = false;
        },
      },
    );
  }
</script>

<StaffLayout {breadcrumbs}>
  <PageHeaderBar
    title="Activate template–booklet pair"
    description="Pair activation is the only state flip — draft template × draft booklet, atomic."
    {breadcrumbs}
  />

  <section class="max-w-2xl space-y-6 p-6">
    {#if errors.omr_pair}
      <div class="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        {errors.omr_pair}
      </div>
    {/if}

    {#if draft_templates.length === 0 || draft_booklets.length === 0}
      <div class="rounded-lg border p-4">
        <h2 class="text-lg font-semibold">Nothing to pair yet</h2>
        <p class="mt-2 text-sm text-muted-foreground">
          {#if draft_templates.length === 0}
            No draft template versions are available.
            <Link href="/setup/omr/templates/canonical" class="font-medium text-primary underline underline-offset-4">Open the template builder</Link>
            to create one.
          {/if}
          {#if draft_booklets.length === 0}
            No draft booklet versions are available.
            <Link href="/setup/omr/booklets" class="font-medium text-primary underline underline-offset-4">Author booklets</Link>
            to create one.
          {/if}
        </p>
      </div>
    {:else}
      <div class="space-y-5 rounded-lg border p-4">
        <div class="space-y-2">
          <Label for="template_version_id">Template</Label>
          <select
            id="template_version_id"
            class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
            bind:value={templateVersionId}
          >
            {#each draft_templates as template (template.id)}
              <option value={template.id}>{template.label} ({template.code})</option>
            {/each}
          </select>
          <p class="text-xs text-muted-foreground">{templateSummary}</p>
        </div>

        <div class="space-y-2">
          <Label for="booklet_version_id">Booklet</Label>
          <select
            id="booklet_version_id"
            class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
            bind:value={bookletVersionId}
          >
            {#each draft_booklets as booklet (booklet.id)}
              <option value={booklet.id}>{booklet.label} ({booklet.code})</option>
            {/each}
          </select>
          <p class="text-xs text-muted-foreground">{bookletSummary}</p>
        </div>

        <div class="flex gap-3">
          <Button onclick={submit} disabled={!templateVersionId || !bookletVersionId || saving}>
            {saving ? 'Activating…' : 'Activate pair'}
          </Button>
          <Button asChild variant="outline">
            <Link href="/setup/omr">Cancel</Link>
          </Button>
        </div>
      </div>
    {/if}
  </section>
</StaffLayout>
