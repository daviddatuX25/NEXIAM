<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';

  let { template = {} } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Result Sheet Templates', href: '/setup/result-templates' },
    { label: template.name ?? 'Edit' },
  ];
</script>

<svelte:head><title>Edit Result Sheet Template · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="p-6">
    <p class="text-sm text-muted-foreground">Template edit form (placeholder guide + analyzer + preview in progress).</p>
  </div>
</StaffLayout>
