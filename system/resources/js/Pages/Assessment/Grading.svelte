<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import ModeBanner from '@/Components/kit/ModeBanner.svelte';

  let { page_state = 'ready', sessions = [], degraded = false } = $props();
</script>

<svelte:head><title>Grading</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Grading' }]}>
  <div class="space-y-4 p-6">
    {#if degraded}<ModeBanner title="Manual grading mode" description="Inference provider unavailable; manual entry remains authoritative." />{/if}
    <E2PageState state={page_state} />
    <ul>{#each sessions as g (g.id)}<li>{g.exam_session?.code ?? g.exam_session_id} — {g.status}</li>{/each}</ul>
  </div>
</StaffLayout>
