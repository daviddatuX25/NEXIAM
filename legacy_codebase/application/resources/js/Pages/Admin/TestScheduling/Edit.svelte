<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link } from '@inertiajs/svelte';
  import EditForm from './EditForm.svelte';

  let { session, rooms = [], proctors = [] } = $props();
const breadcrumbs = [{ label: 'Exam Scheduling', href: '/admin/exam-scheduling' }, { label: 'Edit' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    {#if session?.id}
      {#key session.id}
        <EditForm {session} {rooms} {proctors} />
      {/key}
    {/if}
  </div>
</AuthenticatedLayout>
