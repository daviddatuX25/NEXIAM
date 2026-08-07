<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import EditForm from './EditForm.svelte';

  let { room } = $props();

  const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Rooms', href: '/admin/rooms' }, { label: 'Edit' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    {#key room?.id}
      <EditForm {room} />
    {/key}
  </div>
</AuthenticatedLayout>
