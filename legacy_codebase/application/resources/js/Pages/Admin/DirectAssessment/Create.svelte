<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { useForm, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import { Label } from '@/Components/ui/label';
  import { Input } from '@/Components/ui/input';
  import { Checkbox } from '@/Components/ui/checkbox';
  import * as Dialog from '@/Components/ui/dialog';

  let { academicYears, applicants, activeAcademicYearId, storeRoute } = $props();

  const page = usePage();
  let showSuccessDialog = $state(false);

  // Show success dialog when redirected back with flash
  $effect(() => {
    if ($page.props.flash?.direct_assessment_created) {
      showSuccessDialog = true;
    }
  });

  const breadcrumbs = [
    { label: 'Exam Scheduling', href: '/admin/exam-scheduling' },
    { label: 'Direct Assessment' },
  ];

  const form = useForm({
    academic_year_id: activeAcademicYearId || '',
    applicant_ids: [],
    label: '',
  });

  function toggleApplicant(id) {
    const ids = $form.applicant_ids;
    const idx = ids.indexOf(id);
    form.update((f) => ({
      ...f,
      applicant_ids: idx >= 0 ? ids.filter((i) => i !== id) : [...ids, id],
    }));
  }

  function submit(e) {
    e.preventDefault();
    $form.post(storeRoute);
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div>
      <h1 class="text-2xl font-semibold">Create Direct Assessment Session</h1>
      <p class="mt-1 text-sm text-muted-foreground">
        Create a grading session for walk-in or offline score entry. No room or time scheduling required.
      </p>
    </div>

    <form onsubmit={submit} class="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Session Details</CardTitle>
          <CardDescription>Select the academic year and an optional label for this session.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
          <div class="space-y-2">
            <label for="academic_year_id" class="text-sm font-medium leading-none">Academic Year</label>
            <select
              id="academic_year_id"
              bind:value={$form.academic_year_id}
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            >
              {#each academicYears as ay}
                <option value={ay.id}>{ay.academic_year} — Semester {ay.semester}</option>
              {/each}
            </select>
            {#if $form.errors.academic_year_id}
              <p class="text-sm text-destructive">{$form.errors.academic_year_id}</p>
            {/if}
          </div>

          <div class="space-y-2">
            <label for="label" class="text-sm font-medium leading-none">Label (optional)</label>
            <Input id="label" bind:value={$form.label} placeholder="e.g. Walk-in Batch 3" />
            {#if $form.errors.label}
              <p class="text-sm text-destructive">{$form.errors.label}</p>
            {/if}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Select Applicants</CardTitle>
          <CardDescription>Choose accepted applicants who are not already in an active grading session.</CardDescription>
        </CardHeader>
        <CardContent>
          {#if applicants.length === 0}
            <p class="text-sm text-muted-foreground">No eligible applicants found.</p>
          {:else}
            <div class="space-y-2 max-h-64 overflow-y-auto">
              {#each applicants as applicant (applicant.id)}
                <label class="flex items-center gap-3 p-2 rounded-md hover:bg-muted/50 cursor-pointer">
                  <Checkbox
                    checked={$form.applicant_ids.includes(applicant.id)}
                    onCheckedChange={() => toggleApplicant(applicant.id)}
                  />
                  <div class="flex-1">
                    <span class="text-sm font-medium">{applicant.name}</span>
                    <span class="text-xs text-muted-foreground ml-2">{applicant.reference}</span>
                  </div>
                </label>
              {/each}
            </div>
          {/if}
          {#if $form.errors.applicant_ids}
            <p class="text-sm text-destructive mt-2">{$form.errors.applicant_ids}</p>
          {/if}
        </CardContent>
      </Card>

      <div class="flex items-center justify-end gap-3">
        <Button type="button" variant="outline" onclick={() => window.history.back()}>Cancel</Button>
        <Button type="submit" disabled={$form.processing || $form.applicant_ids.length === 0}>
          {$form.processing ? 'Creating...' : 'Create Session'}
        </Button>
      </div>
    </form>
  </div>

  <Dialog.Root bind:open={showSuccessDialog}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30 mb-3">
          <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>
        <Dialog.Title class="text-center">Direct Assessment Created</Dialog.Title>
        <Dialog.Description class="text-center">
          The applicants have been registered for direct assessment and are ready for score encoding. Please contact a <strong>Test Administrator</strong> to begin grading.
        </Dialog.Description>
      </Dialog.Header>
      <Dialog.Footer class="sm:justify-center gap-2 mt-2">
        <Button variant="outline" onclick={() => { showSuccessDialog = false; }}>
          Stay Here
        </Button>
        <Button onclick={() => { window.location.href = '/admin/exam-scheduling'; }}>
          Go to Exam Scheduling
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</AuthenticatedLayout>
