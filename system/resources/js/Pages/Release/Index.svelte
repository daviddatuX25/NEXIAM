<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';

  let { page_state = 'ready' } = $props();
</script>

<svelte:head><title>Release</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Release' }]}>
  <div class="p-6"><E2PageState state={page_state} /><p class="text-sm text-muted-foreground">Release actions use server confirmation and after-commit notifications.</p></div>
</StaffLayout>
