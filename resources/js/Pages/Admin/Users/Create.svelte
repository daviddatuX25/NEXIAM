<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { success } from '@/lib/toast';

  let { roles = [] } = $props();

  const breadcrumbs = [{ label: 'Users', href: '/admin/users' }, { label: 'Create' }];

  // Only roles allowed by StoreUserRequest (staff, admin, proctor, registrar_administrator, test_administrator)
  const allowedRoleNames = ['staff', 'admin', 'proctor', 'registrar_administrator', 'test_administrator'];
  const selectableRoles = $derived(roles.filter((r) => allowedRoleNames.includes(r.name)));

  let selectedRoles = $state([]);

  const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('User created');
    }
  };

  function toggleRole(name) {
    if (selectedRoles.includes(name)) {
      selectedRoles = selectedRoles.filter((r) => r !== name);
    } else {
      selectedRoles = [...selectedRoles, name];
    }
  }

  function submitForm(e) {
    e.preventDefault();
    $form.transform((data) => ({ ...data, roles: selectedRoles }));
    $form.post('/admin/users');
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Name</label>
        <Input id="name" bind:value={$form.name} placeholder="Full name" required />
        {#if $form.errors?.name}
          <p class="text-sm text-destructive">{$form.errors.name}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="email" class="text-sm font-medium">Email</label>
        <Input id="email" type="email" bind:value={$form.email} placeholder="user@example.com" required />
        {#if $form.errors?.email}
          <p class="text-sm text-destructive">{$form.errors.email}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="password" class="text-sm font-medium">Password</label>
        <Input id="password" type="password" bind:value={$form.password} placeholder="Min 8 characters, mixed case and numbers" required />
        {#if $form.errors?.password}
          <p class="text-sm text-destructive">{$form.errors.password}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="password_confirmation" class="text-sm font-medium">Confirm Password</label>
        <Input id="password_confirmation" type="password" bind:value={$form.password_confirmation} placeholder="Same as above" required />
        {#if $form.errors?.password_confirmation}
          <p class="text-sm text-destructive">{$form.errors.password_confirmation}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <p class="text-sm font-medium">Roles</p>
        <p class="text-xs text-muted-foreground">Select at least one role.</p>
        <div class="flex flex-wrap gap-4 pt-2">
          {#each selectableRoles as r}
            <label class="flex items-center gap-2 cursor-pointer min-h-[44px]">
              <input
                type="checkbox"
                checked={selectedRoles.includes(r.name)}
                onchange={() => toggleRole(r.name)}
                class="h-4 w-4 rounded border-input accent-primary"
              />
              <span class="text-sm">{r.display_name ?? r.name}</span>
            </label>
          {/each}
        </div>
        {#if $form.errors?.roles}
          <p class="text-sm text-destructive">{$form.errors.roles}</p>
        {/if}
      </div>

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Creating...' : 'Create User'}
        </Button>
        <Link href="/admin/users">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
