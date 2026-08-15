<script>
  /**
   * Registrar status search + eligibility card (A3).
   * Students may check their status at the Registrar counter without phones.
   * Status facts only — assessment scores and counselor notes stay Guidance-owned.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Input } from '@/Components/ui/input';
  import { Link, router } from '@inertiajs/svelte';
  import { CheckCircle2, Search, XCircle } from 'lucide-svelte';

  let {
    page_state = 'search',
    query = '',
    results = [],
    card = null,
  } = $props();

  let searchTerm = $state(query);

  function runSearch() {
    router.get('/registrar/enrollment-eligibility', { q: searchTerm }, {
      preserveState: true,
      replace: true,
    });
  }

  const factLabels = {
    accepted: 'Application accepted',
    grading_finalized: 'Exam completed and graded',
    result_released: 'Result released',
    advised: 'Consultation advised',
  };

  function formatDate(iso) {
    if (!iso) return null;
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }
</script>

<svelte:head><title>Enrollment eligibility</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Enrollment eligibility' }]}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Enrollment eligibility"
      description="Search a student's status. Eligibility is computed from accepted + graded + released + advised — never hand-set."
    />

    {#if page_state === 'search'}
      <Card.Root>
        <Card.Content class="space-y-4 p-4">
          <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
              <Input
                bind:value={searchTerm}
                placeholder="Name, reference number, or email"
                onkeydown={(event) => {
                  if (event.key === 'Enter') runSearch();
                }}
              />
            </div>
            <Button type="button" class="min-h-10" onclick={runSearch}>
              <Search class="size-4" />
              Search
            </Button>
          </div>

          {#if results.length === 0}
            <p class="text-sm text-muted-foreground">
              {query ? 'No applicants match that search.' : 'Search by name, reference number, or email.'}
            </p>
          {:else}
            <ul class="divide-y">
              {#each results as row (row.id)}
                <li class="flex items-center justify-between gap-3 py-3">
                  <div class="min-w-0">
                    <p class="font-medium">{row.name}</p>
                    <p class="font-mono text-xs text-muted-foreground">{row.reference_number}</p>
                  </div>
                  <Link href={`/registrar/enrollment-eligibility/${row.id}`}>
                    <Button variant="outline" class="min-h-10">View status</Button>
                  </Link>
                </li>
              {/each}
            </ul>
          {/if}
        </Card.Content>
      </Card.Root>
    {:else if page_state === 'card' && card}
      <div class="space-y-4">
        <Link href="/registrar/enrollment-eligibility">
          <Button variant="ghost" class="-ml-3 min-h-9">← Back to search</Button>
        </Link>

        <Card.Root class={card.eligible ? 'overflow-hidden border-primary/30 shadow-sm' : 'overflow-hidden shadow-sm'}>
          <Card.Header class="border-b bg-muted/20 pb-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <Card.Title class="text-lg">{card.name}</Card.Title>
                <Card.Description class="font-mono text-xs">{card.reference_number}</Card.Description>
              </div>
              <Badge variant={card.eligible ? 'default' : 'secondary'}>
                {card.eligible ? 'Eligible for enrollment' : 'Not yet eligible'}
              </Badge>
            </div>
          </Card.Header>
          <Card.Content class="space-y-3 pt-5">
            <ul class="space-y-2">
              {#each Object.entries(factLabels) as [key, label]}
                <li class="flex items-center justify-between gap-3 text-sm">
                  <span class="text-muted-foreground">{label}</span>
                  {#if card.facts?.[key]}
                    <span class="inline-flex items-center gap-1.5 font-medium">
                      <CheckCircle2 class="size-4 text-primary" />
                      {key === 'result_released' && card.released_at ? formatDate(card.released_at) : 'Done'}
                    </span>
                  {:else}
                    <span class="inline-flex items-center gap-1.5 font-medium text-muted-foreground">
                      <XCircle class="size-4" />
                      Missing
                    </span>
                  {/if}
                </li>
              {/each}
            </ul>

            {#if !card.eligible && card.missing.length > 0}
              <div class="border-border rounded-md border bg-muted/30 p-3 text-sm">
                <p class="font-medium">What's still missing</p>
                <p class="text-muted-foreground">
                  {card.missing.map((key) => factLabels[key]).join(' · ')}
                </p>
              </div>
            {/if}
          </Card.Content>
        </Card.Root>

        <p class="text-xs text-muted-foreground">
          Status facts only. Assessment scores and counselor notes are not shown outside Guidance. Every lookup is recorded.
        </p>
      </div>
    {/if}
  </div>
</StaffLayout>
