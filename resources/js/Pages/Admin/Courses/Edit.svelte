<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import Switch from '@/Components/ui/switch/switch.svelte';
  import { success } from '@/lib/toast';

  const { course } = $props();

  const form = useForm({
    name: course.name,
    code: course.code,
    is_active: course.is_active,
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Course updated');
    }
  };

  function submitForm(e) {
    e.preventDefault();
    $form.put(`/admin/courses/${course.id}`);
  }

  function doRestore() {
    router.post(`/admin/courses/${course.id}/restore`, {}, {
      onSuccess: () => router.reload(),
    });
  }

  function doDelete() {
    if (confirm('Delete this course? This action cannot be undone.')) {
      router.delete(`/admin/courses/${course.id}`, { onSuccess: () => window.location.href = '/admin/courses' });
    }
  }
const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Courses', href: '/admin/courses' }, { label: 'Edit Course' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    <div class="flex items-center gap-4">
      <Link href="/admin/courses" class="text-sm text-muted-foreground hover:text-foreground">Back to courses</Link>
    </div>

    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="code" class="text-sm font-medium">Course code</label>
        <Input id="code" bind:value={$form.code} required maxlength="20" />
        {#if $form.errors?.code}
          <p class="text-sm text-destructive">{$form.errors.code}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Course name</label>
        <Input id="name" bind:value={$form.name} required />
        {#if $form.errors?.name}
          <p class="text-sm text-destructive">{$form.errors.name}</p>
        {/if}
      </div>

      <div class="flex items-center gap-3">
        <Switch
          checked={$form.is_active}
          onCheckedChange={(checked) => $form.is_active = checked}
          aria-label="Toggle active"
        />
        <span class="text-sm font-medium">{$form.is_active ? 'Active' : 'Inactive'}</span>
      </div>
      <p class="text-xs text-muted-foreground">Inactive courses are hidden from the public application form.</p>

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Saving...' : 'Save'}
        </Button>
        <Link href="/admin/courses">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
        {#if course?.deleted_at}
          <Button type="button" variant="outline" onclick={doRestore}>
            Restore
          </Button>
        {/if}
        <Button type="button" variant="destructive" onclick={doDelete}>
          Delete
        </Button>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
