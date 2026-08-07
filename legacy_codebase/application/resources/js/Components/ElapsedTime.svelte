<script>
  /** @type {string | null} ISO8601 started_at */
  let { startedAt = null } = $props();

  let now = $state(typeof window !== 'undefined' ? Date.now() : 0);

  $effect(() => {
    if (typeof window === 'undefined') return;
    const interval = setInterval(() => {
      now = Date.now();
    }, 1000);
    return () => clearInterval(interval);
  });

  const elapsed = $derived.by(() => {
    if (!startedAt) return '—';
    const start = new Date(startedAt).getTime();
    if (Number.isNaN(start)) return '—';
    const ms = Math.max(0, now - start);
    const sec = Math.floor(ms / 1000) % 60;
    const min = Math.floor(ms / 60000) % 60;
    const hr = Math.floor(ms / 3600000);
    if (hr > 0) {
      return `${hr}h ${min}m`;
    }
    if (min > 0) {
      return `${min}m ${sec}s`;
    }
    return `${sec}s`;
  });
</script>

<span>{elapsed}</span>
