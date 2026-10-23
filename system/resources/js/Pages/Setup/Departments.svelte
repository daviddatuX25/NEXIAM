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
  import { Building2, GraduationCap, Pencil, Power, ShieldCheck, UserCheck } from 'lucide-svelte';

  let {
    departments = [],
    eligible_deans = [],
    office_rooms = [],
    office_mapping_available = false,
  } = $props();

  const form = useForm({
    code: '',
    name: '',
    dean_user_id: '',
    is_active: true,
  });

  function submit() {
    form.post('/setup/departments', {
      onSuccess: () => form.reset('code', 'name', 'dean_user_id'),
    });
  }

  function toggleActive(dept) {
    router.post(`/setup/departments/${dept.id}/toggle`);
  }

  // Edit Department modal state
  let editDialogOpen = $state(false);
  let activeDept = $state(null);
  const editForm = useForm({
    name: '',
    dean_user_id: '',
    office_room_id: '',
    is_active: true,
  });

  function openEdit(dept) {
    activeDept = dept;
    editForm.name = dept.name;
    editForm.dean_user_id = dept.dean_user_id ? String(dept.dean_user_id) : '';
    editForm.office_room_id = dept.office_room_id ? String(dept.office_room_id) : '';
    editForm.is_active = dept.is_active;
    editDialogOpen = true;
  }

  function submitEdit() {
    if (!activeDept) return;
    editForm.put(`/setup/departments/${activeDept.id}`, {
      onSuccess: () => {
        editDialogOpen = false;
        activeDept = null;
      },
    });
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Colleges & Departments' },
  ];
</script>

<svelte:head><title>Colleges &amp; Departments · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Colleges &amp; Academic Departments"
      description="Manage institutional academic colleges, appoint college deans, and oversee department degree program jurisdictions."
    >
      {#snippet actions()}
        <div class="flex items-center gap-2">
          <Link href="/setup/programs">
            <Button variant="outline" size="sm" class="gap-1.5 font-medium">
              <GraduationCap class="size-4" /> View Programs &amp; Courses
            </Button>
          </Link>
          <Link href="/setup">
            <Button variant="ghost" size="sm">Back to readiness</Button>
          </Link>
        </div>
      {/snippet}
    </PageHeaderBar>

    <!-- Create Department Form -->
    <form
      class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-5"
      onsubmit={(e) => {
        e.preventDefault();
        submit();
      }}
    >
      <div class="space-y-1.5">
        <Label for="dept_code">College Code</Label>
        <Input id="dept_code" bind:value={form.code} class="min-h-10 font-mono uppercase" placeholder="CTE" required />
      </div>
      <div class="space-y-1.5 sm:col-span-2">
        <Label for="dept_name">College / Department Name</Label>
        <Input id="dept_name" bind:value={form.name} class="min-h-10" placeholder="e.g. College of Teacher Education" required />
      </div>
      <div class="space-y-1.5">
        <Label for="dean_select">Appointed College Dean</Label>
        <select
          id="dean_select"
          bind:value={form.dean_user_id}
          class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
          <option value="">No Dean Appointed</option>
          {#each eligible_deans as user (user.id)}
            <option value={user.id}>{user.name} ({user.email})</option>
          {/each}
        </select>
      </div>
      <div class="flex items-end">
        <Button type="submit" class="min-h-10 w-full font-semibold" disabled={form.processing}>
          Add College
        </Button>
      </div>
    </form>

    <!-- Departments Table -->
    <div class="overflow-x-auto">
      <Table.Root class="w-full min-w-[680px] text-sm">
        <Table.Header class="bg-muted/40 border-b border-border">
          <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
            <Table.Head class="w-24">Code</Table.Head>
            <Table.Head>College / Department Name</Table.Head>
            <Table.Head>Appointed Dean</Table.Head>
            <Table.Head>Assigned Programs</Table.Head>
            <Table.Head>Status</Table.Head>
            <Table.Head class="pr-6 text-right">Actions</Table.Head>
          </Table.Row>
        </Table.Header>
        <Table.Body>
          {#if departments.length === 0}
            <Table.Row>
              <Table.Cell colspan="6" class="py-10 text-center text-sm text-muted-foreground">
                No colleges or departments registered yet.
              </Table.Cell>
            </Table.Row>
          {:else}
            {#each departments as d (d.id)}
              <Table.Row class="border-b border-border/40 hover:bg-muted/30 transition-colors">
                <Table.Cell>
                  <Badge variant="outline" class="font-mono text-xs font-bold">{d.code}</Badge>
                </Table.Cell>
                <Table.Cell class="font-medium text-foreground">{d.name}</Table.Cell>
                <Table.Cell>
                  {#if d.dean}
                    <div class="flex items-center gap-1.5">
                      <UserCheck class="size-3.5 text-primary shrink-0" />
                      <div>
                        <p class="text-xs font-semibold leading-none">{d.dean.name}</p>
                        <p class="text-[10px] text-muted-foreground">{d.dean.email}</p>
                      </div>
                    </div>
                  {:else}
                    <span class="text-xs italic text-muted-foreground">None appointed</span>
                  {/if}
                </Table.Cell>
                <Table.Cell>
                  <div class="flex flex-wrap items-center gap-1">
                    <span class="font-semibold text-xs mr-1">{d.programs_count ?? d.programs?.length ?? 0}</span>
                    {#each d.programs ?? [] as prog (prog.id)}
                      <Link href={`/setup/programs/${prog.id}/hub`}>
                        <Badge variant="secondary" class="font-mono text-[10px] px-1.5 py-0">{prog.code}</Badge>
                      </Link>
                    {/each}
                  </div>
                </Table.Cell>
                <Table.Cell>
                  <Badge variant={d.is_active ? 'default' : 'secondary'}>
                    {d.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </Table.Cell>
                <Table.Cell class="pr-6 text-right" data-action-cell>
                  <EntityRowActions
                    primaryAction={{
                      label: 'Edit',
                      onclick: () => openEdit(d),
                      icon: Pencil,
                      variant: 'outline',
                    }}
                    overflowItems={[
                      {
                        label: d.is_active ? 'Deactivate' : 'Activate',
                        icon: Power,
                        onclick: () => toggleActive(d),
                      },
                    ]}
                    dense={true}
                  />
                </Table.Cell>
              </Table.Row>
            {/each}
          {/if}
        </Table.Body>
      </Table.Root>
    </div>
  </div>

  <!-- Edit Department Modal Dialog -->
  <Dialog.Root bind:open={editDialogOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Edit College / Department</Dialog.Title>
        <Dialog.Description>
          Update department title, appointed Dean, or operational status.
        </Dialog.Description>
      </Dialog.Header>
      <form
        onsubmit={(e) => {
          e.preventDefault();
          submitEdit();
        }}
        class="space-y-4 py-2"
      >
        <div class="space-y-1.5">
          <Label for="edit_dept_code">College Code</Label>
          <Input id="edit_dept_code" value={activeDept?.code} class="min-h-10 font-mono uppercase bg-muted/40" disabled />
        </div>
        <div class="space-y-1.5">
          <Label for="edit_dept_name">College Name</Label>
          <Input id="edit_dept_name" bind:value={editForm.name} class="min-h-10" required />
        </div>
        <div class="space-y-1.5">
          <Label for="edit_dean_select">Appointed College Dean</Label>
          <select
            id="edit_dean_select"
            bind:value={editForm.dean_user_id}
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
          >
            <option value="">No Dean Appointed</option>
            {#each eligible_deans as user (user.id)}
              <option value={user.id}>{user.name} ({user.email})</option>
            {/each}
          </select>
        </div>
        {#if office_mapping_available}
          <div class="space-y-1.5">
            <Label for="edit_office_room">Department office / service point</Label>
            <select
              id="edit_office_room"
              bind:value={editForm.office_room_id}
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
              <option value="">No office location configured</option>
              {#each office_rooms as room (room.id)}
                <option value={String(room.id)}>{room.name}{room.building_name ? ` · ${room.building_name}` : ''}</option>
              {/each}
            </select>
          </div>
        {/if}
        <div class="flex items-center gap-2 pt-1">
          <input
            type="checkbox"
            id="edit_is_active"
            bind:checked={editForm.is_active}
            class="h-4 w-4 rounded border-border"
          />
          <Label for="edit_is_active" class="cursor-pointer">Active College / Department</Label>
        </div>
        <Dialog.Footer>
          <Button type="button" variant="outline" onclick={() => { editDialogOpen = false; }}>Cancel</Button>
          <Button type="submit" disabled={editForm.processing}>Save Changes</Button>
        </Dialog.Footer>
      </form>
    </Dialog.Content>
  </Dialog.Root>
</StaffLayout>
