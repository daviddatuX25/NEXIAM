<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PasswordStrengthInput from '@/Components/kit/PasswordStrengthInput.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Link, useForm } from '@inertiajs/svelte';

  let { user, roles = [], can_full_hr = false } = $props();

  let selectedRoles = $state([...(user.roles ?? [])]);
  let password = $state('');
  let passwordConfirmation = $state('');

  const form = useForm({
    name: user.name,
    email: user.email,
    password: '',
    password_confirmation: '',
    roles: [...(user.roles ?? [])],
    office: user.office,
    program_codes: user.program_codes ?? [],
  });

  function toggleRole(name) {
    if (selectedRoles.includes(name)) {
      selectedRoles = selectedRoles.filter((r) => r !== name);
    } else {
      selectedRoles = [...selectedRoles, name];
    }
  }

  function submitForm(e) {
    e.preventDefault();
    form.password = password || '';
    form.password_confirmation = passwordConfirmation || '';
    form.roles = selectedRoles;
    form.put(`/staff/personnel/${user.id}`);
  }

  const breadcrumbs = [
    { label: 'Personnel', href: '/staff/personnel' },
    { label: 'Edit' },
  ];
</script>

<svelte:head><title>Edit staff</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="mx-auto max-w-lg space-y-6 p-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Edit staff</h1>
      <p class="text-sm capitalize text-muted-foreground">Status: {user.status}</p>
    </div>

    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Name</label>
        <Input id="name" bind:value={form.name} required />
        {#if form.errors?.name}<p class="text-sm text-destructive">{form.errors.name}</p>{/if}
      </div>

      <div class="space-y-2">
        <label for="email" class="text-sm font-medium">Email</label>
        <Input id="email" type="email" bind:value={form.email} required />
        {#if form.errors?.email}<p class="text-sm text-destructive">{form.errors.email}</p>{/if}
      </div>

      <PasswordStrengthInput
        bind:password
        bind:passwordConfirmation
        errors={form.errors}
        label="New password"
        confirmationLabel="Confirm new password"
      />
      <p class="text-xs text-muted-foreground">Leave password blank to keep the current password.</p>

      {#if can_full_hr}
        <div class="space-y-2">
          <label for="office" class="text-sm font-medium">Office</label>
          <Input id="office" bind:value={form.office} required />
        </div>
      {:else}
        <p class="text-sm text-muted-foreground">Office: <span class="font-medium text-foreground">{user.office}</span></p>
      {/if}

      <div class="space-y-2">
        <p class="text-sm font-medium">Roles</p>
        <div class="flex flex-wrap gap-4 pt-2">
          {#each roles as r}
            <label class="flex min-h-11 cursor-pointer items-center gap-2">
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
        {#if form.errors?.roles}<p class="text-sm text-destructive">{form.errors.roles}</p>{/if}
      </div>

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={form.processing}>
          {form.processing ? 'Saving…' : 'Save'}
        </Button>
        <Link href="/staff/personnel">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</StaffLayout>
