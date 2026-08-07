<script>
  import { Link, useForm, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import Switch from '@/Components/ui/switch/switch.svelte';
  import { success } from '@/lib/toast';

  let { room } = $props();

  const form = useForm({
    name: room?.name ?? '',
    building: room?.building ?? '',
    floor: room?.floor ?? '',
    capacity: String(room?.capacity ?? ''),
    is_active: room?.is_active ?? true,
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Room updated');
    }
  };

  function submitForm(e) {
    e.preventDefault();
    $form.transform((data) => ({
      ...data,
      capacity: parseInt(data.capacity, 10),
    }));
    $form.put(`/admin/rooms/${room.id}`);
  }

  function doRestore() {
    router.post(`/admin/rooms/${room.id}/restore`, {}, {
      onSuccess: () => router.reload(),
    });
  }

  function doDelete() {
    if (confirm('Delete this room? This action cannot be undone.')) {
      router.delete(`/admin/rooms/${room.id}`, { onSuccess: () => window.location.href = '/admin/rooms' });
    }
  }
</script>

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

  <div class="flex items-center gap-3">
    <Switch
      checked={$form.is_active}
      onCheckedChange={(checked) => $form.is_active = checked}
      aria-label="Toggle active"
    />
    <span class="text-sm font-medium">{$form.is_active ? 'Active' : 'Inactive'}</span>
  </div>
  <p class="text-xs text-muted-foreground">Inactive rooms are hidden from scheduling.</p>

  <div class="flex gap-2 pt-4">
    <Button type="submit" disabled={$form.processing}>
      {$form.processing ? 'Saving...' : 'Save'}
    </Button>
    <Link href="/admin/rooms">
      <Button type="button" variant="outline">Cancel</Button>
    </Link>
    {#if room?.deleted_at}
      <Button type="button" variant="outline" onclick={doRestore}>
        Restore
      </Button>
    {/if}
    <Button type="button" variant="destructive" onclick={doDelete}>
      Delete
    </Button>
  </div>
</form>
