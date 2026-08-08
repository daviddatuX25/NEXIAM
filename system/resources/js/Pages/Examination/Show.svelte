<script>
  /**
   * Exam session Show — legacy Admin/TestScheduling/Show shape:
   * details, assigned roster, available assign picker, publish from show.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';
  import { Input } from '@/Components/ui/input';
  import * as Table from '@/Components/ui/table';
  import { Textarea } from '@/Components/ui/textarea';
  import { Link, router } from '@inertiajs/svelte';
  import { Ban, Lock, LockOpen, Pencil, Search, Send, Undo2, UserMinus, UserPlus, ArrowRightLeft } from 'lucide-svelte';

  let {
    session,
    assigned_applicants = [],
    available_applicants = [],
    reassign_targets = [],
    publish_block_reason = null,
    is_publishable = false,
  } = $props();

  let selectedAvailable = $state([]);
  let searchAvailable = $state('');
  let searchAssigned = $state('');
  let reasonOpen = $state(false);
  let reasonMode = $state('unpublish'); // unpublish | cancel
  let reason = $state('');
  let selectedAssigned = $state([]);
  let reassignOpen = $state(false);
  let reassignTargetId = $state('');
  let reassignReason = $state('');
  let reassigning = $state(false);

  const filteredAvailable = $derived(
    available_applicants.filter((app) => {
      const term = searchAvailable.toLowerCase();
      return (
        (app.name && app.name.toLowerCase().includes(term)) ||
        (app.reference_number && app.reference_number.toLowerCase().includes(term)) ||
        (app.email && app.email.toLowerCase().includes(term))
      );
    }),
  );

  const filteredAssigned = $derived(
    assigned_applicants.filter((app) => {
      const term = searchAssigned.toLowerCase();
      return (
        (app.name && app.name.toLowerCase().includes(term)) ||
        (app.reference_number && app.reference_number.toLowerCase().includes(term))
      );
    }),
  );

  function toggleAvailable(id) {
    selectedAvailable = selectedAvailable.includes(id)
      ? selectedAvailable.filter((x) => x !== id)
      : [...selectedAvailable, id];
  }

  function assignSelected() {
    if (selectedAvailable.length === 0) return;
    const ids = [...selectedAvailable];
    selectedAvailable = [];

    const postNext = (index) => {
      if (index >= ids.length) {
        router.reload();
        return;
      }
      router.post(
        `/exams/sessions/${session.id}/assign`,
        { admission_attempt_id: ids[index] },
        {
          preserveScroll: true,
          onFinish: () => postNext(index + 1),
        },
      );
    };

    postNext(0);
  }

  function removeAssigned(assignmentId) {
    router.post(`/exams/sessions/${session.id}/assignments/${assignmentId}/remove`, {
      reason: 'Removed from session show',
    });
  }

  function toggleAssigned(id) {
    selectedAssigned = selectedAssigned.includes(id)
      ? selectedAssigned.filter((x) => x !== id)
      : [...selectedAssigned, id];
  }

  function openReassign(ids) {
    selectedAssigned = [...ids];
    reassignTargetId = '';
    reassignReason = '';
    reassignOpen = true;
  }

  function submitReassign() {
    if (!reassignTargetId || selectedAssigned.length === 0) return;
    reassigning = true;
    router.post(
      `/exams/sessions/${session.id}/reassign`,
      {
        assignment_ids: selectedAssigned,
        target_session_id: Number(reassignTargetId),
        reason: reassignReason || 'Reassigned from session show',
      },
      {
        preserveScroll: true,
        onFinish: () => {
          reassigning = false;
          reassignOpen = false;
          selectedAssigned = [];
        },
      },
    );
  }

  const openReassignTargets = $derived(reassign_targets.filter((t) => !t.full));

  function publish() {
    router.post(`/exams/sessions/${session.id}/publish`, {
      expected_version: session.version,
    });
  }


  function openReservation() {
    router.post(`/exams/sessions/${session.id}/open-reservation`, {
      expected_version: session.version,
    });
  }

  function closeReservation() {
    router.post(`/exams/sessions/${session.id}/close-reservation`, {
      expected_version: session.version,
    });
  }

  function bookingModeLabel(mode) {
    if (mode === 'open_for_reservation') return 'Open for reservation';
    return 'Staff only';
  }

  function openReason(mode) {
    reasonMode = mode;
    reason = '';
    reasonOpen = true;
  }

  function submitReason() {
    if (!reason.trim()) return;
    const path =
      reasonMode === 'unpublish'
        ? `/exams/sessions/${session.id}/unpublish`
        : `/exams/sessions/${session.id}/cancel`;
    router.post(path, {
      expected_version: session.version,
      reason,
    }, {
      onFinish: () => {
        reasonOpen = false;
      },
    });
  }

  function statusVariant(status) {
    if (status === 'draft') return 'secondary';
    if (status === 'published') return 'default';
    if (status === 'in_progress') return 'outline';
    if (status === 'completed') return 'outline';
    if (status === 'cancelled') return 'destructive';
    return 'outline';
  }

  const breadcrumbs = [
    { label: 'Exams', href: '/exams/sessions' },
    { label: session?.code ?? 'Session' },
  ];
</script>

<svelte:head><title>{session?.code ?? 'Exam session'}</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title={session.code}
      description="View session details, assign applicants, and publish the schedule."
    >
      {#snippet actions()}
        <div class="flex flex-wrap gap-2">
          {#if session.status === 'draft'}
            <Link href={`/exams/sessions/${session.id}/edit`}>
              <Button variant="outline" class="min-h-10 gap-2">
                <Pencil class="size-4" />
                Edit session
              </Button>
            </Link>
            <Button
              class="min-h-10 gap-2"
              disabled={!is_publishable}
              title={publish_block_reason || 'Publish session'}
              onclick={publish}
            >
              <Send class="size-4" />
              Publish
            </Button>
            <Button variant="outline" class="min-h-10 gap-2 text-destructive" onclick={() => openReason('cancel')}>
              <Ban class="size-4" />
              Cancel session
            </Button>
          {:else if session.status === 'published'}
            <Button variant="outline" class="min-h-10 gap-2" onclick={() => openReason('unpublish')}>
              <Undo2 class="size-4" />
              Unpublish
            </Button>
            <Button variant="outline" class="min-h-10 gap-2 text-destructive" onclick={() => openReason('cancel')}>
              <Ban class="size-4" />
              Cancel session
            </Button>
          {/if}
          {#if session.status !== 'completed' && session.status !== 'cancelled'}
            {#if session.booking_mode === 'open_for_reservation'}
              <Button variant="outline" class="min-h-10 gap-2" onclick={closeReservation}>
                <Lock class="size-4" />
                Close reservation
              </Button>
            {:else}
              <Button variant="outline" class="min-h-10 gap-2" onclick={openReservation}>
                <LockOpen class="size-4" />
                Open reservation
              </Button>
            {/if}
          {/if}
        </div>
      {/snippet}
    </PageHeaderBar>

    {#if publish_block_reason && session.status === 'draft'}
      <div class="rounded-md border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
        Cannot publish yet: {publish_block_reason}
      </div>
    {/if}

    <div class="rounded-xl border border-border bg-card p-6">
      <dl class="grid gap-3 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-muted-foreground">When</dt>
          <dd class="font-medium">
            {session.date} · {session.start_time}{#if session.end_time}–{session.end_time}{/if}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Room</dt>
          <dd class="font-medium">
            {session.room?.name ?? session.room?.code ?? '—'} (cap. {session.room?.capacity ?? '—'})
          </dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Academic year</dt>
          <dd class="font-medium">{session.academic_cycle?.label ?? '—'}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Status</dt>
          <dd><Badge variant={statusVariant(session.status)}>{session.status}</Badge></dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Booking</dt>
          <dd>
            <Badge variant={session.booking_mode === 'open_for_reservation' ? 'default' : 'outline'}>
              {bookingModeLabel(session.booking_mode)}
            </Badge>
          </dd>
        </div>
        <div class="sm:col-span-2">
          <dt class="text-sm text-muted-foreground">Proctors</dt>
          <dd class="font-medium">
            {(session.proctors ?? []).map((p) => p.name).join(', ') || '—'}
          </dd>
        </div>
      </dl>
    </div>

    <div class="rounded-xl border border-border bg-card p-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold tracking-tight">Assigned applicants</h2>
          <p class="mt-1 text-sm text-muted-foreground">
            Remove frees the seat. Reassign moves them to another session in one step.
          </p>
        </div>
        {#if session.status !== 'completed' && session.status !== 'cancelled'}
          <Button
            variant="outline"
            class="min-h-10 gap-2"
            disabled={selectedAssigned.length === 0 || openReassignTargets.length === 0}
            onclick={() => openReassign(selectedAssigned)}
            title={openReassignTargets.length === 0 ? 'No other sessions with capacity' : 'Reassign selected'}
          >
            <ArrowRightLeft class="size-4" />
            Reassign selected ({selectedAssigned.length})
          </Button>
        {/if}
      </div>

      {#if assigned_applicants.length > 0}
        <div class="relative mt-4 max-w-sm">
          <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <Input type="search" placeholder="Search assigned..." class="h-9 pl-8" bind:value={searchAssigned} />
        </div>
        <div class="mt-4 overflow-x-auto">
          <Table.Root>
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="w-12"></Table.Head>
                <Table.Head>Reference</Table.Head>
                <Table.Head>Name</Table.Head>
                <Table.Head>Source</Table.Head>
                <Table.Head class="text-center">Actions</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each filteredAssigned as row (row.id)}
                <Table.Row>
                  <Table.Cell>
                    {#if session.status !== 'completed' && session.status !== 'cancelled'}
                      <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        checked={selectedAssigned.includes(row.id)}
                        onchange={() => toggleAssigned(row.id)}
                        aria-label={`Select ${row.name}`}
                      />
                    {/if}
                  </Table.Cell>
                  <Table.Cell>{row.reference_number}</Table.Cell>
                  <Table.Cell>{row.name}</Table.Cell>
                  <Table.Cell class="text-muted-foreground">{row.source}</Table.Cell>
                  <Table.Cell class="text-center">
                    {#if session.status !== 'completed' && session.status !== 'cancelled'}
                      <div class="flex flex-wrap items-center justify-center gap-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          class="h-8 px-2 text-xs"
                          disabled={openReassignTargets.length === 0}
                          onclick={() => openReassign([row.id])}
                        >
                          <ArrowRightLeft class="mr-1.5 size-3.5" />
                          Reassign
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          class="h-8 px-2 text-xs text-destructive"
                          onclick={() => removeAssigned(row.id)}
                        >
                          <UserMinus class="mr-1.5 size-3.5" />
                          Remove
                        </Button>
                      </div>
                    {/if}
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </div>
      {:else}
        <p class="mt-4 text-sm text-muted-foreground">No applicants assigned yet.</p>
      {/if}
    </div>

    {#if session.status !== 'completed' && session.status !== 'cancelled'}
      <div class="rounded-xl border border-border bg-card p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-lg font-semibold tracking-tight">Available applicants</h2>
            <p class="mt-1 text-sm text-muted-foreground">
              Accepted applicants without an active exam assignment.
            </p>
          </div>
          <Button
            class="min-h-10 gap-2"
            disabled={selectedAvailable.length === 0}
            onclick={assignSelected}
          >
            <UserPlus class="size-4" />
            Assign selected ({selectedAvailable.length})
          </Button>
        </div>

        {#if available_applicants.length > 0}
          <div class="relative mt-4 max-w-sm">
            <Search
              class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
              type="search"
              placeholder="Search available..."
              class="h-9 pl-8"
              bind:value={searchAvailable}
            />
          </div>
          <div class="mt-4 overflow-x-auto">
            <Table.Root>
              <Table.Header class="bg-muted/50">
                <Table.Row>
                  <Table.Head class="w-12"></Table.Head>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each filteredAvailable as app (app.id)}
                  <Table.Row>
                    <Table.Cell>
                      <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        checked={selectedAvailable.includes(app.id)}
                        onchange={() => toggleAvailable(app.id)}
                        aria-label={`Select ${app.name}`}
                      />
                    </Table.Cell>
                    <Table.Cell>{app.reference_number}</Table.Cell>
                    <Table.Cell>{app.name}</Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </div>
        {:else}
          <p class="mt-4 text-sm text-muted-foreground">No eligible applicants available to assign.</p>
        {/if}
      </div>
    {/if}
  </div>

  <Dialog.Root bind:open={reasonOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>
          {reasonMode === 'unpublish' ? 'Unpublish session' : 'Cancel session'}
        </Dialog.Title>
        <Dialog.Description>
          {reasonMode === 'unpublish'
            ? 'Returns the session to draft. Assignments stay; applicants may need a new publish notice later.'
            : 'Terminal cancel. Frees active assignments and open reservations. Requires a reason.'}
        </Dialog.Description>
      </Dialog.Header>
      <Textarea bind:value={reason} rows={3} placeholder="Reason…" />
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => (reasonOpen = false)}>Back</Button>
        <Button type="button" disabled={!reason.trim()} onclick={submitReason}>Confirm</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  <Dialog.Root bind:open={reassignOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Reassign applicant{selectedAssigned.length === 1 ? '' : 's'}</Dialog.Title>
        <Dialog.Description>
          Move {selectedAssigned.length} seat{selectedAssigned.length === 1 ? '' : 's'} to another session in this academic year.
          Applicant notify on reassignment is not wired yet.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-muted-foreground" for="reassign-target">
            Target session
          </label>
          <select
            id="reassign-target"
            class="flex min-h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            bind:value={reassignTargetId}
          >
            <option value="">Select a session…</option>
            {#each openReassignTargets as target (target.id)}
              <option value={String(target.id)}>
                {target.code} · {target.date} {target.start_time} · {target.room ?? 'Room'} · {target.remaining} seats left
              </option>
            {/each}
          </select>
          {#if openReassignTargets.length === 0}
            <p class="mt-2 text-sm text-muted-foreground">No other sessions with remaining capacity.</p>
          {/if}
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-muted-foreground" for="reassign-reason">
            Reason (optional)
          </label>
          <Textarea id="reassign-reason" rows={3} bind:value={reassignReason} placeholder="Why are they moving?" />
        </div>
      </div>
      <Dialog.Footer>
        <Button type="button" variant="outline" class="min-h-10" onclick={() => (reassignOpen = false)}>
          Cancel
        </Button>
        <Button
          type="button"
          class="min-h-10 gap-2"
          disabled={reassigning || !reassignTargetId || selectedAssigned.length === 0}
          onclick={submitReassign}
        >
          <ArrowRightLeft class="size-4" />
          {reassigning ? 'Reassigning…' : 'Confirm reassign'}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

</StaffLayout>
