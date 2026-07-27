<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import SessionRoster from '@/Components/SessionRoster.svelte';

  let { session, applicants = [], stats = {}, permissions = {}, breadcrumbParent = { label: 'Test Administration', href: '/admin/test-admin/sessions' } } = $props();

  const breadcrumbs = $derived([
    breadcrumbParent,
    { label: session?.id ? 'Session #' + session.id : 'Session' }
  ]);
</script>

<svelte:head>
  <title>Session Roster - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <SessionRoster {session} {applicants} {stats} {permissions} />
</AuthenticatedLayout>
