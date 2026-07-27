<script lang="ts">
  import { Link } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { ChevronLeft, ChevronRight } from 'lucide-svelte';

  let {
    data,
    variant = 'table',
    class: className = ''
  } = $props();
</script>

{#if data != null && data.last_page > 1}
  <div class="mt-4 pt-4 border-t border-border/50 flex items-center {variant === 'centered' ? 'justify-center' : 'justify-between'} {className}">
    {#if variant === 'table'}
      <div class="text-sm text-muted-foreground hidden sm:block">
        Showing
        <span class="font-medium">{data.from || 0}</span>
        to
        <span class="font-medium">{data.to || 0}</span>
        of
        <span class="font-medium">{data.total}</span>
        results
      </div>
    {/if}

    <nav aria-label="Pagination Navigation" class="flex items-center space-x-2">
      <Link href={data.prev_page_url} preserveState preserveScroll disabled={!data.prev_page_url}>
        <Button variant="outline" size="sm" class="h-8 w-8 p-0" disabled={!data.prev_page_url}>
          <span class="sr-only">Previous</span>
          <ChevronLeft class="h-4 w-4" />
        </Button>
      </Link>

      <div class="text-sm font-medium">
        Page {data.current_page} of {data.last_page}
      </div>

      <Link href={data.next_page_url} preserveState preserveScroll disabled={!data.next_page_url}>
        <Button variant="outline" size="sm" class="h-8 w-8 p-0" disabled={!data.next_page_url}>
          <span class="sr-only">Next</span>
          <ChevronRight class="h-4 w-4" />
        </Button>
      </Link>
    </nav>
  </div>
{/if}
