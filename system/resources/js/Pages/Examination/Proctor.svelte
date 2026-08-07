<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';

  let { page_state = 'ready', duties = [] } = $props();
</script>

<svelte:head><title>Proctor</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Proctor' }]}>
  <div class="p-6"><E2PageState state={page_state} />
    <ul>{#each duties as d (d.id)}<li>Session {d.exam_session?.code ?? d.exam_session_id}</li>{/each}</ul>
  </div>
</StaffLayout>
