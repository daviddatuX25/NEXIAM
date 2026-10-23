<script>
  import ActionMenuProvider from '@/Components/kit/ActionMenuProvider.svelte';
  import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
  import EntityRowActions from '@/Components/kit/EntityRowActions.svelte';

  let { inMenu = false } = $props();

  const items = [
    { label: 'Applicant Waitlist', href: '/exams/sessions/waitlist' },
    { label: 'Second Action', onclick: () => {} },
  ];

  const overflow = [
    { label: 'Edit', href: '/edit' },
    { label: 'Delete', destructive: true, onclick: () => {} },
  ];
</script>

{#if inMenu}
  <ActionMenuProvider>
    <div data-testid="menu-container">
      <ActionDropdown {items} />
      <EntityRowActions primaryAction={{ label: 'Primary', href: '/primary' }} overflowItems={overflow} />
    </div>
  </ActionMenuProvider>
{:else}
  <div data-testid="standard-container">
    <ActionDropdown {items} />
    <EntityRowActions primaryAction={{ label: 'Primary', href: '/primary' }} overflowItems={overflow} />
  </div>
{/if}
