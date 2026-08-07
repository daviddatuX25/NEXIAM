<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Table from '@/Components/ui/table';
  import { Link, useForm } from '@inertiajs/svelte';

  let {
    cycle = null,
    programs = [],
    quotas = [],
    is_program_head = false,
  } = $props();

  const form = useForm({
    program_id: programs[0]?.id ? String(programs[0].id) : '',
    academic_cycle_id: cycle?.id ? String(cycle.id) : '',
    slots: 40,
    notice: '',
  });

  function submit() {
    form.post('/setup/quotas', {
      onSuccess: () => form.reset('notice'),
    });
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Quotas' },
  ];
</script>

<svelte:head><title>Quotas · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Program quotas"
      description={is_program_head
        ? 'Set slots for your assigned program(s) before result release.'
        : 'Test Administrator path — adjust quotas and leave a notice for the Program Head.'}
    >
      {#snippet actions()}
        <Link href="/setup"><Button variant="outline" size="sm">Back to readiness</Button></Link>
      {/snippet}
    </PageHeaderBar>

    {#if cycle}
      <p class="text-sm text-muted-foreground">
        Quotas for
        <span class="font-medium text-foreground">{cycle.staff_label || cycle.name}</span>
        ({cycle.school_year || cycle.code})
      </p>
    {/if}

    {#if programs.length === 0}
      <div class="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground">
        No programs available in your scope. Ask HR to assign program codes on your staff profile.
      </div>
    {:else}
      <form
        class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2"
        onsubmit={(e) => {
          e.preventDefault();
          submit();
        }}
      >
        <div class="space-y-1.5">
          <Label for="program">Program</Label>
          <select
            id="program"
            bind:value={form.program_id}
            class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
          >
            {#each programs as p}
              <option value={String(p.id)}>{p.code} — {p.name}</option>
            {/each}
          </select>
        </div>
        <div class="space-y-1.5">
          <Label for="slots">Slots</Label>
          <Input id="slots" type="number" min="0" bind:value={form.slots} class="min-h-10" />
        </div>
        <div class="space-y-1.5 sm:col-span-2">
          <Label for="notice">
            {is_program_head ? 'Note (optional)' : 'Notice to Program Head (recommended)'}
          </Label>
          <textarea
            id="notice"
            bind:value={form.notice}
            rows="3"
            placeholder={is_program_head
              ? 'Optional note for your records…'
              : 'Explain the change so the Program Head sees why slots moved…'}
            class="border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm"
          ></textarea>
        </div>
        <Button type="submit" class="min-h-10 w-fit" disabled={form.processing}>Save quota</Button>
      </form>
    {/if}

    <div>
      <h2 class="mb-2 text-sm font-semibold">Current quotas</h2>
      {#if quotas.length === 0}
        <p class="text-sm text-muted-foreground">No quotas saved for this cycle yet.</p>
      {:else}
        <Table.Root>
          <Table.Header>
            <Table.Row>
              <Table.Head>Program</Table.Head>
              <Table.Head>Slots</Table.Head>
              <Table.Head>Last notice</Table.Head>
              <Table.Head>Updated by</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each quotas as q (q.id)}
              <Table.Row>
                <Table.Cell>
                  <span class="font-mono text-xs">{q.program_code}</span>
                  <span class="text-muted-foreground"> · {q.program_name}</span>
                </Table.Cell>
                <Table.Cell><Badge variant="secondary">{q.slots}</Badge></Table.Cell>
                <Table.Cell class="max-w-xs truncate text-xs text-muted-foreground"
                  >{q.last_notice || '—'}</Table.Cell
                >
                <Table.Cell class="text-xs">{q.last_mutated_by || '—'}</Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
      {/if}
    </div>

    {#if quotas.some((q) => (q.changes ?? []).length)}
      <div>
        <h2 class="mb-2 text-sm font-semibold">Recent changes (Program Head visibility)</h2>
        <ul class="space-y-2">
          {#each quotas as q (q.id)}
            {#each q.changes ?? [] as change (change.id)}
              <li class="rounded-lg border border-border/70 px-3 py-2 text-sm">
                <div class="flex flex-wrap items-baseline gap-2">
                  <span class="font-mono text-xs">{q.program_code}</span>
                  <span class="text-muted-foreground">
                    {change.from_slots ?? '—'} → {change.to_slots} slots
                  </span>
                  <span class="text-xs text-muted-foreground">by {change.actor || 'staff'}</span>
                </div>
                {#if change.notice}
                  <p class="mt-1 text-xs text-muted-foreground">{change.notice}</p>
                {/if}
              </li>
            {/each}
          {/each}
        </ul>
      </div>
    {/if}
  </div>
</StaffLayout>
