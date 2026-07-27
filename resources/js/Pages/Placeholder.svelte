<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';

  let { title = 'Coming Soon', description = 'This section is under development.' } = $props();
</script>

<svelte:head>
  <title>{title} - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout>
  <div class="rounded-lg border border-dashed border-border bg-muted/30 p-12 text-center">
    <h1 class="text-xl font-semibold">{title}</h1>
    <p class="mt-2 text-muted-foreground">{description}</p>
  </div>
</AuthenticatedLayout>
