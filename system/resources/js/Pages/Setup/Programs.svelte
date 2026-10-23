<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import EntityRowActions from '@/Components/kit/EntityRowActions.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Table from '@/Components/ui/table';
  import * as Dialog from '@/Components/ui/dialog';
  import { Link, useForm, router } from '@inertiajs/svelte';
  import { Globe, LayoutGrid, Pencil, Power, SlidersHorizontal } from 'lucide-svelte';

  let { programs = [], departments = [] } = $props();

  const form = useForm({
    code: '',
    name: '',
    department_id: '',
    is_active: true,
  });

  function submit() {
    form.post('/setup/programs', { onSuccess: () => form.reset('code', 'name', 'department_id') });
  }

  let editDialogOpen = $state(false);
  let activeProgram = $state(null);
  const editForm = useForm({
    name: '',
    department_id: '',
    is_active: true,
  });

  function openEdit(program) {
    activeProgram = program;
    editForm.name = program.name;
    editForm.department_id = program.department_id ? String(program.department_id) : '';
    editForm.is_active = program.is_active;
    editForm.clearErrors();
    editDialogOpen = true;
  }

  function submitEdit() {
    if (!activeProgram) return;

    editForm.put(`/setup/programs/${activeProgram.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        editDialogOpen = false;
        activeProgram = null;
      },
    });
  }

  function toggleActive(program) {
    router.put(`/setup/programs/${program.id}`, {
      name: program.name,
      department_id: program.department_id,
      is_active: !program.is_active,
    });
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Programs' },
  ];
</script>

<svelte:head><title>Programs &amp; Courses · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Programs &amp; Courses"
      description="Manage degree programs and academic courses, and designate programs open for admissions."
    >
      {#snippet actions()}
        <div class="flex flex-wrap items-center gap-2">
          <Link href="/setup/departments">
            <Button variant="outline" size="sm" class="font-medium">
              Manage Colleges &amp; Departments
            </Button>
          </Link>
          <Link href="/setup"><Button variant="ghost" size="sm">Back to readiness</Button></Link>
        </div>
      {/snippet}
    </PageHeaderBar>
    <div class="space-y-3 md:hidden">
      {#each programs as p (p.id)}
        <article class="space-y-3 rounded-xl border border-border bg-card p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
              <p class="font-mono text-xs text-muted-foreground">{p.code}</p>
              <h2 class="font-medium text-foreground">{p.name}</h2>
              <p class="text-sm text-muted-foreground">{p.department ? `${p.department.code} - ${p.department.name}` : 'No Department'}</p>
            </div>
            <Badge variant={p.is_active ? 'default' : 'secondary'}>{p.is_active ? 'Active' : 'Inactive'}</Badge>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant="outline" onclick={() => openEdit(p)}>
              <Pencil class="mr-1.5 size-4" /> Edit
            </Button>
            <Link href={`/setup/programs/${p.id}/hub`}>
              <Button type="button" size="sm" variant="outline">Program Hub</Button>
            </Link>
            <Button type="button" size="sm" variant="ghost" onclick={() => toggleActive(p)}>
              <Power class="mr-1.5 size-4" /> {p.is_active ? 'Deactivate' : 'Activate'}
            </Button>
          </div>
        </article>
      {/each}
    </div>


    <form
      class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-5"
      onsubmit={(e) => {
        e.preventDefault();
        submit();
      }}
    >
      <div class="space-y-1.5">
        <Label for="code">Code</Label>
        <Input id="code" bind:value={form.code} class="min-h-10 font-mono" placeholder="BSN" />
      </div>
      <div class="space-y-1.5 sm:col-span-2">
        <Label for="name">Name</Label>
        <Input id="name" bind:value={form.name} class="min-h-10" />
      </div>
      <div class="space-y-1.5">
        <Label for="department_id">Department / College</Label>
        <select
          id="department_id"
          bind:value={form.department_id}
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
          <option value="">No Department</option>
          {#each departments as d (d.id)}
            <option value={d.id}>{d.code} - {d.name}</option>
          {/each}
        </select>
      </div>
      <div class="flex items-end">
        <Button type="submit" class="min-h-10 w-full" disabled={form.processing}>Add program</Button>
      </div>
    </form>

    <div class="hidden overflow-x-auto md:block">
      <Table.Root class="w-full min-w-[680px] text-sm">
        <Table.Header class="bg-muted/40 border-b border-border">
          <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
            <Table.Head>Code</Table.Head>
            <Table.Head>Name</Table.Head>
            <Table.Head>Department</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head class="pr-6 text-right">Actions</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#each programs as p (p.id)}
            <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
              <Table.Cell class="font-mono text-xs">{p.code}</Table.Cell>
              <Table.Cell>{p.name}</Table.Cell>
              <Table.Cell>
                {#if p.department}
                  <Badge variant="outline" class="font-mono text-xs">{p.department.code}</Badge>
                {:else}
                  <span class="text-xs text-muted-foreground">—</span>
                {/if}
              </Table.Cell>
              <Table.Cell>
                <Badge variant={p.is_active ? 'default' : 'secondary'}
                  >{p.is_active ? 'Active' : 'Inactive'}</Badge
                >
              </Table.Cell>
              <Table.Cell class="pr-6 text-right" data-action-cell>
                <EntityRowActions
                  primaryAction={{
                    label: 'Program Hub',
                    href: `/setup/programs/${p.id}/hub`,
                    icon: LayoutGrid,
                    variant: 'outline',
                  }}
                  overflowItems={[
                    {
                      label: 'Manage Quota',
                      href: `/setup/programs/${p.id}/hub?tab=quotas`,
                      icon: SlidersHorizontal,
                    },
                    p.public_details_href
                      ? {
                          label: 'Edit public details',
                          href: p.public_details_href,
                          icon: Globe,
                        }
                      : null,
                    {
                      label: 'Edit',
                      onclick: () => openEdit(p),
                      icon: Pencil,
                    },
                    {
                      label: p.is_active ? 'Deactivate' : 'Activate',
                      onclick: () => toggleActive(p),
                      icon: Power,
                    },
                  ]}
                  dense={true}
                />
              </Table.Cell>
            </Table.Row>
          {/each}
        </Table.Body>
      </Table.Root>
    </div>
  </div>
  <Dialog.Root bind:open={editDialogOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Edit Program</Dialog.Title>
        <Dialog.Description>
          Update the program name or its department. Its active status will remain unchanged.
        </Dialog.Description>
      </Dialog.Header>
      <form
        class="space-y-4 py-2"
        onsubmit={(event) => {
          event.preventDefault();
          submitEdit();
        }}
      >
        <div class="space-y-1.5">
          <Label for="edit_program_name">Name</Label>
          <Input id="edit_program_name" bind:value={editForm.name} class="min-h-10" required />
          {#if editForm.errors.name}
            <p class="text-sm text-destructive">{editForm.errors.name}</p>
          {/if}
        </div>
        <div class="space-y-1.5">
          <Label for="edit_program_department">Department / College</Label>
          <select
            id="edit_program_department"
            bind:value={editForm.department_id}
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          >
            <option value="">No Department</option>
            {#each departments as department (department.id)}
              <option value={String(department.id)}>{department.code} - {department.name}</option>
            {/each}
          </select>
          {#if editForm.errors.department_id}
            <p class="text-sm text-destructive">{editForm.errors.department_id}</p>
          {/if}
        </div>
        <Dialog.Footer>
          <Button type="button" variant="outline" onclick={() => { editDialogOpen = false; }}>Cancel</Button>
          <Button type="submit" disabled={editForm.processing}>Save Changes</Button>
        </Dialog.Footer>
      </form>
    </Dialog.Content>
  </Dialog.Root>
</StaffLayout>
