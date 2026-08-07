<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { success } from '@/lib/toast';

  let { user, roles, googleLinked } = $props();

  const breadcrumbs = [{ label: 'Users', href: '/admin/users' }, { label: 'Edit' }];

  const initialRoles = (user.roles ?? []).map((r) => r.name);
  let selectedRoles = $state([...initialRoles]);

  const form = useForm({
    name: user.name,
    email: user.email,
    password: '',
    password_confirmation: '',
    roles: initialRoles,
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('User updated');
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
    $form.put(`/admin/users/${user.id}`);
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    <form onsubmit={submitForm}
      class="space-y-4 rounded-lg border border-border bg-card p-6"
    >
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Name</label>
        <Input id="name" bind:value={$form.name} required />
        {#if $form.errors?.name}
          <p class="text-sm text-destructive">{$form.errors.name}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="email" class="text-sm font-medium">Email</label>
        <Input id="email" type="email" bind:value={$form.email} required />
        {#if $form.errors?.email}
          <p class="text-sm text-destructive">{$form.errors.email}</p>
        {/if}
      </div>

      {#if googleLinked}
        <div class="flex items-center gap-2 text-sm text-emerald-600 font-semibold">
          <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
          </svg>
          Google account linked — changing the email above will remove this link
        </div>
      {:else}
        <p class="text-xs text-muted-foreground">No Google account linked.</p>
      {/if}

      <div class="space-y-2">
        <label for="password" class="text-sm font-medium">New Password</label>
        <Input id="password" type="password" bind:value={$form.password} placeholder="Leave blank to keep current" />
        {#if $form.errors?.password}
          <p class="text-sm text-destructive">{$form.errors.password}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="password_confirmation" class="text-sm font-medium">Confirm New Password</label>
        <Input id="password_confirmation" type="password" bind:value={$form.password_confirmation} placeholder="Leave blank to keep current" />
      </div>

      <div class="space-y-2">
        <p class="text-sm font-medium">Roles</p>
        <p class="text-xs text-muted-foreground">Select one or more roles. You cannot remove your own Super Admin role.</p>
        <div class="flex flex-wrap gap-4 pt-2">
          {#each roles as r}
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={selectedRoles.includes(r.name)}
                onchange={() => toggleRole(r.name)}
                class="h-4 w-4 rounded border-input accent-primary"
              />
              <span class="text-sm">{r.display_name}</span>
            </label>
          {/each}
        </div>
        {#if $form.errors?.roles}
          <p class="text-sm text-destructive">{$form.errors.roles}</p>
        {/if}
      </div>

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Saving...' : 'Save'}
        </Button>
        <Link href="/admin/users">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
