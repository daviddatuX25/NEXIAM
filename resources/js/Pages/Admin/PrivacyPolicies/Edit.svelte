<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Switch } from '@/Components/ui/switch';
  import { success, error } from '@/lib/toast';

  let { policy } = $props();

  const form = useForm({
    title: policy.title ?? '',
    content: policy.content ?? '',
    is_active: policy.is_active ?? false,
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Privacy policy updated');
    } else {
      error('Please fix the errors in the form');
    }
  };

  function submitForm(e) {
    e.preventDefault();
    $form.put(`/admin/privacy-policies/${policy.id}`);
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/admin/setup' },
    { label: 'Privacy Policies', href: '/admin/privacy-policies' },
    { label: 'Edit' },
  ];
</script>

<svelte:head>
  <title>Edit Privacy Policy - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout {breadcrumbs}>
  <div class="mx-auto max-w-2xl space-y-6">
    <form onsubmit={submitForm} class="space-y-6 rounded-lg border border-border bg-card p-6">
      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Edit Privacy Policy</h3>

        <div class="space-y-2">
          <label for="title" class="text-sm font-medium">Title *</label>
          <Input id="title" bind:value={$form.title} placeholder="e.g., Privacy Policy v1.0" required />
          {#if $form.errors?.title}
            <p class="text-sm text-destructive">{$form.errors.title}</p>
          {/if}
        </div>

        <div class="space-y-2">
          <label for="content" class="text-sm font-medium">Content *</label>
          <textarea
            id="content"
            bind:value={$form.content}
            class="flex min-h-[300px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            placeholder="Enter the privacy policy content here."
            required
          ></textarea>
          {#if $form.errors?.content}
            <p class="text-sm text-destructive">{$form.errors.content}</p>
          {/if}
        </div>

        <div class="rounded-md border border-border bg-muted/50 p-4">
          <div class="flex items-center justify-between">
            <div class="space-y-1">
              <label for="is_active" class="text-sm font-medium">Set as active</label>
              <p class="text-xs text-muted-foreground">Only one policy can be active at a time. This will be shown to applicants.</p>
            </div>
            <Switch id="is_active" bind:checked={$form.is_active} />
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-4 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Saving...' : 'Save Changes'}
        </Button>
        <Link href="/admin/privacy-policies">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
