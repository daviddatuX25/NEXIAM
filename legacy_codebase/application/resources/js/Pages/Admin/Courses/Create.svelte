<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { success } from '@/lib/toast';

  const form = useForm({
    name: '',
    code: '',
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Course created');
    }
  };

  function submitForm(e) {
    e.preventDefault();
    $form.post('/admin/courses');
  }
const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Courses', href: '/admin/courses' }, { label: 'Add Course' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    <div class="flex items-center gap-4">
      <Link href="/admin/courses" class="text-sm text-muted-foreground hover:text-foreground">Back to courses</Link>
    </div>

    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="code" class="text-sm font-medium">Course code</label>
        <Input id="code" bind:value={$form.code} placeholder="e.g., BSIT" required maxlength="20" />
        {#if $form.errors?.code}
          <p class="text-sm text-destructive">{$form.errors.code}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Course name</label>
        <Input id="name" bind:value={$form.name} placeholder="e.g., Bachelor of Science in Information Technology" required />
        {#if $form.errors?.name}
          <p class="text-sm text-destructive">{$form.errors.name}</p>
        {/if}
      </div>

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Creating...' : 'Create Course'}
        </Button>
        <Link href="/admin/courses">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
