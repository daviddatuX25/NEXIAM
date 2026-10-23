<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import InfoPopover from '@/Components/kit/InfoPopover.svelte';
  import { Link, useForm } from '@inertiajs/svelte';

  let { cycle } = $props();

  const form = useForm({
    school_year: cycle.school_year ?? '',
    semester: String(cycle.semester ?? '1'),
    application_start_date: cycle.application_start_date ?? '',
    application_end_date: cycle.application_end_date ?? '',
  });

  function submit(e) {
    e.preventDefault();
    form.put(`/setup/cycles/${cycle.id}`);
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Academic Years', href: '/setup/cycles' },
    { label: 'Edit' },
  ];
</script>

<svelte:head><title>Edit Academic Year · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Edit Academic Year"
      description="Update the school year, semester, and application window dates."
    />

    <form onsubmit={submit} class="space-y-6">
      <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] max-w-6xl items-start">
        <div class="space-y-4 rounded-xl border border-border bg-card p-6 shadow-xs">
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
        <div class="flex items-center gap-1.5">
          <Label for="application_start_date">Application window start</Label>
          <InfoPopover
            title="Public intake window"
            content="Sets the opening and closing dates for the cycle's application window on public /apply."
          />
        </div>
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
              {form.processing ? 'Saving...' : 'Save'}
            </Button>
            <Link href="/setup/cycles">
              <Button type="button" variant="outline" class="min-h-10">Cancel</Button>
            </Link>
          </div>
        </div>

        <div class="space-y-5">
          {#if cycle.is_active}
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-5 text-emerald-950 dark:text-emerald-100 shadow-xs">
              <div class="flex items-center gap-2 font-semibold text-sm text-emerald-800 dark:text-emerald-300">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                Active Academic Year
              </div>
              <p class="mt-2 text-xs leading-relaxed text-emerald-800/80 dark:text-emerald-300/80">
                This academic year is currently active and drives live applicant registrations, examination sessions, and scoring workflows. Activate a different year from the list to change it.
              </p>
            </div>
          {/if}

          <div class="rounded-xl border border-border bg-card p-6 shadow-xs space-y-4">
            <div class="space-y-1 border-b border-border pb-3">
              <h2 class="text-sm font-semibold text-foreground">Email &amp; Notifications</h2>
              <p class="text-xs text-muted-foreground">System delivery channels, office notice policies, and recent dispatch activity.</p>
            </div>
            <p class="text-xs text-muted-foreground leading-relaxed">
              Opens institution-wide settings for the active academic year, not historical-year policy.
            </p>
            <Link href="/setup/notifications" class="block pt-1">
              <Button type="button" variant="outline" class="w-full min-h-10">Manage Email &amp; Notifications</Button>
            </Link>
          </div>
        </div>
      </div>
    </form>
  </div>
</StaffLayout>
