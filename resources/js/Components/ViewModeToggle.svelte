<script>
  import * as ToggleGroup from '@/Components/ui/toggle-group';
  import { MonitorSmartphone, Table2, LayoutGrid } from 'lucide-svelte';

  let { value = $bindable('responsive') } = $props();
</script>

<ToggleGroup.Root
  type="single"
  bind:value
  variant="outline"
  size="sm"
  class="min-h-[36px] rounded-md border border-border"
  aria-label="View layout"
>
  <ToggleGroup.Item value="responsive" aria-label="Auto (responsive)" class="min-h-[36px] px-2" title="Auto">
    <MonitorSmartphone class="h-4 w-4" />
  </ToggleGroup.Item>
  <ToggleGroup.Item value="table" aria-label="Table view" class="min-h-[36px] px-2" title="Table">
    <Table2 class="h-4 w-4" />
  </ToggleGroup.Item>
  <ToggleGroup.Item value="cards" aria-label="Card view" class="min-h-[36px] px-2" title="Cards">
    <LayoutGrid class="h-4 w-4" />
  </ToggleGroup.Item>
</ToggleGroup.Root>