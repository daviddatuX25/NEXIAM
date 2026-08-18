<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';

  let { templates = [] } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Result Sheet Templates' },
  ];
</script>

<svelte:head><title>Result Sheet Templates · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="p-6">
    <pre>{JSON.stringify(templates, null, 2)}</pre>
  </div>
</StaffLayout>
