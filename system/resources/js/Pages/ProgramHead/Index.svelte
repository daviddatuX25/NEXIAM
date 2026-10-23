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
    is_dean = false,
    department = null,
  } = $props();

  const showUrl = (code) =>
    typeof route === 'function'
      ? route('program-head.show', { program: code })
      : `/staff/programs/${code}`;

  const breadcrumbs = $derived([
    { label: is_dean || department ? (department?.code ? `My Department (${department.code})` : 'My Department') : 'My Program', href: '/staff/programs' },
    { label: 'My Programs' },
  ]);
  const programHeadContext = $derived.by(() => {
    const safetyKey = `${page_state}:${programs?.length ?? 0}:${cycle ?? 'none'}`;

    return {
      entityKey: 'program-head-hub',
      safetyKey,
      freshness: 'current',
      pageState: page_state,
      programsCount: programs?.length ?? 0,
      cycle: cycle ?? null,
      firstProgramCode: programs?.[0]?.code ?? null,
    };
  });
</script>

<svelte:head><title>My Programs</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6" data-tour="program-head-assigned-programs">
    {#if page_state !== 'ready'}
      <E2PageState state={page_state} />
    {:else}
      <PageHeaderBar
        title={department?.name ? `${department.name} (${department.code}) — My Programs` : 'My Programs'}
        description="Review applicant pipelines, qualification cohorts, and enrollment quotas for your assigned academic programs."
        tourKey="program-head-overview"
        context={programHeadContext}
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
        <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-tour="program-head-programs-grid">
          {#each programs as program (program.code)}
            <li class="glass-panel flex flex-col rounded-2xl p-5 transition-shadow hover:shadow-md" data-tour="program-head-program-card">
              <p class="font-mono text-xs font-semibold uppercase tracking-wider text-muted-foreground">{program.code}</p>
              <h2 class="mt-1 text-lg font-semibold text-foreground">{program.name}</h2>
              <div class="mt-4 flex flex-wrap gap-2">
                <Link href={showUrl(program.code)}>
                  <Button class="min-h-10 gap-2" data-tour="program-head-open-program">
                    <FolderOpen class="size-4" />
                    Open program
                  </Button>
                </Link>
                {#if program.public_details_href}
                  <Link href={program.public_details_href}>
                    <Button variant="outline" class="min-h-10">Edit public details</Button>
                  </Link>
                {/if}
              </div>
            </li>
          {/each}
        </ul>
      {/if}
    {/if}
  </div>
</StaffLayout>
