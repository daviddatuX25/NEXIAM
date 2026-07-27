<script>
  import { router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { MessageSquare, Send, Sparkles, Calendar, CheckCircle2, Trash2 } from 'lucide-svelte';
  import { Textarea } from '@/Components/ui/textarea';
  import { success as showSuccess, error as showError } from '@/lib/toast';

  let {
    applicant_count = 0,
    rooms = [],
    draft_sessions = [],
    messages: initialMessages = [],
    openrouter_configured = false,
    csrf_token = '',
    onApplied = null,
  } = $props();

  let messages = $state([...(initialMessages ?? [])]);
  let input = $state('');
  let loading = $state(false);
  let applying = $state(false);
  let error = $state('');
  let structuredSchedule = $state(null);
  let applyError = $state('');
  /** True only after we receive an assistant reply during this session (not from loaded history). */
  let hasReplyThisSession = $state(false);
  /** Reference to the messages container for auto-scrolling. */
  let messagesContainer = $state(null);

  let isHydrated = $state(false);

  $effect(() => {
    if (typeof window !== 'undefined' && !isHydrated) {
      const storedMsgs = localStorage.getItem('ai_scheduler_messages');
      const storedSchedule = localStorage.getItem('ai_scheduler_schedule');
      if (storedMsgs) {
        try {
          messages = JSON.parse(storedMsgs);
        } catch (e) {}
      }
      if (storedSchedule) {
        try {
          structuredSchedule = JSON.parse(storedSchedule);
        } catch (e) {}
      }
      isHydrated = true;
    }
  });

  $effect(() => {
    if (typeof window !== 'undefined' && isHydrated) {
      const cappedMessages = messages.slice(-50);
      localStorage.setItem('ai_scheduler_messages', JSON.stringify(cappedMessages));
    }
  });

  $effect(() => {
    if (typeof window !== 'undefined' && isHydrated) {
      if (structuredSchedule) {
        localStorage.setItem('ai_scheduler_schedule', JSON.stringify(structuredSchedule));
      } else {
        localStorage.removeItem('ai_scheduler_schedule');
      }
    }
  });

  const roomMap = $derived(
    Object.fromEntries((rooms ?? []).map((r) => [r.id, r]))
  );
  const draftMap = $derived(
    Object.fromEntries((draft_sessions ?? []).map((s) => [s.id, s]))
  );

  /**
   * Scrub JSON blocks and orphan braces from conversational text to keep UI extremely clean.
   */
  function formatContent(content) {
    if (!content) return '';

    // First, strip markdown code fences that wrap JSON
    let clean = content.replace(/```\s*json\s*([\s\S]*?)\s*```/gis, '');
    clean = clean.replace(/```\s*([\s\S]*?)\s*```/gis, (match) => {
      // If the code block contains JSON-like keys, strip the whole block
      if (match.includes('"sessions"') || match.includes('"room_id"') || match.includes('"applicant_ids"')) {
        return '';
      }
      return match;
    });

    // Parse out raw/bare JSON structures by tracking matching braces/brackets
    let i = 0;
    while (i < clean.length) {
      const char = clean[i];
      if (char === '{' || char === '[') {
        let braceCount = 1;
        let j = i + 1;
        let inString = false;
        let isEscaped = false;
        const openChar = char;
        const closeChar = char === '{' ? '}' : ']';

        while (j < clean.length && braceCount > 0) {
          const c = clean[j];
          if (isEscaped) {
            isEscaped = false;
          } else if (c === '\\') {
            isEscaped = true;
          } else if (c === '"') {
            inString = !inString;
          } else if (!inString) {
            if (c === openChar) {
              braceCount++;
            } else if (c === closeChar) {
              braceCount--;
            }
          }
          j++;
        }

        if (braceCount === 0) {
          const block = clean.substring(i, j);
          if (
            block.includes('"sessions"') || 
            block.includes('"room_id"') || 
            block.includes('"exam_session_id"') || 
            block.includes('"applicant_ids"')
          ) {
            clean = clean.substring(0, i) + clean.substring(j);
            continue;
          }
        }
      }
      i++;
    }

    // Clean up orphan closing braces/brackets and extra whitespace
    clean = clean.replace(/^\s*[\}\]]+\s*$/gm, '');
    return clean.replace(/\n{3,}/g, '\n\n').trim();
  }

  /**
   * Normalise a raw session object from the AI to the canonical field set.
   * Tries multiple known aliases so the UI is robust to AI hallucinations.
   */
  function normalizeSession(s) {
    return {
      action:          s.action    ?? null,
      exam_session_id: s.exam_session_id ?? s.session_id ?? null,
      room_id:        s.room_id   ?? s.roomId   ?? s.room?.id   ?? null,
      room_name:      s.room_name ?? s.room?.name ?? null,
      date:           s.date      ?? s.session_date ?? s.exam_date ?? null,
      start_time:     s.start_time ?? s.startTime ?? s.time_start ?? null,
      end_time:       s.end_time   ?? s.endTime   ?? s.time_end   ?? null,
      applicant_ids:  s.applicant_ids ?? s.applicantIds ?? s.applicants ?? [],
    };
  }

  function getScheduleRows(schedule) {
    if (!schedule?.sessions?.length) return [];
    return schedule.sessions.map((raw) => {
      const s = normalizeSession(raw);
      let action = s.action;
      if (!action) {
        action = s.exam_session_id ? ((s.room_id || s.date || s.start_time) ? 'edit' : 'assign') : 'create';
      }

      if (action === 'edit') {
        const draft = draftMap[s.exam_session_id];
        const roomName = roomMap[s.room_id]?.name ?? s.room_name ?? draft?.room?.name ?? '—';
        const dateStr = s.date ?? draft?.date ?? '—';
        const startTime = s.start_time ?? draft?.start_time;
        const endTime = s.end_time ?? draft?.end_time;
        const timeStr = [startTime, endTime].filter(Boolean).join('–') || '—';
        return {
          type: 'Edit draft',
          room: roomName,
          date: dateStr,
          time: timeStr,
          applicant_count: (s.applicant_ids ?? []).length > 0 ? (s.applicant_ids ?? []).length : 'No change',
        };
      }

      if (action === 'assign') {
        const draft = draftMap[s.exam_session_id];
        return {
          type: 'Assign only',
          room: draft?.room?.name ?? '—',
          date: draft?.date ?? '—',
          time: draft ? [draft.start_time, draft.end_time].filter(Boolean).join('–') : '—',
          applicant_count: (s.applicant_ids ?? []).length,
        };
      }

      if (action === 'delete') {
        const draft = draftMap[s.exam_session_id];
        return {
          type: 'Delete draft',
          room: draft?.room?.name ?? '—',
          date: draft?.date ?? '—',
          time: draft ? [draft.start_time, draft.end_time].filter(Boolean).join('–') : '—',
          applicant_count: `Release ${draft?.current_count ?? 0} applicant(s)`,
        };
      }

      return {
        type: 'New',
        room: roomMap[s.room_id]?.name ?? s.room_name ?? `Room ${s.room_id ?? '?'}`,
        date: s.date ?? '—',
        time: [s.start_time, s.end_time].filter(Boolean).join('–') || '—',
        applicant_count: (s.applicant_ids ?? []).length,
      };
    });
  }

  const previewRows = $derived.by(() => getScheduleRows(structuredSchedule));

  /** Scroll conversation to bottom after messages change. */
  function scrollToBottom() {
    if (messagesContainer) {
      requestAnimationFrame(() => {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      });
    }
  }

  $effect(() => {
    // Re-run when messages array changes
    messages;
    scrollToBottom();
  });

  async function send() {
    const text = input.trim();
    if (!text) return;
    if (loading) return;

    input = '';
    error = '';
    applyError = '';
    messages = [...messages, { role: 'user', content: text }];
    loading = true;

    try {
      const res = await fetch('/admin/exam-scheduling/schedule-assistant/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf_token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          message: text,
        }),
        credentials: 'same-origin',
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        error = data.message ?? 'Something went wrong. Please try again.';
        messages = messages.filter((m) => m.role !== 'user' || m.content !== text);
        return;
      }

      if (data.reply || data.structured_schedule) {
        hasReplyThisSession = true;
        messages = [
          ...messages,
          {
            role: 'assistant',
            content: data.reply ?? null,
            schedule: data.structured_schedule ?? undefined,
          },
        ];
      }
      if (data.structured_schedule) {
        structuredSchedule = data.structured_schedule;
      }
    } catch (e) {
      error = 'Network error. Please try again.';
      messages = messages.filter((m) => m.role !== 'user' || m.content !== text);
    } finally {
      loading = false;
    }
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  }

  async function applySchedule() {
    if (!structuredSchedule?.sessions?.length || applying) return;
    applying = true;
    applyError = '';

    try {
      const res = await fetch('/admin/exam-scheduling/schedule-assistant/apply-schedule', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf_token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ sessions: structuredSchedule.sessions }),
        credentials: 'same-origin',
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        applyError = data.message ?? 'Failed to apply schedule.';
        showError(applyError);
        return;
      }

      showSuccess(data.message ?? 'Schedule applied successfully!');
      if (typeof window !== 'undefined') {
        localStorage.removeItem('ai_scheduler_messages');
        localStorage.removeItem('ai_scheduler_schedule');
      }

      if (typeof onApplied === 'function') {
        onApplied();
      } else if (data.redirect_url) {
        router.visit(data.redirect_url, { preserveState: false });
      } else {
        router.visit('/admin/exam-scheduling', { preserveState: false });
      }
    } catch (e) {
      applyError = 'Network error. Please try again.';
      showError(applyError);
    } finally {
      applying = false;
    }
  }

  async function resetConversation() {
    if (!confirm('Reset the conversation? This cannot be undone.')) return;
    try {
      const res = await fetch('/admin/exam-scheduling/schedule-assistant/conversation', {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': csrf_token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      if (res.ok) {
        messages = [];
        hasReplyThisSession = false;
        structuredSchedule = null;
        error = '';
        if (typeof window !== 'undefined') {
          localStorage.removeItem('ai_scheduler_messages');
          localStorage.removeItem('ai_scheduler_schedule');
        }
      }
    } catch (e) {
      error = 'Failed to reset conversation.';
    }
  }
</script>

<!-- Main container: flex column to fill dialog height -->
<div class="flex flex-col gap-4 min-h-0">
  <!-- Context header — compact stats bar -->
  <div class="rounded-lg border border-border bg-muted/30 px-4 py-3">
    <div class="flex items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <Sparkles class="w-5 h-5 text-primary shrink-0" />
        <h2 class="text-lg font-bold text-foreground">Schedule with AI</h2>
      </div>
      {#if messages.length > 0}
        <button
          type="button"
          onclick={resetConversation}
          class="text-xs text-muted-foreground hover:text-destructive transition-colors shrink-0"
          title="Reset conversation"
        >
          <Trash2 class="h-4 w-4" />
        </button>
      {/if}
    </div>
    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm mt-1.5">
      <span class="flex items-center gap-1.5">
        <Calendar class="w-4 h-4 text-muted-foreground" />
        <strong>{applicant_count}</strong> applicants to schedule
      </span>
      <span class="text-muted-foreground">{rooms.length} active rooms</span>
      {#if draft_sessions.length > 0}
        <span class="text-muted-foreground">{draft_sessions.length} draft session(s)</span>
      {/if}
    </div>
  </div>

  {#if !openrouter_configured}
    <Card.Root class="border-amber-500/50 bg-amber-500/10 shrink-0">
      <Card.Content class="pt-6">
        <p class="text-sm text-foreground">
          <strong>AI scheduling is currently disabled.</strong>
          Please contact your system administrator to configure the AI service. You can still use manual scheduling.
        </p>
      </Card.Content>
    </Card.Root>
  {/if}

  <!-- Conversation card — grows to fill available space -->
  <Card.Root variant="glass" class="flex flex-col min-h-0 flex-1">
    <Card.Header class="shrink-0">
      <Card.Title class="flex items-center gap-2">
        <MessageSquare class="h-5 w-5" />
        Conversation
      </Card.Title>
      {#if error}
        <p class="text-sm text-destructive rounded-md bg-destructive/10 px-3 py-2 mt-2">{error}</p>
      {/if}
    </Card.Header>
    <Card.Content class="flex flex-col gap-3 min-h-0 flex-1">
      <!-- Messages area — scrollable -->
      <div
        bind:this={messagesContainer}
        class="rounded-lg border border-border bg-muted/30 min-h-[180px] max-h-[45vh] overflow-y-auto p-4 space-y-3 flex-1"
      >
        {#if messages.length === 0}
          <p class="text-sm text-muted-foreground">
            e.g. &quot;I want morning slots only, 9–12, next week&quot; or &quot;Spread applicants across 3 rooms on Monday and Tuesday.&quot;
          </p>
        {:else}
          {#each messages as msg}
            <div class="flex flex-col gap-1 {msg.role === 'user' ? 'items-end' : 'items-start'}">
              <span class="text-xs text-muted-foreground">{msg.role === 'user' ? 'You' : 'Assistant'}</span>
              {#if msg.role === 'user'}
                <div class="rounded-lg px-3 py-2 text-sm max-w-[85%] bg-primary text-primary-foreground">
                  {msg.content}
                </div>
              {:else}
                {#if formatContent(msg.content)}
                  <div class="rounded-lg px-3 py-2 text-sm max-w-[85%] bg-muted whitespace-pre-line">
                    {formatContent(msg.content)}
                  </div>
                {/if}
              {/if}
            </div>
          {/each}
        {/if}
      </div>

      <!-- Input area — pinned to bottom, never scrolls away -->
      <div class="shrink-0 space-y-2">
        <!-- Textarea + Send button row -->
        <div class="flex items-end gap-2">
          <Textarea
            bind:value={input}
            onkeydown={handleKeydown}
            placeholder="Type your message..."
            rows="2"
            class="flex-1 min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm min-h-[44px] resize-none"
            maxlength="4000"
            disabled={loading}
          />
          <Button
            type="button"
            onclick={() => send()}
            disabled={loading || !input.trim()}
            class="min-h-[44px] min-w-[44px] shrink-0"
            title="Send message"
          >
            {#if loading}
              <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            {:else}
              <Send class="h-4 w-4" />
            {/if}
          </Button>
        </div>
      </div>
    </Card.Content>
  </Card.Root>

  <!-- Preview card — only when schedule exists -->
  {#if structuredSchedule?.sessions?.length > 0}
    <Card.Root variant="glass" class="shrink-0">
      <Card.Header>
        <Card.Title>Preview</Card.Title>
        <Card.Description>
          Review the generated schedule. Click Apply to create exam sessions and assign applicants.
        </Card.Description>
      </Card.Header>
      <Card.Content class="space-y-4">
        {#if applyError}
          <p class="text-sm text-destructive rounded-md bg-destructive/10 px-3 py-2">{applyError}</p>
        {/if}
        <div class="rounded-lg border border-border overflow-x-auto">
          <Table.Root>
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-4 py-3">Type</Table.Head>
                <Table.Head class="px-4 py-3">Room</Table.Head>
                <Table.Head class="px-4 py-3">Date</Table.Head>
                <Table.Head class="px-4 py-3">Time</Table.Head>
                <Table.Head class="px-4 py-3">Applicants</Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each previewRows as row}
                <Table.Row>
                  <Table.Cell class="px-4 py-3">{row.type}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{row.room}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{row.date}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{row.time}</Table.Cell>
                  <Table.Cell class="px-4 py-3">{row.applicant_count}</Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </div>
        <Button onclick={applySchedule} disabled={applying} class="min-h-[44px] w-full sm:w-auto">
          {#if applying}
            Applying…
          {:else}
            <CheckCircle2 class="h-4 w-4 mr-2" />
            Apply schedule
          {/if}
        </Button>
      </Card.Content>
    </Card.Root>
  {/if}
</div>
