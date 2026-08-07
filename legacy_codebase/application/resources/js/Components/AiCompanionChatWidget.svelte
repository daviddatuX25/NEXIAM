<script>
  import { MessageSquare, Send, X, Trash2 } from 'lucide-svelte';
  import { Textarea } from '@/Components/ui/textarea';

  let {
    ai_companion_enabled = false,
    csrf_token = '',
    initialMessages = [],
  } = $props();

  let messages = $state([...(initialMessages ?? [])]);
  let input = $state('');
  let loading = $state(false);
  let error = $state('');
  let warning = $state('');
  let expanded = $state(false);
  let clearing = $state(false);

  async function send() {
    const text = input.trim();
    if (!text || loading) return;

    input = '';
    error = '';
    messages = [...messages, { role: 'user', content: text }];
    loading = true;

    try {
      const res = await fetch('/portal/ai-companion/chat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf_token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ message: text }),
        credentials: 'same-origin',
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        error = data.message ?? 'Something went wrong. Please try again.';
        messages = messages.filter((m) => m.role !== 'user' || m.content !== text);
        return;
      }

      if (data.reply) {
        messages = [...messages, { role: 'assistant', content: data.reply }];
      }

      // Check for warnings from server
      if (data.warning?.length) {
        warning = data.warning.length;
      } else if (data.warning?.history) {
        warning = data.warning.history;
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

  async function clearHistory() {
    if (clearing) return;
    clearing = true;
    try {
      const res = await fetch('/portal/ai-companion/clear-history', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf_token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      });
      if (res.ok) {
        messages = [];
      }
    } finally {
      clearing = false;
    }
  }
</script>

{#if ai_companion_enabled}
  {#if expanded}
    <div
      class="fixed bottom-6 right-6 z-50 w-[380px] max-h-[520px] bg-card border border-border rounded-xl shadow-xl backdrop-blur-sm flex flex-col overflow-hidden transition-all duration-200 ease-out"
    >
      <!-- Header -->
      <div class="flex items-center justify-between px-4 py-3 border-b">
        <h3 class="text-base font-semibold">Cat-Bot</h3>
        <div class="flex items-center gap-1">
          {#if messages.length > 0}
            <button
              type="button"
              onclick={() => {
                if (confirm('Clear history: This will delete all messages. Continue?')) {
                  clearHistory();
                }
              }}
              disabled={clearing}
              class="p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent transition-colors disabled:opacity-50"
              aria-label="Clear chat history"
            >
              <Trash2 class="h-5 w-5" />
            </button>
          {/if}
          <button
            type="button"
            onclick={() => (expanded = false)}
            class="p-1.5 rounded-md text-muted-foreground hover:text-foreground hover:bg-accent transition-colors"
            aria-label="Close chat"
          >
            <X class="h-5 w-5" />
          </button>
        </div>
      </div>

      <!-- Messages Area -->
      <div class="flex-1 overflow-y-auto min-h-[280px] max-h-[360px] p-3 bg-muted/30">
        {#if error}
          <p class="text-sm text-destructive rounded-md bg-destructive/10 px-3 py-2 mb-2">{error}</p>
        {/if}

        {#if messages.length === 0}
          <div class="text-center py-8">
            <p class="text-base font-semibold">Start a conversation</p>
            <p class="text-sm text-muted-foreground mt-1">
              Ask about your results and course fit. Advice is based on your scores and the data we have.
            </p>
            <p class="text-xs text-muted-foreground mt-3">
              Please do not share sensitive personal information like passwords or financial details.
            </p>
          </div>
        {:else}
          {#each messages as msg}
            <div class="flex flex-col gap-1 {msg.role === 'user' ? 'items-end' : 'items-start'} animate-in fade-in duration-150">
              <span class="text-xs text-muted-foreground">{msg.role === 'user' ? 'You' : 'Advisor'}</span>
              <div
                class="rounded-lg px-3 py-2 text-sm max-w-[85%] {msg.role === 'user'
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted'}"
              >
                {msg.content}
              </div>
            </div>
          {/each}
          {#if warning}
            <p class="text-xs text-amber-600 dark:text-amber-400 mt-2 bg-amber-50 dark:bg-amber-900/20 rounded px-2 py-1">{warning}</p>
          {/if}
        {/if}
      </div>

      <!-- Input Area -->
      <div class="flex items-center gap-2 px-4 py-3 border-t bg-card">
        <Textarea
          bind:value={input}
          onkeydown={handleKeydown}
          placeholder="Type your question..."
          rows="2"
          class="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm min-h-[44px] resize-none"
          maxlength="2000"
          disabled={loading}
        />
        <button
          type="button"
          onclick={send}
          disabled={loading || !input.trim()}
          class="min-h-[44px] min-w-[44px] inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground px-4 py-2 text-sm font-medium hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {#if loading}
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          {:else}
            <Send class="h-4 w-4" />
          {/if}
        </button>
      </div>
    </div>
  {:else}
    <button
      type="button"
      onclick={() => (expanded = true)}
      title="Cat-Bot"
      class="fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full bg-primary text-primary-foreground shadow-lg flex items-center justify-center hover:scale-105 transition-transform duration-100"
      aria-label="Open chat"
    >
      <MessageSquare class="h-6 w-6" />
    </button>
  {/if}
{/if}