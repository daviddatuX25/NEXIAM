<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { success } from '@/lib/toast';

  let { academicYear } = $props();

  const form = useForm({
    academic_year: academicYear.academic_year,
    semester: academicYear.semester,
    application_start_date: academicYear.application_start_date ?? '',
    application_end_date: academicYear.application_end_date ?? '',
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Academic year updated');
    }
  };

  function submitForm(e) {
    e.preventDefault();
    $form.put(`/admin/academic-years/${academicYear.id}`);
  }
  const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Academic Years', href: '/admin/academic-years' }, { label: 'Edit' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="academic_year" class="text-sm font-medium">Academic year</label>
        <Input
          id="academic_year"
          bind:value={$form.academic_year}
          placeholder="e.g., 2025-2026"
          required
          maxlength="20"
        />
        {#if $form.errors?.academic_year}
          <p class="text-sm text-destructive">{$form.errors.academic_year}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="semester" class="text-sm font-medium">Semester</label>
        <Input
          id="semester"
          bind:value={$form.semester}
          placeholder="e.g., 1 or First Semester"
          required
          maxlength="50"
        />
        {#if $form.errors?.semester}
          <p class="text-sm text-destructive">{$form.errors.semester}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="application_start_date" class="text-sm font-medium">Application window start</label>
        <Input
          id="application_start_date"
          type="date"
          bind:value={$form.application_start_date}
        />
        {#if $form.errors?.application_start_date}
          <p class="text-sm text-destructive">{$form.errors.application_start_date}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="application_end_date" class="text-sm font-medium">Application window end</label>
        <Input
          id="application_end_date"
          type="date"
          bind:value={$form.application_end_date}
        />
        {#if $form.errors?.application_end_date}
          <p class="text-sm text-destructive">{$form.errors.application_end_date}</p>
        {/if}
      </div>

      {#if academicYear.is_active}
        <p class="text-sm text-muted-foreground">This academic year is currently active. Use &quot;Set active&quot; on another year from the list to change.</p>
      {/if}

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Saving...' : 'Save'}
        </Button>
        <Link href="/admin/academic-years">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>