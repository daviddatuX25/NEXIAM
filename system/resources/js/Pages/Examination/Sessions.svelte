<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';

  let { page_state = 'ready', sessions = [] } = $props();
</script>

<svelte:head><title>Exam sessions</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Exams' }]}>
  <div class="p-6"><E2PageState state={page_state} />
    <ul class="mt-4">{#each sessions as s (s.id)}<li>{s.code} — {s.status}</li>{/each}</ul>
  </div>
</StaffLayout>
