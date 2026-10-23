<script>
  import { setContext } from 'svelte';

  let { children } = $props();

  setContext('inActionMenu', true);
</script>

{@render children?.()}
