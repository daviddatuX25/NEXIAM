<script>
  /**
   * Applicant action hub (staff).
   * Server-authored destinations for the current duty and scope. The server
   * decides what this user may do for this applicant; this page only renders
   * what was authorized. Scanning never executes any action automatically.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';
  import { ArrowRight, Inbox } from 'lucide-svelte';

  let { page_state = 'ready', attempt = { id: null, reference_code: '' }, destinations = [] } = $props();
</script>

<svelte:head><title>Applicant action hub</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Applicant action hub' }]}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if page_state !== 'ready'}
      <E2PageState state={page_state} />
    {:else}
      <PageHeaderBar
        title="Applicant action hub"
        description="Server-authored destinations for your current duty and scope."
      />

      <Card.Root>
        <Card.Header class="pb-3">
          <Card.Title class="text-lg">Applicant</Card.Title>
          <Card.Description class="font-mono text-xs">
            {attempt.reference_code || 'No reference'}
          </Card.Description>
        </Card.Header>
        <Card.Content class="pt-4">
          {#if destinations.length === 0}
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
              <Inbox class="size-4" />
              No action is available for your current duty or scope.
            </div>
          {:else}
            <ul class="divide-y">
              {#each destinations as destination (destination.key)}
                <li>
                  <Link
                    href={destination.url}
                    class="flex items-center justify-between gap-3 py-3 text-sm font-medium hover:text-primary"
                  >
                    {destination.label}
                    <ArrowRight class="size-4 shrink-0 text-muted-foreground" />
                  </Link>
                </li>
              {/each}
            </ul>
          {/if}
        </Card.Content>
      </Card.Root>

      <p class="text-xs text-muted-foreground">
        Scanning never executes any of these actions automatically.
      </p>
    {/if}
  </div>
</StaffLayout>
