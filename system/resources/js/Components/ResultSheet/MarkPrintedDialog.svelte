<script>
  import { Button } from '@/Components/ui/button';
  import { CheckSquare, X } from 'lucide-svelte';
  import { usePage } from '@inertiajs/svelte';
  import { success, error } from '@/lib/toast';
  import { csrfToken, newIdempotencyKey } from '@/lib/utils';

  let {
    open = false,
    documentIds = [],
    count = 1,
    onclose = () => {},
    onmarked = () => {},
  } = $props();

  const page = usePage();
  let loading = $state(false);

  async function handleMark() {
    if (documentIds.length === 0) {
      onclose();
      return;
    }

    loading = true;
    const token = csrfToken() || page.props.csrf_token || '';

    try {
      const res = await fetch('/release/print/mark-printed', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ document_ids: documentIds, printed: true, idempotency_key: newIdempotencyKey() }),
      });

      if (res.ok) {
        success(`Marked ${count} result sheet(s) as printed.`);
        onmarked();
      } else {
        error('Failed to update printed status.');
      }
    } catch {
      error('Network error updating printed status.');
    } finally {
      loading = false;
      onclose();
    }
  }
</script>

{#if open}
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog" aria-modal="true">
    <div class="w-full max-w-md space-y-4 rounded-xl border border-border bg-card p-6 shadow-xl animate-in fade-in zoom-in-95 duration-150">
      <div class="flex items-start justify-between">
        <div class="flex items-center gap-2.5">
          <div class="rounded-full bg-primary/10 p-2 text-primary">
            <CheckSquare class="size-5" />
          </div>
          <div>
            <h3 class="text-base font-semibold text-foreground">Mark as printed?</h3>
            <p class="text-xs text-muted-foreground">Keep staff records synchronized with physical paper</p>
          </div>
        </div>
        <button type="button" class="rounded-md p-1 text-muted-foreground hover:bg-muted" onclick={onclose} aria-label="Close">
          <X class="size-4" />
        </button>
      </div>

      <p class="text-sm text-muted-foreground">
        Confirm that <strong class="text-foreground">{count}</strong> result sheet{count === 1 ? '' : 's'} were physically printed. This does not record applicant handover.
      </p>

      <div class="flex items-center justify-end gap-2 pt-2">
        <Button variant="outline" size="sm" onclick={onclose} disabled={loading}>
          Dismiss
        </Button>
        <Button size="sm" class="gap-1.5 font-semibold" onclick={handleMark} disabled={loading}>
          <CheckSquare class="size-4" />
          {loading ? 'Marking…' : 'Mark as printed'}
        </Button>
      </div>
    </div>
  </div>
{/if}
