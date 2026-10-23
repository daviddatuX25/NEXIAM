<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import AvatarSelector from '@/Pages/Staff/Profile/AvatarSelector.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { PinInput } from '@/Components/ui/pin-input';
  import * as Dialog from '@/Components/ui/dialog';
  import { router, useForm } from '@inertiajs/svelte';
  import { csrfHeaders } from '@/lib/utils';
  import { Check, Clock3, Copy, Link2, Link2Off, Lock, Mail, MapPin, Phone, ShieldCheck, User, X } from 'lucide-svelte';

  let { page_state = 'ready', user = null, directory = {}, security = null, email_confirmation = null } = $props();
  let emailRequestOpen = $state(false);
  let googleUnlinkDialogOpen = $state(false);
  let mfaDialogOpen = $state(false);
  let mfaDialogMode = $state('recovery');
  let mfaBusy = $state(false);
  let mfaError = $state('');
  let mfaSetup = $state(null);
  let mfaCodes = $state([]);
  let mfaCurrentPassword = $state('');
  let mfaTotpCode = $state('');
  let mfaConfirmationCode = $state('');
  let mfaCodesCopied = $state(false);
  let mfaSecretCopied = $state(false);
  let mfaSecondsRemaining = $state(600);
  let mfaConfirmFormElement = $state(null);
  let mfaRecoveryFormElement = $state(null);
  $effect(() => {
    if (!mfaSetup?.expires_at) {
      mfaSecondsRemaining = 600;
      return;
    }

    const expiresAt = Date.parse(mfaSetup.expires_at);
    const updateRemaining = () => {
      mfaSecondsRemaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
    };

    updateRemaining();
    const timer = setInterval(updateRemaining, 1000);
    return () => clearInterval(timer);
  });

  const mfaIsEnrolled = $derived(security?.two_factor_confirmed ?? security?.mfa_status === 'enrolled');
  const mfaIsRequired = $derived(Boolean(security?.mfa_required));

  const canToggleSelfMfa = $derived(Boolean(security?.can_toggle_mfa));
  let mfaPolicyBusy = $state(false);

  function toggleSelfMfaPolicy(mode) {
    if (mfaPolicyBusy) return;
    mfaPolicyBusy = true;
    const path = mode === 'disable' ? '/staff/profile/mfa/disable' : '/staff/profile/mfa/require';
    router.post(path, {}, {
      preserveScroll: true,
      onFinish: () => { mfaPolicyBusy = false; },
    });
  }
  function formatMfaTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
  }

  async function beginProfileEnrollment(event) {
    event.preventDefault();
    mfaBusy = true;
    mfaError = '';
    try {
      mfaSetup = await postMfaJson('/staff/profile/mfa/enroll/begin', {
        password: mfaCurrentPassword,
      });
    } catch (caught) {
      mfaError = caught.message ?? 'Two-factor enrollment could not be started.';
    } finally {
      mfaBusy = false;
    }
  }

  async function confirmProfileEnrollment(event) {
    event?.preventDefault?.();
    const cleanCode = mfaConfirmationCode.replace(/\D/g, '');
    if (cleanCode.length !== 6) {
      mfaError = 'Please enter a 6-digit verification code.';
      return;
    }
    mfaBusy = true;
    mfaError = '';
    try {
      const result = await postMfaJson('/staff/profile/mfa/enroll/confirm', {
        code: cleanCode,
      });
      mfaCodes = result.recovery_codes;
      mfaSetup = null;
    } catch (caught) {
      mfaError = caught.message ?? 'The authenticator code could not be confirmed.';
    } finally {
      mfaBusy = false;
    }
  }

  function beginMfaSetup() {
    openMfaDialog('setup');
  }
  async function postMfaJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...csrfHeaders(),
      },
      body: JSON.stringify(payload ?? {}),
    });
    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.message ?? Object.values(data.errors ?? {})[0]?.[0] ?? 'The request could not be completed.');
    }
    return data;
  }

  const breadcrumbs = [{ label: 'Staff', href: '/staff' }, { label: 'My Profile' }];
  const nameForm = useForm({ name: user?.name ?? '' });
  const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '', totp_code: '' });
  const emailForm = useForm({ new_email: '', current_password: '', totp_code: '' });
  const confirmationForm = useForm({ current_password: '', totp_code: '' });
  const googleUnlinkForm = useForm({ current_password: '', totp_code: '' });
  const directoryForm = useForm({
    phone_extension: directory?.phone_extension ?? '',
    room_number: directory?.room_number ?? '',
    consultation_hours: directory?.consultation_hours ?? '',
  });

  const passwordCriteria = $derived([
    { label: 'At least 8 characters', met: passwordForm.password.length >= 8 },
    { label: 'An uppercase letter', met: /[A-Z]/.test(passwordForm.password) },
    { label: 'A lowercase letter', met: /[a-z]/.test(passwordForm.password) },
    { label: 'A number', met: /[0-9]/.test(passwordForm.password) },
    { label: 'A symbol', met: /[^A-Za-z0-9]/.test(passwordForm.password) },
  ]);

  function submitName(event) {
    event.preventDefault();
    nameForm.put('/staff/profile/name', { preserveScroll: true });
  }
  function submitDirectory(event) {
    event.preventDefault();
    directoryForm.post('/staff/profile/directory', { preserveScroll: true });
  }

  function submitPassword(event) {
    event.preventDefault();
    passwordForm.put('/staff/profile/password', {
      preserveScroll: true,
      onSuccess: () => passwordForm.reset(),
    });
  }

  function submitEmailRequest(event) {
    event.preventDefault();
    emailForm.post('/staff/profile/email/request', {
      preserveScroll: true,
      onSuccess: () => {
        emailForm.reset();
        emailRequestOpen = false;
      },
    });
  }
  function submitEmailConfirmation(event) {
    event.preventDefault();
    confirmationForm.post(`/staff/profile/email/confirm/${email_confirmation.token}`, {
      preserveScroll: true,
    });
  }

  function cancelEmailRequest() {
    emailForm.delete('/staff/profile/email/cancel', { preserveScroll: true });
  }
  function openGoogleUnlinkDialog() {
    googleUnlinkForm.reset();
    googleUnlinkForm.clearErrors();
    googleUnlinkDialogOpen = true;
  }

  function closeGoogleUnlinkDialog() {
    googleUnlinkDialogOpen = false;
    googleUnlinkForm.reset();
    googleUnlinkForm.clearErrors();
  }

  function submitGoogleUnlink(event) {
    event.preventDefault();
    googleUnlinkForm.post('/staff/profile/google/unlink', {
      preserveScroll: true,
      onSuccess: closeGoogleUnlinkDialog,
    });
  }

  function openMfaDialog(mode) {
    mfaDialogMode = mode;
    mfaError = '';
    mfaSetup = null;
    mfaCodes = [];
    mfaCurrentPassword = '';
    mfaTotpCode = '';
    mfaConfirmationCode = '';
    mfaCodesCopied = false;
    mfaSecretCopied = false;
    mfaDialogOpen = true;
  }

  function closeMfaDialog() {
    const hadNewCodes = mfaCodes.length > 0;
    mfaDialogOpen = false;
    mfaSetup = null;
    mfaCodes = [];
    mfaCurrentPassword = '';
    mfaTotpCode = '';
    mfaConfirmationCode = '';
    mfaCodesCopied = false;
    mfaSecretCopied = false;
    mfaError = '';
    if (hadNewCodes) {
      router.reload({ preserveScroll: true });
    }
  }


  async function submitRecoveryCodeRequest(event) {
    event?.preventDefault?.();
    const cleanTotp = mfaTotpCode.replace(/\D/g, '');
    if (mfaIsEnrolled && cleanTotp.length !== 6) {
      mfaError = 'Please enter a 6-digit authenticator code.';
      return;
    }
    mfaBusy = true;
    mfaError = '';
    try {
      const payload = { current_password: mfaCurrentPassword };
      if (mfaIsEnrolled) payload.totp_code = cleanTotp;
      const result = await postMfaJson('/staff/profile/mfa/recovery-codes', payload);
      mfaCodes = result.recovery_codes;
    } catch (caught) {
      mfaError = caught.message ?? 'Recovery codes could not be regenerated.';
    } finally {
      mfaBusy = false;
    }
  }

  async function beginAuthenticatorReenrollment(event) {
    event.preventDefault();
    mfaBusy = true;
    mfaError = '';
    try {
      mfaSetup = await postMfaJson('/staff/profile/mfa/re-enroll/begin', {
        current_password: mfaCurrentPassword,
      });
    } catch (caught) {
      mfaError = caught.message ?? 'Authenticator re-enrollment could not be started.';
    } finally {
      mfaBusy = false;
    }
  }

  async function confirmAuthenticatorReenrollment(event) {
    event?.preventDefault?.();
    const cleanCode = mfaConfirmationCode.replace(/\D/g, '');
    if (cleanCode.length !== 6) {
      mfaError = 'Please enter a 6-digit verification code.';
      return;
    }
    mfaBusy = true;
    mfaError = '';
    try {
      const result = await postMfaJson('/staff/profile/mfa/re-enroll/confirm', {
        code: cleanCode,
      });
      mfaCodes = result.recovery_codes;
      mfaSetup = null;
    } catch (caught) {
      mfaError = caught.message ?? 'The authenticator code could not be confirmed.';
    } finally {
      mfaBusy = false;
    }
  }

  async function copyMfaSecret() {
    if (!mfaSetup?.secret) return;
    try {
      await navigator.clipboard.writeText(mfaSetup.secret);
      mfaSecretCopied = true;
      setTimeout(() => (mfaSecretCopied = false), 2000);
    } catch (_) {}
  }

  async function copyMfaCodes() {
    try {
      await navigator.clipboard.writeText(mfaCodes.join('\n'));
      mfaCodesCopied = true;
      setTimeout(() => (mfaCodesCopied = false), 2000);
    } catch (_) {
      mfaError = 'Recovery codes could not be copied. Select and copy them manually.';
    }
  }

  function downloadMfaCodes() {
    const blob = new Blob(
      [`NEXIAM staff recovery codes\nSave these codes somewhere safe. Each code works once.\n\n${mfaCodes.join('\n')}\n`],
      { type: 'text/plain' }
    );
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = 'nexiam-staff-recovery-codes.txt';
    anchor.click();
    URL.revokeObjectURL(url);
  }
</script>


<svelte:head><title>My Profile</title></svelte:head>

<StaffLayout {breadcrumbs}>
  {#if page_state === 'confirm_email'}
    <div class="craft-card-depth mx-auto w-full max-w-lg rounded-xl border border-border/70 p-6">
      <div class="mb-6 flex items-center gap-3">
        <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10">
          <Mail class="size-5 text-primary" aria-hidden="true" />
        </div>
        <div>
          <h1 class="text-lg font-semibold text-foreground">Confirm your new email address</h1>
          <p class="text-sm text-muted-foreground">Verify account control to finish changing your staff email.</p>
        </div>
      </div>
      <p class="mb-5 rounded-lg bg-muted/40 p-3 text-sm">
        Confirming email: <strong>{email_confirmation.new_email}</strong>
      </p>
      <form onsubmit={submitEmailConfirmation} class="space-y-4">
        <div class="space-y-2">
          <label for="confirmation_current_password" class="text-sm font-medium leading-none">Current Password</label>
          <Input id="confirmation_current_password" type="password" bind:value={confirmationForm.current_password} required autocomplete="current-password" />
          {#if confirmationForm.errors?.current_password}<p class="text-sm text-destructive">{confirmationForm.errors.current_password}</p>{/if}
        </div>
        {#if email_confirmation.mfa_enrolled}
          <div class="space-y-2">
            <label for="confirmation_totp_code" class="text-sm font-medium leading-none">TOTP code</label>
            <Input id="confirmation_totp_code" bind:value={confirmationForm.totp_code} inputmode="numeric" autocomplete="one-time-code" required />
            {#if confirmationForm.errors?.totp_code}<p class="text-sm text-destructive">{confirmationForm.errors.totp_code}</p>{/if}
          </div>
        {/if}
        <Button type="submit" disabled={confirmationForm.processing}>
          {confirmationForm.processing ? 'Confirming...' : 'Confirm Email Change'}
        </Button>
      </form>
    </div>
  {:else if page_state === 'ready'}
    <div class="w-full max-w-7xl space-y-8">
      <div class="grid grid-cols-1 gap-8 lg:grid-cols-2 lg:items-start">
        <div class="space-y-8">
      <section class="craft-card-depth rounded-xl border border-border/70 p-6">
        <div class="mb-6 flex items-center gap-3">
          <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10">
            <User class="size-5 text-primary" aria-hidden="true" />
          </div>
          <div>
            <h2 class="text-lg font-semibold text-foreground">Profile Information</h2>
            <p class="text-sm text-muted-foreground">Update your name and email address.</p>
          </div>
        </div>

        <form onsubmit={submitName} class="space-y-4">
          <div class="space-y-2">
            <label for="name" class="text-sm font-medium leading-none">Name</label>
            <Input id="name" bind:value={nameForm.name} required autocomplete="name" />
            {#if nameForm.errors?.name}<p class="text-sm text-destructive">{nameForm.errors.name}</p>{/if}
          </div>
          <div class="flex items-center gap-3 pt-2">
            <Button type="submit" disabled={nameForm.processing}>
              {nameForm.processing ? 'Saving...' : 'Save Changes'}
            </Button>
            {#if nameForm.recentlySuccessful}<span class="animate-in text-sm font-medium text-primary">Saved.</span>{/if}
          </div>
        </form>

        <div class="mt-6 space-y-4 border-t border-border pt-5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Email address</p>
              <p class="mt-1 flex items-center gap-2 text-sm font-medium">
                <Mail class="size-4 text-muted-foreground" aria-hidden="true" />{user.email}
              </p>
            </div>
            {#if security.pending_email}
              <div class="flex flex-wrap items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                <p><strong>Pending verification:</strong> {security.pending_email.new_email}. Please check your inbox.</p>
                <Button type="button" variant="outline" class="min-h-9 border-amber-400" onclick={cancelEmailRequest} disabled={emailForm.processing}>
                  {emailForm.processing ? 'Cancelling...' : 'Cancel Request'}
                </Button>
              </div>
            {:else}
              <Button type="button" variant="outline" onclick={() => emailRequestOpen = !emailRequestOpen}>
                {emailRequestOpen ? 'Close' : 'Change Email Address'}
              </Button>
            {/if}
          </div>

          {#if emailRequestOpen && !security.pending_email}
            <form onsubmit={submitEmailRequest} class="space-y-4 rounded-lg border border-border bg-muted/20 p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h3 class="font-semibold">Request an email change</h3>
                  <p class="text-sm text-muted-foreground">Your current email remains active until you verify the new address.</p>
                </div>
                <button type="button" class="rounded-md p-1 text-muted-foreground hover:bg-muted" aria-label="Close email change form" onclick={() => emailRequestOpen = false}>
                  <X class="size-4" />
                </button>
              </div>
              <div class="space-y-2">
                <label for="new_email" class="text-sm font-medium leading-none">New Email</label>
                <Input id="new_email" type="email" bind:value={emailForm.new_email} required autocomplete="email" />
                {#if emailForm.errors?.new_email}<p class="text-sm text-destructive">{emailForm.errors.new_email}</p>{/if}
              </div>
              <div class="space-y-2">
                <label for="email_current_password" class="text-sm font-medium leading-none">Current Password</label>
                <Input id="email_current_password" type="password" bind:value={emailForm.current_password} required autocomplete="current-password" />
                {#if emailForm.errors?.current_password}<p class="text-sm text-destructive">{emailForm.errors.current_password}</p>{/if}
              </div>
              {#if security.mfa_enrolled}
                <div class="space-y-2">
                  <label for="email_totp_code" class="text-sm font-medium leading-none">TOTP code</label>
                  <Input id="email_totp_code" bind:value={emailForm.totp_code} inputmode="numeric" autocomplete="one-time-code" required />
                  {#if emailForm.errors?.totp_code}<p class="text-sm text-destructive">{emailForm.errors.totp_code}</p>{/if}
                </div>
              {/if}
              <div class="flex items-center gap-3">
                <Button type="submit" disabled={emailForm.processing}>{emailForm.processing ? 'Sending...' : 'Send Verification'}</Button>
                {#if emailForm.recentlySuccessful}<span class="text-sm font-medium text-primary">Verification sent.</span>{/if}
              </div>
            </form>
          {/if}


          <div class="flex flex-wrap gap-2 pt-1" aria-label="Staff assignment details">
            {#if user.office}<span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">Office: {user.office}</span>{/if}
            {#if user.status}<span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">Status: {user.status}</span>{/if}
            {#each user.roles ?? [] as role (typeof role === 'string' ? role : role.name)}
              {@const roleName = typeof role === 'string' ? role : (role.display_name ?? role.name ?? 'Staff')}
              <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">Role: {roleName.replaceAll('_', ' ').replace(/\b\w/g, (character) => character.toUpperCase())}</span>
            {/each}
          </div>
        </div>
      </section>
      <AvatarSelector currentAvatar={user.avatar ?? { type: 'initials', preset: null, url: null }} name={user.name ?? ''} />

      <section class="craft-card-depth rounded-xl border border-border/70 p-6">
        <div class="mb-6 flex items-center gap-3">
          <div class="flex size-9 items-center justify-center rounded-lg bg-sky-500/10">
            <MapPin class="size-5 text-sky-600 dark:text-sky-400" aria-hidden="true" />
          </div>
          <div>
            <h2 class="text-lg font-semibold text-foreground">Campus Office Details</h2>
            <p class="text-sm text-muted-foreground">Manage your own campus desk and consultation details on this profile.</p>
          </div>
        </div>
        <form onsubmit={submitDirectory} class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <label for="phone_extension" class="text-sm font-medium leading-none">Phone extension</label>
              <div class="relative">
                <Phone class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <Input id="phone_extension" class="pl-9" bind:value={directoryForm.phone_extension} autocomplete="tel-extension" />
              </div>
              {#if directoryForm.errors?.phone_extension}<p class="text-sm text-destructive">{directoryForm.errors.phone_extension}</p>{/if}
            </div>
            <div class="space-y-2">
              <label for="room_number" class="text-sm font-medium leading-none">Room / office</label>
              <div class="relative">
                <MapPin class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <Input id="room_number" class="pl-9" bind:value={directoryForm.room_number} autocomplete="off" />
              </div>
              {#if directoryForm.errors?.room_number}<p class="text-sm text-destructive">{directoryForm.errors.room_number}</p>{/if}
            </div>
          </div>
          <div class="space-y-2">
            <label for="consultation_hours" class="text-sm font-medium leading-none">Consultation hours</label>
            <div class="relative">
              <Clock3 class="pointer-events-none absolute left-3 top-3 size-4 text-muted-foreground" aria-hidden="true" />
              <textarea
                id="consultation_hours"
                bind:value={directoryForm.consultation_hours}
                rows="3"
                maxlength="255"
                placeholder="e.g. Monday–Friday, 9:00 AM–4:00 PM"
                class="flex w-full rounded-md border border-input bg-background py-2 pl-9 pr-3 text-sm shadow-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50"
              ></textarea>
            </div>
            {#if directoryForm.errors?.consultation_hours}<p class="text-sm text-destructive">{directoryForm.errors.consultation_hours}</p>{/if}
          </div>
          <div class="flex flex-wrap items-center gap-3 pt-1">
            <Button type="submit" disabled={directoryForm.processing}>
              {directoryForm.processing ? 'Saving...' : 'Save directory information'}
            </Button>
            {#if directoryForm.recentlySuccessful}<span class="text-sm font-medium text-primary" role="status">Directory information saved.</span>{/if}
          </div>
        </form>
      </section>
        </div>
        <div class="space-y-8">
          <section class="craft-card-depth rounded-xl border border-border/70 p-6">
            <div class="mb-6 flex items-center gap-3">
              <div class="flex size-9 items-center justify-center rounded-lg bg-amber-500/10">
                <Lock class="size-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
              </div>
              <div>
                <h2 class="text-lg font-semibold text-foreground">Change Password</h2>
                <p class="text-sm text-muted-foreground">Use a strong, unique password to secure your account.</p>
              </div>
            </div>
            <form onsubmit={submitPassword} class="space-y-4">
              <div class="space-y-2">
                <label for="current_password" class="text-sm font-medium leading-none">Current Password</label>
                <Input id="current_password" type="password" bind:value={passwordForm.current_password} required autocomplete="current-password" />
                {#if passwordForm.errors?.current_password}<p class="text-sm text-destructive">{passwordForm.errors.current_password}</p>{/if}
              </div>
              <div class="space-y-2">
                <label for="new_password" class="text-sm font-medium leading-none">New Password</label>
                <Input id="new_password" type="password" bind:value={passwordForm.password} required autocomplete="new-password" />
                {#if passwordForm.errors?.password}<p class="text-sm text-destructive">{passwordForm.errors.password}</p>{/if}
              </div>
              <div class="space-y-2">
                <label for="password_confirmation" class="text-sm font-medium leading-none">Confirm New Password</label>
                <Input id="password_confirmation" type="password" bind:value={passwordForm.password_confirmation} required autocomplete="new-password" />
              </div>
              {#if security?.mfa_enrolled}
                <div class="space-y-2">
                  <label for="password_totp" class="text-sm font-medium leading-none">Authenticator Code</label>
                  <Input id="password_totp" bind:value={passwordForm.totp_code} inputmode="numeric" autocomplete="one-time-code" required pattern="[0-9]{6}" maxlength="6" />
                  {#if passwordForm.errors?.totp_code}<p class="text-sm text-destructive">{passwordForm.errors.totp_code}</p>{/if}
                </div>
              {/if}
              <ul class="grid gap-1 text-xs sm:grid-cols-2" aria-label="Password criteria">
                {#each passwordCriteria as criterion (criterion.label)}
                  <li class={criterion.met ? 'text-emerald-700 dark:text-emerald-400' : 'text-muted-foreground'}>
                    <span aria-hidden="true">{criterion.met ? '✓' : '○'}</span> {criterion.label}
                  </li>
                {/each}
              </ul>
              <div class="flex items-center gap-3 pt-2">
                <Button type="submit" disabled={passwordForm.processing}>
                  {passwordForm.processing ? 'Updating...' : 'Update Password'}
                </Button>
                {#if passwordForm.recentlySuccessful}<span class="animate-in text-sm font-medium text-primary">Updated.</span>{/if}
              </div>
            </form>
          </section>
      <section id="security" class="craft-card-depth scroll-mt-24 rounded-xl border border-border/70 p-6">
        <div class="flex items-center gap-3">
          <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10">
            <ShieldCheck class="size-5 text-primary" aria-hidden="true" />
          </div>
          <div>
            <h2 class="text-lg font-semibold text-foreground">Security &amp; Two-Factor Authentication</h2>
            <p class="text-sm text-muted-foreground">Review the protections on your staff account.</p>
          </div>
        </div>
        <div class="mt-5 flex flex-wrap items-center gap-2">
          {#if mfaIsEnrolled}
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">Two-Factor Authentication Active</span>
            <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground">{security.recovery_codes_remaining} of 8 remaining</span>
          {:else if mfaIsRequired}
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">Two-Factor Required — Not Yet Set Up</span>
          {:else if security.mfa_status === 'pending'}
            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">Two-Factor Authentication Pending</span>
          {:else}
            <span class="rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground">Two-Factor Optional for Your Role</span>
          {/if}
        </div>
        {#if mfaIsEnrolled}
          <p class="mt-4 rounded-lg border border-dashed border-border bg-muted/20 p-3 text-sm text-muted-foreground">
            Two-factor authentication is active on your staff account. Keep your recovery codes in a secure place.
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <Button type="button" variant="outline" onclick={() => openMfaDialog('recovery')}>
              Regenerate Recovery Codes
            </Button>
            <Button type="button" variant="outline" onclick={() => openMfaDialog('reenroll')}>
              Reconfigure Authenticator App
            </Button>
          </div>
        {:else}
          <p class="mt-4 rounded-lg border border-dashed border-border bg-muted/20 p-3 text-sm text-muted-foreground">
            {mfaIsRequired
              ? 'Two-factor authentication is required for your role. Set it up to protect your staff account.'
              : 'Two-factor authentication is optional for your role, but recommended to add an extra layer of protection to your staff account.'}
          </p>
          <div class="mt-4 flex flex-wrap gap-2">
            <Button type="button" onclick={beginMfaSetup}>
              Set Up Two-Factor Authentication
            </Button>
          </div>
        {/if}

        <div class="mt-5 rounded-lg border border-border/70 bg-muted/20 p-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
              <p class="text-sm font-semibold text-foreground">Two-Factor Login Requirement</p>
              {#if canToggleSelfMfa}
                <p class="text-xs text-muted-foreground">
                  {mfaIsRequired || mfaIsEnrolled
                    ? 'Two-factor login is currently required on your account. As an Office Head, you may turn off (pause) two-factor login for your account.'
                    : 'Two-factor login is currently turned off (paused) for your account. As an Office Head, you may turn on two-factor login at any time.'}
                </p>
              {:else}
                <p class="text-xs text-muted-foreground">
                  Your two-factor login requirement is managed by your Office Head or HR Administrator.
                </p>
              {/if}
            </div>
            {#if canToggleSelfMfa}
              <div class="shrink-0">
                {#if mfaIsRequired || mfaIsEnrolled}
                  <Button type="button" variant="outline" size="sm" disabled={mfaPolicyBusy} onclick={() => toggleSelfMfaPolicy('disable')}>
                    {mfaPolicyBusy ? 'Updating…' : 'Turn Off Two-Factor'}
                  </Button>
                {:else}
                  <Button type="button" variant="outline" size="sm" disabled={mfaPolicyBusy} onclick={() => toggleSelfMfaPolicy('require')}>
                    {mfaPolicyBusy ? 'Updating…' : 'Turn On Two-Factor'}
                  </Button>
                {/if}
              </div>
            {/if}
          </div>
        </div>
      </section>
      <section class="craft-card-depth rounded-xl border border-border/70 p-6">
        <div class="mb-5 flex items-center gap-3">
          <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10">
            <Link2 class="size-5 text-primary" aria-hidden="true" />
          </div>
          <div>
            <h2 class="text-lg font-semibold text-foreground">Linked Accounts</h2>
            <p class="text-sm text-muted-foreground">Manage the external identity connected to your staff account.</p>
          </div>
        </div>
        {#if security.google_linked}
          <div class="flex flex-wrap items-center justify-between gap-3">
            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300">Google account linked</span>
            <Button type="button" variant="outline" class="gap-2 text-destructive" onclick={openGoogleUnlinkDialog}>
              <Link2Off class="size-4" aria-hidden="true" />
              Unlink Google Account
            </Button>
          </div>
        {:else}
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">No Google account linked.</p>
            <a href="/auth/google">
              <Button type="button" variant="outline" class="gap-2">
                <Link2 class="size-4" aria-hidden="true" />
                Link Google Account
              </Button>
            </a>
          </div>
        {/if}
      </section>
        </div>
      </div>
    </div>
  {/if}

  <Dialog.Root open={googleUnlinkDialogOpen} onOpenChange={(value) => (value ? (googleUnlinkDialogOpen = true) : closeGoogleUnlinkDialog())}>
    <Dialog.Portal>
      <Dialog.Overlay />
      <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
          <Dialog.Title>Unlink Google Account</Dialog.Title>
          <Dialog.Description>
            Confirm your current password{security.mfa_enrolled ? ' and authenticator code' : ''} to unlink the Google account from your staff profile.
          </Dialog.Description>
        </Dialog.Header>
        <form onsubmit={submitGoogleUnlink} class="space-y-4">
          <div class="space-y-2">
            <label for="google-unlink-current-password" class="text-sm font-medium">Current password</label>
            <Input id="google-unlink-current-password" type="password" bind:value={googleUnlinkForm.current_password} required autocomplete="current-password" />
            {#if googleUnlinkForm.errors?.current_password}<p class="text-sm text-destructive" role="alert">{googleUnlinkForm.errors.current_password}</p>{/if}
          </div>
          {#if security.mfa_enrolled}
            <div class="space-y-2">
              <label for="google-unlink-totp-code" class="text-sm font-medium">Authenticator code</label>
              <Input id="google-unlink-totp-code" bind:value={googleUnlinkForm.totp_code} inputmode="numeric" autocomplete="one-time-code" required pattern="[0-9]{6}" maxlength="6" />
              {#if googleUnlinkForm.errors?.totp_code}<p class="text-sm text-destructive" role="alert">{googleUnlinkForm.errors.totp_code}</p>{/if}
            </div>
          {/if}
          <Dialog.Footer>
            <Button type="button" variant="outline" onclick={closeGoogleUnlinkDialog}>Cancel</Button>
            <Button type="submit" variant="destructive" disabled={googleUnlinkForm.processing}>
              {googleUnlinkForm.processing ? 'Unlinking...' : 'Unlink Google Account'}
            </Button>
          </Dialog.Footer>
        </form>
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>

  <Dialog.Root open={mfaDialogOpen} onOpenChange={(value) => (value ? (mfaDialogOpen = true) : closeMfaDialog())}>
    <Dialog.Portal>
      <Dialog.Overlay />
      <Dialog.Content class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
        <Dialog.Header>
          <Dialog.Title>
            {mfaDialogMode === 'recovery'
              ? 'Regenerate Recovery Codes'
              : mfaDialogMode === 'setup'
                ? 'Set Up Two-Factor Authentication'
                : 'Reconfigure Authenticator App'}
          </Dialog.Title>
          <Dialog.Description>
            {mfaDialogMode === 'recovery'
              ? 'Confirm your current password and authenticator code to replace your recovery codes.'
              : mfaDialogMode === 'setup'
                ? 'Confirm your password, scan the QR code with your authenticator app, and enter the verification code.'
                : 'Confirm your password, enroll your new device, and verify it with a six-digit code.'}
          </Dialog.Description>
        </Dialog.Header>

        {#if mfaError}
          <p class="rounded-md bg-destructive/10 p-3 text-sm text-destructive" role="alert">{mfaError}</p>
        {/if}

        {#if mfaCodes.length > 0}
          <div class="space-y-4">
            <p class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" role="status">
              Save these recovery codes now. They will not be shown again.
            </p>
            <ul class="grid grid-cols-2 gap-2 rounded-lg border border-border bg-muted/20 p-4 font-mono text-sm" aria-label="New recovery codes">
              {#each mfaCodes as code (code)}
                <li>{code}</li>
              {/each}
            </ul>
            <div class="flex flex-wrap gap-2">
              <Button type="button" variant="outline" onclick={copyMfaCodes}>
                {mfaCodesCopied ? 'Copied' : 'Copy Codes'}
              </Button>
              <Button type="button" variant="outline" onclick={downloadMfaCodes}>Download Codes</Button>
            </div>
            <Dialog.Footer class="gap-2 pt-2 sm:justify-end">
              <Button type="button" onclick={closeMfaDialog}>Done</Button>
            </Dialog.Footer>
          </div>
        {:else if mfaDialogMode === 'recovery'}
          <form bind:this={mfaRecoveryFormElement} onsubmit={submitRecoveryCodeRequest} class="space-y-4">
            <div class="space-y-2">
              <Label for="mfa-recovery-password" class="text-sm font-medium">Current password</Label>
              <Input id="mfa-recovery-password" type="password" bind:value={mfaCurrentPassword} required autocomplete="current-password" />
            </div>
            {#if mfaIsEnrolled}
              <div class="space-y-2 text-center">
                <Label for="mfa-recovery-totp" class="text-sm font-medium">Authenticator code</Label>
                <p class="text-xs text-muted-foreground">Enter the 6-digit code from your authenticator app</p>
                <PinInput
                  inputId="mfa-recovery-totp"
                  bind:value={mfaTotpCode}
                  disabled={mfaBusy}
                  onComplete={() => mfaRecoveryFormElement?.requestSubmit()}
                  class="justify-center"
                />
              </div>
            {/if}
            <Dialog.Footer class="gap-2 pt-2 sm:justify-between">
              <Button type="button" variant="outline" onclick={closeMfaDialog}>Cancel</Button>
              <Button type="submit" disabled={mfaBusy || (mfaIsEnrolled && mfaTotpCode.replace(/\D/g, '').length !== 6)}>
                {mfaBusy ? 'Regenerating…' : 'Regenerate Codes'}
              </Button>
            </Dialog.Footer>
          </form>
        {:else if mfaSetup}
          <div class="space-y-4">
            <div class="mx-auto w-fit rounded-xl border border-border/60 bg-white p-3 shadow-xs">
              <img src={mfaSetup.qr_code ?? mfaSetup.qr_svg} alt="QR code for your new authenticator setup" class="size-56" />
            </div>
            {#if mfaSetup.secret}
              <div class="rounded-lg border border-border/60 bg-muted/30 p-3 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-medium text-muted-foreground">Can't scan the QR code?</span>
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    onclick={copyMfaSecret}
                  >
                    {#if mfaSecretCopied}
                      <Check class="size-3 text-emerald-600" /> Copied key
                    {:else}
                      <Copy class="size-3" /> Copy setup key
                    {/if}
                  </button>
                </div>
                <p class="text-xs text-muted-foreground">Enter this setup key in your authenticator app:</p>
                <p class="break-all font-mono text-xs text-foreground bg-background p-2 rounded border border-border/40 select-all" data-testid="mfa-secret">
                  {mfaSetup.secret}
                </p>
              </div>
            {/if}
            {#if mfaDialogMode === 'setup'}
              <p class="text-center text-xs text-muted-foreground" role="timer" aria-live="polite">
                Enrollment expires in <strong>{formatMfaTime(mfaSecondsRemaining)}</strong>.
              </p>
            {/if}
            <form bind:this={mfaConfirmFormElement} onsubmit={mfaDialogMode === 'setup' ? confirmProfileEnrollment : confirmAuthenticatorReenrollment} class="space-y-4 pt-1">
              <div class="space-y-2 text-center">
                <Label for="mfa-confirmation-code" class="text-sm font-medium">Six-digit authenticator code</Label>
                <p class="text-xs text-muted-foreground">Enter the 6-digit code from your authenticator app</p>
                <PinInput
                  inputId="mfa-confirmation-code"
                  bind:value={mfaConfirmationCode}
                  disabled={mfaBusy || (mfaDialogMode === 'setup' && mfaSecondsRemaining === 0)}
                  autofocus
                  onComplete={() => mfaConfirmFormElement?.requestSubmit()}
                  class="justify-center"
                />
              </div>
              <Dialog.Footer class="gap-2 pt-2 sm:justify-between">
                <Button type="button" variant="outline" onclick={closeMfaDialog}>Cancel</Button>
                <Button
                  type="submit"
                  disabled={mfaBusy || mfaConfirmationCode.replace(/\D/g, '').length !== 6 || (mfaDialogMode === 'setup' && mfaSecondsRemaining === 0)}
                  aria-busy={mfaBusy}
                >
                  {mfaBusy ? 'Verifying…' : (mfaDialogMode === 'setup' ? 'Confirm & Enable 2FA' : 'Confirm New Device')}
                </Button>
              </Dialog.Footer>
            </form>
          </div>
        {:else}
          <form onsubmit={mfaDialogMode === 'setup' ? beginProfileEnrollment : beginAuthenticatorReenrollment} class="space-y-4">
            <div class="space-y-2">
              <Label for="mfa-enrollment-password" class="text-sm font-medium">Current password</Label>
              <Input id="mfa-enrollment-password" type="password" bind:value={mfaCurrentPassword} required autocomplete="current-password" />
            </div>
            <Dialog.Footer class="gap-2 pt-2 sm:justify-between">
              <Button type="button" variant="outline" onclick={closeMfaDialog}>Cancel</Button>
              <Button type="submit" disabled={mfaBusy || !mfaCurrentPassword}>{mfaBusy ? 'Preparing…' : 'Continue'}</Button>
            </Dialog.Footer>
          </form>
        {/if}
      </Dialog.Content>
    </Dialog.Portal>
  </Dialog.Root>
</StaffLayout>
