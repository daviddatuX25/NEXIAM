<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Link, useForm } from '@inertiajs/svelte';

  const form = useForm({
    school_year: '',
    semester: '1',
    application_start_date: '',
    application_end_date: '',
  });

  function submit(e) {
    e.preventDefault();
    form.post('/setup/cycles');
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Academic years', href: '/setup/cycles' },
    { label: 'Create' },
  ];
</script>

<svelte:head><title>Create academic year · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Add academic year"
      description="Create a school year and semester. Set the application window dates, then activate it from the list."
    />

    <form
      class="max-w-lg space-y-4 rounded-xl border border-border bg-card p-6"
      onsubmit={submit}
    >
      <div class="space-y-1.5">
        <Label for="school_year">Academic year</Label>
        <Input
          id="school_year"
          bind:value={form.school_year}
          placeholder="e.g. 2025-2026"
          class="min-h-10"
          maxlength={20}
          required
        />
        {#if form.errors.school_year}
          <p class="text-sm text-destructive">{form.errors.school_year}</p>
        {/if}
      </div>

      <div class="space-y-1.5">
        <Label for="semester">Semester</Label>
        <select
          id="semester"
          bind:value={form.semester}
          class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
          required
        >
          <option value="1">1st Semester</option>
          <option value="2">2nd Semester</option>
        </select>
      </div>

      <div class="space-y-1.5">
        <Label for="application_start_date">Application window start</Label>
        <Input
          id="application_start_date"
          type="date"
          bind:value={form.application_start_date}
          class="min-h-10"
        />
      </div>

      <div class="space-y-1.5">
        <Label for="application_end_date">Application window end</Label>
        <Input
          id="application_end_date"
          type="date"
          bind:value={form.application_end_date}
          class="min-h-10"
        />
      </div>

      <div class="flex gap-2 pt-2">
        <Button type="submit" class="min-h-10" disabled={form.processing}>
          {form.processing ? 'Creating...' : 'Create academic year'}
        </Button>
        <Link href="/setup/cycles">
          <Button type="button" variant="outline" class="min-h-10">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</StaffLayout>
