<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';

  let { page_state = 'ready', attempts = [] } = $props();
</script>

<svelte:head><title>Consultation</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Consultation' }]}>
  <div class="p-6"><E2PageState state={page_state} />
    <ul>{#each attempts as a (a.id)}<li>{a.reference_code}</li>{/each}</ul>
  </div>
</StaffLayout>
