<script>
  /**
   * Program Head workspace — assigned-program Index.
   * Server-authored scope only: the UI renders the programs the server
   * assigns; it never derives, filters, or hides based on roles.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Button } from '@/Components/ui/button';
  import { Link } from '@inertiajs/svelte';
  import { FolderOpen } from 'lucide-svelte';

  let {
    page_state = 'denied',
    programs = [],
    cycle = null,
  } = $props();

  const showUrl = (code) =>
    typeof route === 'function'
      ? route('program-head.show', { program: code })
      : `/staff/programs/${code}`;

  const breadcrumbs = [
    { label: 'Program head', href: '/staff/programs' },
    { label: 'My programs' },
  ];
</script>

<svelte:head><title>My Programs</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if page_state !== 'ready'}
      <E2PageState state={page_state} />
    {:else}
      <PageHeaderBar
        title="My Programs"
        description="Assigned-program workspace — scope is limited to your assigned programs."
      />

      {#if cycle}
        <p class="text-sm text-muted-foreground">
          Active cycle: <span class="font-mono text-xs font-semibold text-foreground">{cycle}</span>
        </p>
      {/if}

      {#if programs.length === 0}
        <p class="text-muted-foreground py-12 text-center">
          No programs are assigned to you yet. Contact the registrar to scope your workspace.
        </p>
      {:else}
        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {#each programs as program (program.code)}
            <li class="glass-panel flex flex-col rounded-2xl p-5 transition-shadow hover:shadow-md">
              <p class="font-mono text-xs font-semibold uppercase tracking-wider text-muted-foreground">{program.code}</p>
              <h2 class="mt-1 text-lg font-semibold text-foreground">{program.name}</h2>
              <div class="mt-4">
                <Link href={showUrl(program.code)}>
                  <Button class="min-h-10 gap-2">
                    <FolderOpen class="size-4" />
                    Open program
                  </Button>
                </Link>
              </div>
            </li>
          {/each}
        </ul>
      {/if}
    {/if}
  </div>
</StaffLayout>
