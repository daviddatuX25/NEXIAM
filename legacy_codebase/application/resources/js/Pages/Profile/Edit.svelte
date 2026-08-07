<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import PasswordStrengthInput from '@/Components/PasswordStrengthInput.svelte';
  import { success as showSuccess } from '@/lib/toast';
  import { User, Lock, Mail, AlertTriangle } from 'lucide-svelte';
  import { onMount } from 'svelte';
  import { usePage } from '@inertiajs/svelte';

  let { user, googleLinked } = $props();

  const page = usePage();
  const breadcrumbs = [{ label: 'Profile' }];

  // ── Profile info form ──
  const profileForm = useForm({
    name: user.name,
    email: user.email,
  });

  // ── Password form ──
  const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  let emailChanged = $derived($profileForm.email !== user.email);

  onMount(() => {
    const flash = $page.props.flash;
    if (flash?.success) showSuccess(flash.success);
  });

  function submitProfile(e) {
    e.preventDefault();
    $profileForm.put('/profile', {
      preserveScroll: true,
      onSuccess: () => {
        showSuccess('Profile updated.');
        // Update our local "user" reference so emailChanged recalculates correctly
        user = { ...user, name: $profileForm.name, email: $profileForm.email };
      },
    });
  }

  function submitPassword(e) {
    e.preventDefault();
    $passwordForm.put('/profile/password', {
      preserveScroll: true,
      onSuccess: () => {
        showSuccess('Password changed.');
        $passwordForm.reset();
      },
    });
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-2xl space-y-8">
    <!-- ── Section: Profile Information ── -->
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
      <div class="mb-6">
        <div class="flex items-center gap-3 mb-1">
          <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-primary/10">
            <User class="w-5 h-5 text-primary" />
          </div>
          <div>
            <h2 class="text-lg font-semibold text-foreground">Profile Information</h2>
            <p class="text-sm text-muted-foreground">Update your name and email address.</p>
          </div>
        </div>
      </div>

      <form onsubmit={submitProfile} class="space-y-4">
        <div class="space-y-2">
          <label for="name" class="text-sm font-medium leading-none">Name</label>
          <Input id="name" bind:value={$profileForm.name} required autocomplete="name" />
          {#if $profileForm.errors?.name}
            <p class="text-sm text-destructive">{$profileForm.errors.name}</p>
          {/if}
        </div>

        <div class="space-y-2">
          <label for="email" class="text-sm font-medium leading-none">Email</label>
          <div class="relative">
            <Mail class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
            <Input
              id="email"
              type="email"
              bind:value={$profileForm.email}
              required
              autocomplete="email"
              class="pl-9"
            />
          </div>
          {#if $profileForm.errors?.email}
            <p class="text-sm text-destructive">{$profileForm.errors.email}</p>
          {/if}
        </div>

        {#if googleLinked}
          <div class="flex items-start gap-2 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 p-3 text-sm">
            <svg class="h-4 w-4 mt-0.5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            <div>
              <span class="font-semibold text-emerald-700 dark:text-emerald-400">Google account linked</span>
              {#if emailChanged}
                <p class="text-emerald-600 dark:text-emerald-500 mt-0.5">
                  <AlertTriangle class="inline w-3.5 h-3.5 -mt-0.5" />
                  Changing your email will remove this Google link.
                </p>
              {/if}
            </div>
          </div>
        {:else}
          <p class="text-xs text-muted-foreground">No Google account linked.</p>
        {/if}

        <div class="flex items-center gap-3 pt-2">
          <Button type="submit" disabled={$profileForm.processing}>
            {$profileForm.processing ? 'Saving...' : 'Save Changes'}
          </Button>
          {#if $profileForm.recentlySuccessful}
            <span class="text-sm text-primary font-medium animate-in fade-in">Saved.</span>
          {/if}
        </div>
      </form>
    </div>

    <!-- ── Section: Change Password ── -->
    <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
      <div class="mb-6">
        <div class="flex items-center gap-3 mb-1">
          <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-amber-500/10">
            <Lock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
          </div>
          <div>
            <h2 class="text-lg font-semibold text-foreground">Change Password</h2>
            <p class="text-sm text-muted-foreground">Use a strong, unique password to secure your account.</p>
          </div>
        </div>
      </div>

      <form onsubmit={submitPassword} class="space-y-4">
        <div class="space-y-2">
          <label for="current_password" class="text-sm font-medium leading-none">Current Password</label>
          <Input
            id="current_password"
            type="password"
            bind:value={$passwordForm.current_password}
            required
            autocomplete="current-password"
          />
          {#if $passwordForm.errors?.current_password}
            <p class="text-sm text-destructive">{$passwordForm.errors.current_password}</p>
          {/if}
        </div>

        <PasswordStrengthInput
          bind:password={$passwordForm.password}
          bind:passwordConfirmation={$passwordForm.password_confirmation}
          id="new_password"
          label="New Password"
          confirmationLabel="Confirm New Password"
          errors={$passwordForm.errors}
          passwordError={$passwordForm.errors?.password}
          confirmationError={$passwordForm.errors?.password_confirmation}
        />

        <div class="flex items-center gap-3 pt-2">
          <Button type="submit" disabled={$passwordForm.processing}>
            {$passwordForm.processing ? 'Updating...' : 'Update Password'}
          </Button>
          {#if $passwordForm.recentlySuccessful}
            <span class="text-sm text-primary font-medium animate-in fade-in">Updated.</span>
          {/if}
        </div>
      </form>
    </div>
  </div>
</AuthenticatedLayout>
