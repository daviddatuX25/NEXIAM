<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import InfoPopover from '@/Components/kit/InfoPopover.svelte';
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
    { label: 'Academic Years', href: '/setup/cycles' },
    { label: 'Create' },
  ];
</script>

<svelte:head><title>Add Academic Year · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Add Academic Year"
      description="Configure a school year, semester, and application window dates, then activate it from the cycle list."
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
            content="Sets the opening and closing dates for the cycle's application window on public /apply. If left blank at creation, the cycle defaults to a 6-month window starting today."
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
              {form.processing ? 'Creating...' : 'Create academic year'}
            </Button>
            <Link href="/setup/cycles">
              <Button type="button" variant="outline" class="min-h-10">Cancel</Button>
            </Link>
          </div>
        </div>

        <div class="space-y-5">
          <div class="rounded-xl border border-border bg-card p-6 shadow-xs space-y-4">
            <div class="space-y-1 border-b border-border pb-3">
              <h2 class="text-sm font-semibold text-foreground">Cycle Lifecycle Overview</h2>
              <p class="text-xs text-muted-foreground">Institutional admission context</p>
            </div>
            <p class="text-xs text-muted-foreground leading-relaxed">
              Creating an academic year establishes the temporal boundary for applicant admissions, exam session scheduling, and final enrollment endorsements.
            </p>
            <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">
              After creation, new cycles remain inactive drafts until explicitly activated from the Academic Years hub. Only one cycle is active across the institution at any given time.
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</StaffLayout>
