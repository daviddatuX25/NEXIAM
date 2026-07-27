<script>
  import { Link, usePage } from '@inertiajs/svelte';
  import PortalLayout from '@/Layouts/PortalLayout.svelte';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Textarea } from '@/Components/ui/textarea';
  import { MessageSquare, Send, ArrowLeft, Trash2 } from 'lucide-svelte';

  let { csrf_token = '', messages: initialMessages = [] } = $props();

  let messages = $state(initialMessages ?? []);
  let input = $state('');
  let loading = $state(false);
  let error = $state('');
  let warning = $state('');

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

  let clearing = $state(false);
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

<svelte:head>
  <title>Cat-Bot - SecureCAT</title>
</svelte:head>

<PortalLayout>
  <div class="mx-auto max-w-2xl space-y-4">
    <div class="flex items-center justify-between gap-3">
      <Link
        href="/portal/dashboard"
        class="text-sm text-muted-foreground hover:text-foreground flex items-center gap-1 min-h-[44px]"
      >
        <ArrowLeft class="h-4 w-4" />
        Back to dashboard
      </Link>
      {#if messages.length > 0}
        <Button type="button" variant="ghost" size="sm" onclick={clearHistory} disabled={clearing} class="min-h-[44px] text-muted-foreground">
          <Trash2 class="mr-1 h-4 w-4" />
          {clearing ? 'Clearing…' : 'Clear history'}
        </Button>
      {/if}
    </div>

    <Card.Root>
      <Card.Header>
        <Card.Title class="flex items-center gap-2">
          <MessageSquare class="h-5 w-5" />
          Cat-Bot
        </Card.Title>
        <Card.Description>
          Ask about your results and course fit. Advice is based on your scores and the data we have.
        </Card.Description>
        <p class="text-xs text-muted-foreground">
          Please do not share sensitive personal information like passwords or financial details.
        </p>
      </Card.Header>
      <Card.Content class="space-y-4">
        {#if error}
          <p class="text-sm text-destructive rounded-md bg-destructive/10 px-3 py-2">{error}</p>
        {/if}

        <div class="rounded-lg border border-border bg-muted/30 min-h-[200px] max-h-[360px] overflow-y-auto p-4 space-y-3">
          {#if messages.length === 0}
            <p class="text-sm text-muted-foreground">Send a message to start. e.g. &quot;What course fits my scores?&quot;</p>
          {:else}
            {#each messages as msg}
              <div class="flex flex-col gap-1 {msg.role === 'user' ? 'items-end' : 'items-start'}">
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

        <div class="flex gap-2">
          <Textarea
            bind:value={input}
            onkeydown={handleKeydown}
            placeholder="Type your question..."
            rows="2"
            class="flex-1 min-h-[44px] resize-none"
            maxlength="2000"
            disabled={loading}
          />
          <Button type="button" onclick={send} disabled={loading || !input.trim()} class="min-h-[44px] shrink-0">
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
        <p class="text-xs text-muted-foreground">Max 2000 characters per message.</p>
      </Card.Content>
    </Card.Root>
  </div>
</PortalLayout>
