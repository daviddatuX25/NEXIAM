<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import * as Table from '@/Components/ui/table';
  import { Badge } from '@/Components/ui/badge';
  import { UserPlus, UserMinus, Send, ClipboardList, RotateCcw, Pencil, GraduationCap, Search, ChevronLeft, ChevronRight, Trash2 } from 'lucide-svelte';
  import { formatDate } from '@/lib/date-utils';
  import { Input } from '@/Components/ui/input';

  let { session, assigned_applicants = [], available_applicants = [], proctors = [], view = 'admin', breadcrumbParent = { label: 'Exam Scheduling', href: '/admin/exam-scheduling' }, grading_session_id = null, grading_session_status = null } = $props();
  const isProctorView = $derived(view === 'proctor');

  const breadcrumbs = $derived([
    breadcrumbParent,
    { label: session?.id ? 'Session #' + session.id : 'Session' }
  ]);


  let selectedAvailable = $state([]);
  let backtrack = $state(false);
  let searchAvailable = $state('');
  let searchAssigned = $state('');
  let pageAvailable = $state(1);
  let pageAssigned = $state(1);
  const pageSize = 10;

  const filteredAvailable = $derived(
    available_applicants.filter((app) => {
      const term = searchAvailable.toLowerCase();
      return (
        (app.name && app.name.toLowerCase().includes(term)) ||
        (app.reference_number && app.reference_number.toLowerCase().includes(term)) ||
        (app.email && app.email.toLowerCase().includes(term))
      );
    })
  );

  const paginatedAvailable = $derived(
    filteredAvailable.slice((pageAvailable - 1) * pageSize, pageAvailable * pageSize)
  );

  const totalPagesAvailable = $derived(
    Math.ceil(filteredAvailable.length / pageSize)
  );

  const filteredAssigned = $derived(
    assigned_applicants.filter((app) => {
      const term = searchAssigned.toLowerCase();
      return (
        (app.name && app.name.toLowerCase().includes(term)) ||
        (app.reference_number && app.reference_number.toLowerCase().includes(term))
      );
    })
  );

  const paginatedAssigned = $derived(
    filteredAssigned.slice((pageAssigned - 1) * pageSize, pageAssigned * pageSize)
  );

  const totalPagesAssigned = $derived(
    Math.ceil(filteredAssigned.length / pageSize)
  );

  $effect(() => {
    // Reset to page 1 if search query changes
    searchAvailable;
    pageAvailable = 1;
  });

  $effect(() => {
    searchAssigned;
    pageAssigned = 1;
  });

  $effect(() => {
    if (pageAvailable > totalPagesAvailable && totalPagesAvailable > 0) {
      pageAvailable = totalPagesAvailable;
    } else if (pageAvailable < 1) {
      pageAvailable = 1;
    }
  });

  $effect(() => {
    if (pageAssigned > totalPagesAssigned && totalPagesAssigned > 0) {
      pageAssigned = totalPagesAssigned;
    } else if (pageAssigned < 1) {
      pageAssigned = 1;
    }
  });

  const isAllFilteredSelected = $derived(
    filteredAvailable.length > 0 && filteredAvailable.every((app) => selectedAvailable.includes(app.id))
  );

  const isSomeFilteredSelected = $derived(
    filteredAvailable.some((app) => selectedAvailable.includes(app.id)) && !isAllFilteredSelected
  );

  function toggleSelectAllFiltered() {
    if (isAllFilteredSelected) {
      const filteredIds = filteredAvailable.map((app) => app.id);
      selectedAvailable = selectedAvailable.filter((id) => !filteredIds.includes(id));
    } else {
      const filteredIds = filteredAvailable.map((app) => app.id);
      selectedAvailable = Array.from(new Set([...selectedAvailable, ...filteredIds]));
    }
  }


  function formatTime(value) {
    if (value == null || value === '') return '—';
    const parts = String(value).split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const mins = parseInt(parts[1], 10) || 0;
    const h = hours % 12 || 12;
    const ampm = hours < 12 ? 'AM' : 'PM';
    return `${h}:${String(mins).padStart(2, '0')} ${ampm}`;
  }

  function statusVariant(status) {
    if (status === 'draft') return 'muted';
    if (status === 'published') return 'success';
    if (status === 'in_progress') return 'warning';
    if (status === 'completed') return 'outline';
    if (status === 'cancelled') return 'danger';
    return 'outline';
  }

  function toggleAvailable(id) {
    if (selectedAvailable.includes(id)) {
      selectedAvailable = selectedAvailable.filter((x) => x !== id);
    } else {
      selectedAvailable = [...selectedAvailable, id];
    }
  }

  function assignSelected() {
    if (selectedAvailable.length === 0) return;
    router.post(`/admin/exam-scheduling/${session.id}/assign-applicants`, {
      applicant_ids: selectedAvailable,
      backtrack: backtrack
    }, {
      onSuccess: () => {
        selectedAvailable = [];
        backtrack = false;
      },
    });
  }

  function removeAssigned(rowOrId) {
    const sessionApplicantId = typeof rowOrId === 'object' && rowOrId !== null ? rowOrId.session_applicant_id : rowOrId;
    router.post(`/admin/exam-scheduling/${session.id}/remove-applicant`, { session_applicant_id: sessionApplicantId });
  }

  function publish() {
    router.post(`/admin/exam-scheduling/${session.id}/publish`, {}, { onSuccess: () => router.reload() });
  }

  function unpublish() {
    router.post(`/admin/exam-scheduling/${session.id}/unpublish`, {}, { onSuccess: () => router.reload() });
  }

  function reopenSession() {
    router.post(`/admin/exam-scheduling/${session.id}/reopen`, {}, { onSuccess: () => router.reload() });
  }

  function deleteSession() {
    if (confirm('Are you sure you want to delete this exam session? All applicant assignments and grading session/scores (if any) will be permanently deleted.')) {
      router.delete(`/admin/exam-scheduling/${session.id}`);
    }
  }
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6">
    {#if !isProctorView}
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-muted-foreground">View session details, assign applicants, and manage scheduling.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        {#if session.status === 'draft'}
          <Link href={`/admin/exam-scheduling/${session.id}/edit`}>
            <Button variant="outline" class="min-h-[44px] gap-2">
              <Pencil class="h-4 w-4" />
              <span class="hidden sm:inline">Edit session</span>
            </Button>
          </Link>
        {/if}
        {#if ['draft', 'published', 'in_progress'].includes(session.status) || (session.status === 'completed' && grading_session_status !== 'finalized')}
          <Button variant="destructive" class="min-h-[44px] gap-2" onclick={deleteSession}>
            <Trash2 class="h-4 w-4" />
            <span class="hidden sm:inline">Delete session</span>
          </Button>
        {/if}
      </div>
    </div>
    {/if}


    <div class="rounded-lg border border-border bg-card p-6">
      <dl class="mt-4 grid gap-3 sm:grid-cols-2">
        <div>
          <dt class="text-sm text-muted-foreground">Date</dt>
          <dd class="font-medium">{formatDate(session.date)}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Time</dt>
          <dd class="font-medium">{formatTime(session.start_time)}{#if session.end_time} – {formatTime(session.end_time)}{/if}</dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Room</dt>
          <dd class="font-medium">{session.room?.name ?? '—'} (cap. {session.room?.capacity ?? '—'})</dd>
        </div>
        <div>
          <dt class="text-sm text-muted-foreground">Status</dt>
          <dd>
            <Badge variant={statusVariant(session.status)}>{session.status}</Badge>
          </dd>
        </div>
        <div class="sm:col-span-2">
          <dt class="text-sm text-muted-foreground">Proctors</dt>
          <dd class="font-medium">{(session.proctors ?? []).map((p) => p.name).join(', ') || '—'}</dd>
        </div>
      </dl>
      {#if isProctorView && (session.status === 'published' || session.status === 'in_progress')}
        <div class="mt-4 pt-4 border-t border-border">
          <Link
            href="/proctor/sessions/{session.id}"
            class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 min-h-[44px]"
          >
            <ClipboardList class="h-4 w-4" />
            View Session
          </Link>
        </div>
      {/if}
      {#if !isProctorView && session.status === 'completed'}
        <div class="mt-4 pt-4 border-t border-border flex flex-wrap items-center gap-3">
          {#if grading_session_id}
            <a
              href={`/admin/grading/sessions/${grading_session_id}`}
              class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 min-h-[44px] transition-colors"
            >
              <GraduationCap class="h-4 w-4" />
              Go to Grading
            </a>
          {/if}
          {#if !grading_session_id || grading_session_status !== 'finalized'}
            <Button class="min-h-[44px]" variant="outline" onclick={reopenSession}>
              <RotateCcw class="h-4 w-4 mr-2" />
              Reopen session
            </Button>
            <p class="w-full text-sm text-muted-foreground">Reopen so proctors can continue marking attendance or submissions (e.g. late examinee).</p>
          {/if}
        </div>
      {/if}
    </div>

    <!-- Assigned applicants (admin only) -->
    {#if !isProctorView}
    <div class="rounded-lg border border-border bg-card p-6">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold">Assigned applicants</h2>
          <p class="mt-1 text-sm text-muted-foreground">Applicants assigned to this session. Remove to unassign.</p>
        </div>
      </div>
      {#if (assigned_applicants ?? []).length > 0}
        <div class="mt-4 relative max-w-sm">
          <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
          <Input
            type="search"
            placeholder="Search assigned..."
            bind:value={searchAssigned}
            class="pl-8 h-9"
          />
        </div>

        {#if filteredAssigned.length > 0}
          <div class="mt-4 overflow-x-auto scrollbar-hide">
            <Table.Root>
              <Table.Header class="bg-muted/50">
                <Table.Row>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                  <Table.Head class="text-center">Actions</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each paginatedAssigned as row (row.session_applicant_id)}
                  <Table.Row>
                    <Table.Cell>{row.reference_number}</Table.Cell>
                    <Table.Cell>{row.name}</Table.Cell>
                    <Table.Cell class="text-center">
                      <div class="flex justify-center">
                        {#if session.status !== 'completed'}
                        <Button
                          variant="ghost"
                          size="sm"
                          class="h-8 px-2 text-xs text-destructive hover:text-destructive hover:bg-destructive/10"
                          onclick={() => removeAssigned(row)}
                        >
                          <UserMinus class="mr-1.5 h-3.5 w-3.5" />
                          Remove
                        </Button>
                        {/if}
                      </div>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </div>

          {#if totalPagesAssigned > 1}
            <div class="mt-4 pt-4 border-t border-border/50 flex items-center justify-between">
              <div class="text-sm text-muted-foreground hidden sm:block">
                Showing
                <span class="font-medium">{Math.min((pageAssigned - 1) * pageSize + 1, filteredAssigned.length)}</span>
                to
                <span class="font-medium">{Math.min(pageAssigned * pageSize, filteredAssigned.length)}</span>
                of
                <span class="font-medium">{filteredAssigned.length}</span>
                results
              </div>
              <nav aria-label="Pagination Navigation" class="flex items-center space-x-2">
                <Button variant="outline" size="sm" class="h-8 w-8 p-0" onclick={() => pageAssigned--} disabled={pageAssigned <= 1}>
                  <span class="sr-only">Previous</span>
                  <ChevronLeft class="h-4 w-4" />
                </Button>
                <div class="text-sm font-medium">
                  Page {pageAssigned} of {totalPagesAssigned}
                </div>
                <Button variant="outline" size="sm" class="h-8 w-8 p-0" onclick={() => pageAssigned++} disabled={pageAssigned >= totalPagesAssigned}>
                  <span class="sr-only">Next</span>
                  <ChevronRight class="h-4 w-4" />
                </Button>
              </nav>
            </div>
          {/if}
        {:else}
          <p class="mt-4 text-sm text-muted-foreground">No assigned applicants match your search.</p>
        {/if}
      {:else}
        <p class="mt-4 text-sm text-muted-foreground">No applicants assigned yet. Select from available below and click Assign.</p>
      {/if}
    </div>
    {/if}

    <!-- Available applicants (bulk assign) -->
    {#if !isProctorView && session.status !== 'completed'}
    <div class="rounded-lg border border-border bg-card p-6">
      <h2 class="text-lg font-semibold">Available applicants</h2>
      <p class="mt-1 text-sm text-muted-foreground">Accepted applicants not yet assigned to a session. Select and assign.</p>
      {#if (available_applicants ?? []).length > 0}
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="relative flex-1 max-w-sm">
            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
            <Input
              type="search"
              placeholder="Search available..."
              bind:value={searchAvailable}
              class="pl-8 h-9"
            />
          </div>
          {#if selectedAvailable.length > 0}
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <span>Selected {selectedAvailable.length} applicant(s)</span>
              <Button
                variant="ghost"
                size="sm"
                class="h-8 px-2 text-xs text-destructive hover:bg-destructive/10"
                onclick={() => selectedAvailable = []}
              >
                Clear selection
              </Button>
            </div>
          {/if}
        </div>

        {#if filteredAvailable.length > 0}
          <div class="mt-4 overflow-x-auto scrollbar-hide">
            <Table.Root>
              <Table.Header class="bg-muted/50">
                <Table.Row>
                  <Table.Head class="w-12 text-center">
                    <input
                      type="checkbox"
                      class="h-4 w-4 rounded border-input accent-primary cursor-pointer"
                      checked={isAllFilteredSelected}
                      indeterminate={isSomeFilteredSelected}
                      onchange={toggleSelectAllFiltered}
                      aria-label="Select all matching applicants"
                    />
                  </Table.Head>
                  <Table.Head>Reference</Table.Head>
                  <Table.Head>Name</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each paginatedAvailable as app (app.id)}
                  <Table.Row>
                    <Table.Cell class="text-center">
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-input accent-primary cursor-pointer"
                        checked={selectedAvailable.includes(app.id)}
                        onchange={() => toggleAvailable(app.id)}
                        aria-label="Select {app.name}"
                      />
                    </Table.Cell>
                    <Table.Cell>{app.reference_number}</Table.Cell>
                    <Table.Cell>{app.name}</Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </div>

          {#if totalPagesAvailable > 1}
            <div class="mt-4 pt-4 border-t border-border/50 flex items-center justify-between">
              <div class="text-sm text-muted-foreground hidden sm:block">
                Showing
                <span class="font-medium">{Math.min((pageAvailable - 1) * pageSize + 1, filteredAvailable.length)}</span>
                to
                <span class="font-medium">{Math.min(pageAvailable * pageSize, filteredAvailable.length)}</span>
                of
                <span class="font-medium">{filteredAvailable.length}</span>
                results
              </div>
              <nav aria-label="Pagination Navigation" class="flex items-center space-x-2">
                <Button variant="outline" size="sm" class="h-8 w-8 p-0" onclick={() => pageAvailable--} disabled={pageAvailable <= 1}>
                  <span class="sr-only">Previous</span>
                  <ChevronLeft class="h-4 w-4" />
                </Button>
                <div class="text-sm font-medium">
                  Page {pageAvailable} of {totalPagesAvailable}
                </div>
                <Button variant="outline" size="sm" class="h-8 w-8 p-0" onclick={() => pageAvailable++} disabled={pageAvailable >= totalPagesAvailable}>
                  <span class="sr-only">Next</span>
                  <ChevronRight class="h-4 w-4" />
                </Button>
              </nav>
            </div>
          {/if}
        {:else}
          <p class="mt-4 text-sm text-muted-foreground">No available applicants match your search.</p>
        {/if}

        <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-border/50 pt-4">
          <Button
            class="min-h-[44px]"
            disabled={selectedAvailable.length === 0}
            onclick={assignSelected}
          >
            <UserPlus class="h-4 w-4 mr-2" />
            Assign selected ({selectedAvailable.length})
          </Button>

          <label class="flex items-center gap-2 text-sm select-none cursor-pointer">
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-input accent-primary"
              bind:checked={backtrack}
            />
            <span>Backtrack (mark present/submitted and complete session immediately)</span>
          </label>
        </div>
      {:else}
        <p class="mt-4 text-sm text-muted-foreground">No available applicants, or all accepted applicants are already assigned.</p>
      {/if}
    </div>
    {/if}

    <!-- Publish (admin only) - hidden when completed, cancelled, or in_progress (E-002, E-003) -->
    {#if !isProctorView && !['completed', 'cancelled', 'in_progress'].includes(session.status)}
    <div class="rounded-lg border border-border bg-card p-6">
      <h2 class="text-lg font-semibold">Schedule actions</h2>
      <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-6">
        <div>
          {#if session.status === 'published'}
            <Button class="min-h-[44px]" variant="destructive" onclick={unpublish}>
              <Send class="h-4 w-4 mr-2" />
              Unpublish
            </Button>
          {:else}
            <Button
              class="min-h-[44px]"
              disabled={session.is_publishable === false}
              title={session.publish_block_reason || ''}
              onclick={publish}
            >
              <Send class="h-4 w-4 mr-2" />
              Publish session
            </Button>
          {/if}
          {#if session.publish_block_reason}
            <p class="mt-1 text-xs text-destructive">{session.publish_block_reason}</p>
          {:else}
            <p class="mt-1 text-xs text-muted-foreground">Notify assigned applicants and lock schedule.</p>
          {/if}
        </div>
        {#if assigned_applicants.length > 0}
          <div>
            <Button
              class="min-h-[44px]"
              variant="outline"
              onclick={() => {
                if (confirm('Are you sure you want to backtrack this session? All assigned applicants will be marked as present/submitted, and the session will be marked as completed.')) {
                  router.post(`/admin/exam-scheduling/${session.id}/backtrack`, {}, { onSuccess: () => router.reload() });
                }
              }}
            >
              <ClipboardList class="h-4 w-4 mr-2" />
              Backtrack Session
            </Button>
            <p class="mt-1 text-xs text-muted-foreground">Mark already-assigned applicants present/submitted & complete session.</p>
          </div>
        {/if}
      </div>
    </div>
    {/if}
  </div>
</AuthenticatedLayout>
