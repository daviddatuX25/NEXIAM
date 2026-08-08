<script>
  /**
   * Create exam session — legacy Admin/TestScheduling/Create shape (separate page).
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Link, useForm } from '@inertiajs/svelte';

  let { cycles = [], rooms = [], default_cycle_id = null } = $props();

  const form = useForm({
    academic_cycle_id: default_cycle_id ? String(default_cycle_id) : '',
    room_id: '',
    code: '',
    date: '',
    start_time: '',
    end_time: '',
  });

  function submit(e) {
    e.preventDefault();
    form.post('/exams/sessions');
  }

  const breadcrumbs = [
    { label: 'Exams', href: '/exams/sessions' },
    { label: 'Create' },
  ];
</script>

<svelte:head><title>Create exam session</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Create exam session"
      description="Sessions start staff-only. Open reservation from Show after create if applicants should self-book."
    />

    <form class="max-w-lg space-y-4 rounded-xl border border-border bg-card p-6" onsubmit={submit}>
      <div class="space-y-1.5">
        <Label for="academic_cycle_id">Academic year</Label>
        <select
          id="academic_cycle_id"
          class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
          bind:value={form.academic_cycle_id}
          required
        >
          <option value="">Select academic year</option>
          {#each cycles as cycle}
            <option value={String(cycle.id)}>{cycle.label}{cycle.is_active ? ' (Active)' : ''}</option>
          {/each}
        </select>
        {#if form.errors.academic_cycle_id}
          <p class="text-sm text-destructive">{form.errors.academic_cycle_id}</p>
        {/if}
      </div>

      <div class="space-y-1.5">
        <Label for="room_id">Room</Label>
        <select
          id="room_id"
          class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
          bind:value={form.room_id}
          required
        >
          <option value="">Select room</option>
          {#each rooms as room}
            <option value={String(room.id)}>{room.label}</option>
          {/each}
        </select>
        {#if form.errors.room_id}
          <p class="text-sm text-destructive">{form.errors.room_id}</p>
        {/if}
      </div>

      <div class="space-y-1.5">
        <Label for="code">Session code</Label>
        <Input id="code" class="min-h-10" bind:value={form.code} placeholder="e.g. EX-2026-FRI-A" required />
        {#if form.errors.code}
          <p class="text-sm text-destructive">{form.errors.code}</p>
        {/if}
      </div>

      <div class="space-y-1.5">
        <Label for="date">Date</Label>
        <Input id="date" type="date" class="min-h-10" bind:value={form.date} required />
        {#if form.errors.starts_at}
          <p class="text-sm text-destructive">{form.errors.starts_at}</p>
        {/if}
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <Label for="start_time">Start time</Label>
          <Input id="start_time" type="time" class="min-h-10" bind:value={form.start_time} required />
        </div>
        <div class="space-y-1.5">
          <Label for="end_time">End time</Label>
          <Input id="end_time" type="time" class="min-h-10" bind:value={form.end_time} required />
          {#if form.errors.effective_end_at}
            <p class="text-sm text-destructive">{form.errors.effective_end_at}</p>
          {/if}
        </div>
      </div>

      {#if form.errors.schedule}
        <p class="text-sm text-destructive">{form.errors.schedule}</p>
      {/if}

      <div class="flex gap-2 pt-2">
        <Button type="submit" class="min-h-10" disabled={form.processing}>
          {form.processing ? 'Creating...' : 'Create session'}
        </Button>
        <Link href="/exams/sessions">
          <Button type="button" variant="outline" class="min-h-10">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</StaffLayout>
