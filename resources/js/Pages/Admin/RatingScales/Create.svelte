<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Switch } from '@/Components/ui/switch';
  import * as Table from '@/Components/ui/table';
  import { Plus, Trash2 } from 'lucide-svelte';
  import { success } from '@/lib/toast';

  const form = useForm({
    name: '',
    ranges: [
      { min: 90, max: 100, label: 'Outstanding' },
      { min: 75, max: 89, label: 'Above Average' },
      { min: 50, max: 74, label: 'Average' },
      { min: 25, max: 49, label: 'Below Average' },
      { min: 0, max: 24, label: 'Needs Improvement' },
    ],
    is_default: false,
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Rating scale created');
    }
  };

  function addRange() {
    $form.ranges = [...$form.ranges, { min: 0, max: 0, label: '' }];
  }

  function removeRange(index) {
    $form.ranges = $form.ranges.filter((_, i) => i !== index);
  }

  function submitForm(e) {
    e.preventDefault();
    $form.post('/admin/setup/rating-scales');
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/admin/setup' },
    { label: 'Rating Scales', href: '/admin/setup/rating-scales' },
    { label: 'Create' },
  ];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-2xl space-y-6">
    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Name</label>
        <Input id="name" bind:value={$form.name} placeholder="e.g., ISPSC Standard" required maxlength="255" />
        {#if $form.errors?.name}
          <p class="text-sm text-destructive">{$form.errors.name}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label class="text-sm font-medium">Ranges</label>
        <div class="rounded-md border border-border">
          <Table.Root>
            <Table.Header class="bg-muted/50">
              <Table.Row>
                <Table.Head class="px-3 py-2">Min %</Table.Head>
                <Table.Head class="px-3 py-2">Max %</Table.Head>
                <Table.Head class="px-3 py-2">Label</Table.Head>
                <Table.Head class="px-3 py-2 w-12"></Table.Head>
              </Table.Row>
            </Table.Header>
            <Table.Body>
              {#each $form.ranges as range, i}
                <Table.Row>
                  <Table.Cell class="px-3 py-2">
                    <Input
                      type="number"
                      bind:value={$form.ranges[i].min}
                      min="0"
                      max="100"
                      class="w-20"
                    />
                  </Table.Cell>
                  <Table.Cell class="px-3 py-2">
                    <Input
                      type="number"
                      bind:value={$form.ranges[i].max}
                      min="0"
                      max="100"
                      class="w-20"
                    />
                  </Table.Cell>
                  <Table.Cell class="px-3 py-2">
                    <Input
                      bind:value={$form.ranges[i].label}
                      placeholder="Label"
                      maxlength="100"
                      class="min-w-[140px]"
                    />
                  </Table.Cell>
                  <Table.Cell class="px-3 py-2">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      class="h-8 w-8 p-0 text-destructive hover:text-destructive"
                      onclick={() => removeRange(i)}
                      disabled={$form.ranges.length <= 1}
                    >
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </Table.Cell>
                </Table.Row>
              {/each}
            </Table.Body>
          </Table.Root>
        </div>
        {#if $form.errors?.ranges}
          <p class="text-sm text-destructive">{$form.errors.ranges}</p>
        {/if}
        <Button type="button" variant="outline" size="sm" onclick={addRange}>
          <Plus class="mr-1.5 h-3.5 w-3.5" />
          Add Range
        </Button>
      </div>

      <div class="flex items-center gap-3">
        <Switch
          id="is_default"
          checked={$form.is_default}
          onCheckedChange={(v) => ($form.is_default = v)}
        />
        <label for="is_default" class="text-sm font-medium">Default rating scale</label>
      </div>
      {#if $form.errors?.is_default}
        <p class="text-sm text-destructive">{$form.errors.is_default}</p>
      {/if}

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Creating...' : 'Create rating scale'}
        </Button>
        <Link href="/admin/setup/rating-scales">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
