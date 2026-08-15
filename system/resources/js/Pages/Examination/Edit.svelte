<script>
  /**
   * Edit draft exam session — legacy Admin/TestScheduling/Edit shape (separate page).
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Link, useForm } from '@inertiajs/svelte';

  let { session, cycles = [], rooms = [] } = $props();

  const form = useForm({
    academic_cycle_id: String(session.academic_cycle_id ?? ''),
    room_id: String(session.room_id ?? ''),
    code: session.code ?? '',
    date: session.date ?? '',
    start_time: session.start_time ?? '',
    end_time: session.end_time ?? '',
    expected_version: session.version ?? 1,
  });

  function submit(e) {
    e.preventDefault();
    form.put(`/exams/sessions/${session.id}`);
  }

  const breadcrumbs = [
    { label: 'Exam Scheduling', href: '/exams/sessions' },
    { label: session.code ?? 'Session', href: `/exams/sessions/${session.id}` },
    { label: 'Edit' },
  ];
</script>

<svelte:head><title>Edit exam session</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Edit exam session"
      description="Update draft schedule fields. Assign and publish from the session page."
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
          {#each cycles as cycle}
            <option value={String(cycle.id)}>{cycle.label}</option>
          {/each}
        </select>
      </div>

      <div class="space-y-1.5">
        <Label for="room_id">Room</Label>
        <select
          id="room_id"
          class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
          bind:value={form.room_id}
          required
        >
          {#each rooms as room}
            <option value={String(room.id)}>{room.label}</option>
          {/each}
        </select>
      </div>

      <div class="space-y-1.5">
        <Label for="code">Session code</Label>
        <Input id="code" class="min-h-10" bind:value={form.code} required />
        {#if form.errors.code}
          <p class="text-sm text-destructive">{form.errors.code}</p>
        {/if}
      </div>

      <div class="space-y-1.5">
        <Label for="date">Date</Label>
        <Input id="date" type="date" class="min-h-10" bind:value={form.date} required />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-1.5">
          <Label for="start_time">Start time</Label>
          <Input id="start_time" type="time" class="min-h-10" bind:value={form.start_time} required />
        </div>
        <div class="space-y-1.5">
          <Label for="end_time">End time</Label>
          <Input id="end_time" type="time" class="min-h-10" bind:value={form.end_time} required />
        </div>
      </div>

      {#if form.errors.schedule}
        <p class="text-sm text-destructive">{form.errors.schedule}</p>
      {/if}

      <div class="flex gap-2 pt-2">
        <Button type="submit" class="min-h-10" disabled={form.processing}>
          {form.processing ? 'Saving...' : 'Save'}
        </Button>
        <Link href={`/exams/sessions/${session.id}`}>
          <Button type="button" variant="outline" class="min-h-10">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</StaffLayout>
