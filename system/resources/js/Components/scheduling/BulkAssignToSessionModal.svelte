<script>
  import * as Dialog from '@/Components/ui/dialog';
  import { Button } from '@/Components/ui/button';
  import { router } from '@inertiajs/svelte';
  import { formatDate, formatTimeRange } from '@/lib/date-utils';
  import {
    AlertCircle,
    Calendar,
    Check,
    Loader2,
    Users,
    X,
  } from 'lucide-svelte';

  let {
    open = $bindable(false),
    domain = 'exam', // 'exam' | 'consultation'
    sessions = [],
    selectedAttemptIds = [],
    onSuccess = null,
  } = $props();

  let selectedSessionId = $state(null);
  let isSubmitting = $state(false);
  let errorMessage = $state('');

  let eligibleSessions = $derived(
    sessions
      .filter((s) => {
        const status = s.status ?? 'draft';
        if (['completed', 'cancelled'].includes(status)) return false;
        const cap = Number(s.capacity ?? s.room?.capacity ?? 0);
        const assigned = Number(s.assigned_count ?? s.active_count ?? 0);
        return cap - assigned >= 1;
      })
      .map((s) => {
        const cap = Number(s.capacity ?? s.room?.capacity ?? 0);
        const assigned = Number(s.assigned_count ?? s.active_count ?? 0);
        const remaining = Math.max(0, cap - assigned);
        const dateLabel =
          s.date_label ??
          s.date ??
          (s.starts_at ? formatDate(s.starts_at) : s.scheduled_at ? formatDate(s.scheduled_at) : 'Scheduled');
        const timeLabel =
          s.time ??
          (s.starts_at && s.ends_at ? formatTimeRange(s.starts_at, s.ends_at) : '');

        return {
          id: Number(s.id),
          code: s.code,
          room_name: s.room_name ?? s.room?.name ?? 'Room',
          date_label: dateLabel,
          time_label: timeLabel,
          capacity: cap,
          assigned_count: assigned,
          remaining,
          status: s.status ?? 'draft',
          canFitAll: remaining >= selectedAttemptIds.length,
        };
      })
      .sort((a, b) => (b.canFitAll ? 1 : 0) - (a.canFitAll ? 1 : 0) || a.remaining - b.remaining)
  );

  $effect(() => {
    if (open) {
      errorMessage = '';
      if (selectedSessionId === null && eligibleSessions.length > 0) {
        const firstBest = eligibleSessions.find((s) => s.canFitAll) ?? eligibleSessions[0];
        selectedSessionId = firstBest?.id ?? null;
      }
    } else {
      selectedSessionId = null;
      errorMessage = '';
      isSubmitting = false;
    }
  });

  function handleAssign() {
    if (!selectedSessionId || selectedAttemptIds.length === 0 || isSubmitting) {
      return;
    }

    isSubmitting = true;
    errorMessage = '';

    const endpoint =
      domain === 'exam'
        ? `/exams/sessions/${selectedSessionId}/bulk-assign`
        : `/consultation/sessions/${selectedSessionId}/bulk-assign`;

    router.post(
      endpoint,
      { admission_attempt_ids: selectedAttemptIds },
      {
        preserveScroll: true,
        onSuccess: () => {
          isSubmitting = false;
          open = false;
          onSuccess?.();
        },
        onError: (errors) => {
          isSubmitting = false;
          errorMessage =
            Object.values(errors)[0] ?? 'Failed to assign applicants to session.';
        },
        onFinish: () => {
          isSubmitting = false;
        },
      }
    );
  }
</script>

<Dialog.Root bind:open>
  <Dialog.Content
    class="w-full sm:max-w-xl max-h-[90vh] overflow-hidden p-0 font-sans border-border bg-card shadow-2xl rounded-2xl flex flex-col"
    showCloseButton={false}
  >
    <!-- Modal Header -->
    <div
      class="bg-card/95 backdrop-blur-md border-b border-border/80 px-6 py-4 flex items-center justify-between shrink-0"
    >
      <div class="flex items-center gap-3">
        <div
          class="size-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0"
        >
          <Users class="size-4" />
        </div>
        <div>
          <h3 class="font-semibold text-foreground text-sm tracking-tight">
            Assign Applicants to Session
          </h3>
          <p class="text-xs text-muted-foreground">
            <span class="font-semibold text-foreground">{selectedAttemptIds.length}</span>
            applicant{selectedAttemptIds.length === 1 ? '' : 's'} selected for assignment
          </p>
        </div>
      </div>
      <button
        type="button"
        class="size-7 rounded-md border border-border/60 hover:bg-muted/80 text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer"
        onclick={() => (open = false)}
        aria-label="Close"
      >
        <X class="size-3.5" />
      </button>
    </div>

    <!-- Modal Body -->
    <div class="px-6 py-4 overflow-y-auto space-y-4 flex-1">
      {#if errorMessage}
        <div
          class="p-3 rounded-xl bg-destructive/10 border border-destructive/20 flex items-start gap-2.5 text-xs text-destructive"
          role="alert"
        >
          <AlertCircle class="size-4 shrink-0 mt-0.5" />
          <div class="flex-1">{errorMessage}</div>
        </div>
      {/if}

      {#if eligibleSessions.length === 0}
        <div class="py-12 text-center space-y-2">
          <Calendar class="size-10 mx-auto text-muted-foreground/40" />
          <h4 class="text-sm font-semibold text-foreground">No Available Sessions</h4>
          <p class="text-xs text-muted-foreground max-w-sm mx-auto">
            There are no active sessions with open seats in this cycle. Please create or publish a new session first.
          </p>
        </div>
      {:else}
        <div class="space-y-2">
          <label class="text-xs font-semibold text-muted-foreground uppercase tracking-wider font-mono">
            Choose Destination Session ({eligibleSessions.length} available)
          </label>

          <div class="space-y-2 max-h-[50vh] overflow-y-auto pr-1">
            {#each eligibleSessions as session (session.id)}
              {@const isSelected = selectedSessionId === session.id}
              <button
                type="button"
                class="w-full text-left p-3.5 rounded-xl border transition-all cursor-pointer flex items-start justify-between gap-3 {isSelected
                  ? 'border-primary bg-primary/[0.04] ring-1 ring-primary/40 shadow-xs'
                  : 'border-border/70 bg-card hover:border-border hover:bg-muted/20'}"
                onclick={() => (selectedSessionId = session.id)}
              >
                <div class="space-y-1 flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-foreground text-xs font-mono">{session.code}</span>
                    <span class="text-xs text-muted-foreground truncate">&bull; {session.room_name}</span>
                  </div>
                  <div class="flex items-center gap-2 text-xs text-muted-foreground">
                    <Calendar class="size-3 text-muted-foreground/70" />
                    <span>{session.date_label}</span>
                    {#if session.time_label}
                      <span>&bull; {session.time_label}</span>
                    {/if}
                  </div>
                </div>

                <div class="text-right shrink-0 space-y-1">
                  {#if session.canFitAll}
                    <span
                      class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300"
                    >
                      <Check class="size-3" />
                      Fits all {selectedAttemptIds.length}
                    </span>
                  {:else}
                    <span
                      class="inline-flex items-center rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300"
                    >
                      {session.remaining} seats left
                    </span>
                  {/if}
                  <p class="text-[11px] font-mono text-muted-foreground">
                    {session.assigned_count} / {session.capacity} filled
                  </p>
                </div>
              </button>
            {/each}
          </div>
        </div>
      {/if}
    </div>

    <!-- Modal Footer -->
    <div
      class="bg-muted/20 border-t border-border/80 px-6 py-3.5 flex items-center justify-between shrink-0"
    >
      <Button
        type="button"
        variant="ghost"
        size="sm"
        class="text-xs"
        onclick={() => (open = false)}
        disabled={isSubmitting}
      >
        Cancel
      </Button>

      <Button
        type="button"
        size="sm"
        class="text-xs min-w-28"
        onclick={handleAssign}
        disabled={!selectedSessionId || selectedAttemptIds.length === 0 || isSubmitting}
      >
        {#if isSubmitting}
          <Loader2 class="size-3.5 animate-spin mr-1.5" />
          Assigning…
        {:else}
          Assign {selectedAttemptIds.length} Applicant{selectedAttemptIds.length === 1 ? '' : 's'}
        {/if}
      </Button>
    </div>
  </Dialog.Content>
</Dialog.Root>
