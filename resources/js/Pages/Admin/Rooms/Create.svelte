<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { success } from '@/lib/toast';

  const form = useForm({
    name: '',
    building: '',
    floor: '',
    capacity: '',
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Room created');
    }
  };

  function submitForm(e) {
    e.preventDefault();
    $form.transform((data) => ({
      ...data,
      capacity: parseInt(data.capacity, 10) || 0,
    }));
    $form.post('/admin/rooms');
  }
const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Rooms', href: '/admin/rooms' }, { label: 'Add Room' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    <div class="flex items-center gap-4">
      <Link href="/admin/rooms" class="text-sm text-muted-foreground hover:text-foreground">Back to rooms</Link>
    </div>

    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Room name</label>
        <Input id="name" bind:value={$form.name} placeholder="e.g., Room 101" required />
        {#if $form.errors?.name}
          <p class="text-sm text-destructive">{$form.errors.name}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="building" class="text-sm font-medium">Building</label>
        <Input id="building" bind:value={$form.building} placeholder="e.g., ITBR" required />
        {#if $form.errors?.building}
          <p class="text-sm text-destructive">{$form.errors.building}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="floor" class="text-sm font-medium">Floor (optional)</label>
        <Input id="floor" bind:value={$form.floor} placeholder="e.g., 2nd Floor" />
        {#if $form.errors?.floor}
          <p class="text-sm text-destructive">{$form.errors.floor}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="capacity" class="text-sm font-medium">Capacity</label>
        <Input
          id="capacity"
          type="number"
          min="1"
          bind:value={$form.capacity}
          placeholder="Max examinees"
          required
        />
        {#if $form.errors?.capacity}
          <p class="text-sm text-destructive">{$form.errors.capacity}</p>
        {/if}
      </div>

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Creating...' : 'Create Room'}
        </Button>
        <Link href="/admin/rooms">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
