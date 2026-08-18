<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Result Sheet Templates', href: '/setup/result-templates' },
    { label: 'Create' },
  ];
</script>

<svelte:head><title>New Result Sheet Template · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="p-6">
    <p class="text-sm text-muted-foreground">Template form (placeholder guide + analyzer + preview in progress).</p>
  </div>
</StaffLayout>
