<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PasswordStrengthInput from '@/Components/kit/PasswordStrengthInput.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Link, useForm } from '@inertiajs/svelte';

  let { roles = [], can_full_hr = false, default_office = 'hr' } = $props();

  let selectedRoles = $state([]);
  let password = $state('');
  let passwordConfirmation = $state('');

  const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
    office: default_office,
    program_codes: [],
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
    form.password = password;
    form.password_confirmation = passwordConfirmation;
    form.roles = selectedRoles;
    form.post('/staff/personnel');
  }

  const breadcrumbs = [
    { label: 'Personnel', href: '/staff/personnel' },
    { label: 'Create' },
  ];
</script>

<svelte:head><title>Create staff</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="mx-auto max-w-lg space-y-6 p-6">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">Create staff</h1>
      <p class="text-sm text-muted-foreground">
        Assign only approved role helpers. Test Administrator and Proctor are not granted here.
      </p>
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
        label="Password"
      />

      {#if can_full_hr}
        <div class="space-y-2">
          <label for="office" class="text-sm font-medium">Office</label>
          <Input id="office" bind:value={form.office} required />
          {#if form.errors?.office}<p class="text-sm text-destructive">{form.errors.office}</p>{/if}
        </div>
      {:else}
        <input type="hidden" bind:value={form.office} />
        <p class="text-sm text-muted-foreground">Office: <span class="font-medium text-foreground">{default_office}</span></p>
      {/if}

      <div class="space-y-2">
        <p class="text-sm font-medium">Roles</p>
        <p class="text-xs text-muted-foreground">Select at least one approved role helper.</p>
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
          {form.processing ? 'Creating…' : 'Create staff'}
        </Button>
        <Link href="/staff/personnel">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</StaffLayout>
