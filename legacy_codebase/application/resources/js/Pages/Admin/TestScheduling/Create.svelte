<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { success } from '@/lib/toast';

  let { rooms = [], proctors = [] } = $props();

  let backtrackMode = $state(false);

  const form = useForm({
    room_id: '',
    date: '',
    start_time: '',
    end_time: '',
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Exam session created');
    }
  };

  // Per developing-gotchas: checkbox array state does not sync into useForm; use local state and merge in transform
  let selectedProctorIds = $state([]);

  function toggleProctor(id) {
    if (selectedProctorIds.includes(id)) {
      selectedProctorIds = selectedProctorIds.filter((x) => x !== id);
    } else {
      selectedProctorIds = [...selectedProctorIds, id];
    }
  }

  function submitForm(e) {
    e.preventDefault();
    $form.transform((data) => ({
      ...data,
      room_id: data.room_id ? parseInt(data.room_id, 10) : null,
      proctor_ids: selectedProctorIds,
      backtrack: backtrackMode,
    }));
    $form.post('/admin/exam-scheduling');
  }
const breadcrumbs = [{ label: 'Exam Scheduling', href: '/admin/exam-scheduling' }, { label: 'Create' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="room_id" class="text-sm font-medium">Room</label>
        <select
          id="room_id"
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          bind:value={$form.room_id}
          required
        >
          <option value="">Select room</option>
          {#each rooms as room}
            <option value={room.id}>{room.name} ({room.building ?? '—'}, cap. {room.capacity})</option>
          {/each}
        </select>
        {#if $form.errors?.room_id}
          <p class="text-sm text-destructive">{$form.errors.room_id}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="date" class="text-sm font-medium">Date</label>
        <Input id="date" type="date" bind:value={$form.date} required />
        {#if $form.errors?.date}
          <p class="text-sm text-destructive">{$form.errors.date}</p>
        {/if}
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="space-y-2">
          <label for="start_time" class="text-sm font-medium">Start time</label>
          <Input id="start_time" type="time" bind:value={$form.start_time} required />
          {#if $form.errors?.start_time}
            <p class="text-sm text-destructive">{$form.errors.start_time}</p>
          {/if}
        </div>
        <div class="space-y-2">
          <label for="end_time" class="text-sm font-medium">End time (optional)</label>
          <Input id="end_time" type="time" bind:value={$form.end_time} />
          {#if $form.errors?.end_time}
            <p class="text-sm text-destructive">{$form.errors.end_time}</p>
          {/if}
        </div>
      </div>

      <div class="space-y-2">
        <p class="text-sm font-medium">Proctors</p>
        <div class="flex flex-wrap gap-4 pt-2">
          {#each proctors as proctor}
            <label class="flex items-center gap-2 cursor-pointer min-h-[44px]">
              <input
                type="checkbox"
                checked={selectedProctorIds.includes(proctor.id)}
                onchange={() => toggleProctor(proctor.id)}
                class="h-4 w-4 rounded border-input accent-primary"
              />
              <span class="text-sm">{proctor.name}</span>
            </label>
          {/each}
        </div>
        {#if $form.errors?.proctor_ids}
          <p class="text-sm text-destructive">{$form.errors.proctor_ids}</p>
        {/if}
      </div>

      <div class="space-y-2 rounded-md border border-amber-500/30 bg-amber-500/5 p-4">
        <label class="flex items-center gap-2 cursor-pointer min-h-[44px]">
          <input
            type="checkbox"
            bind:checked={backtrackMode}
            class="h-4 w-4 rounded border-input accent-primary"
          />
          <span class="text-sm font-medium">Backtrack session</span>
        </label>
        <p class="text-xs text-muted-foreground">Enable this for recording a past exam session. Allows past dates and lets you mark all assigned applicants as present/submitted immediately after assigning them on the session detail page.</p>
      </div>

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Creating...' : 'Create Session'}
        </Button>
        <Link href="/admin/exam-scheduling">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
