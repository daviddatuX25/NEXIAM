<script>
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';

  const STORAGE_KEY = 'nexiam.applicant_access_key';

  let { reference_code = null, access_key = null } = $props();

  let savedNotice = $state('');
  let keyVisible = $state(false);

  const form = useForm({
    access_key: access_key ?? '',
    birthdate: '',
  });

  function saveOnDevice() {
    if (!form.access_key) {
      savedNotice = 'No access key is available on this page. Check your email or contact the Registrar.';
      return;
    }
    try {
      localStorage.setItem(STORAGE_KEY, form.access_key);
      savedNotice = 'Access key saved on this device. You will still need your birthdate to sign in.';
    } catch {
      savedNotice = 'This browser blocked saving. Copy the key somewhere safe instead.';
    }
  }

  function continueToPortal(event) {
    event.preventDefault();
    if (form.access_key) {
      try {
        localStorage.setItem(STORAGE_KEY, form.access_key);
      } catch {
        // optional
      }
    }
    form.post('/applicant/login');
  }
</script>

<svelte:head>
  <title>Application submitted</title>
</svelte:head>

<GuestLayout>
  <div class="mx-auto max-w-lg space-y-6 px-6 py-16">
    <div class="space-y-2">
      <h1 class="text-3xl font-semibold tracking-tight">Application submitted</h1>
      <p class="text-muted-foreground">
        Save your applicant reference and access key. You need the key plus your birthdate to open the
        applicant portal — you do not need to wait for email.
      </p>
    </div>

    {#if reference_code}
      <div class="rounded-md border bg-muted/40 px-4 py-5">
        <p class="text-sm text-muted-foreground">Applicant reference</p>
        <p class="mt-1 font-mono text-2xl font-semibold tracking-wide">{reference_code}</p>
      </div>
    {/if}

    {#if access_key || form.access_key}
      <div class="space-y-3 rounded-md border border-amber-500/40 bg-amber-500/5 px-4 py-5">
        <p class="text-sm font-medium text-foreground">Your access key</p>
        <p class="text-sm text-muted-foreground">
          Short key for paper notes (format ABCD-EFGH). Hidden by default — show to copy, then save on this
          device or write it down. A copy is also emailed when delivery is enabled.
        </p>
        <div class="flex flex-wrap items-center gap-2">
          <Input
            type={keyVisible ? 'text' : 'password'}
            bind:value={form.access_key}
            readonly
            autocomplete="off"
            class="min-h-11 max-w-xs font-mono tracking-wider"
          />
          <Button type="button" variant="outline" class="min-h-11" onclick={() => (keyVisible = !keyVisible)}>
            {keyVisible ? 'Hide' : 'Show'}
          </Button>
        </div>
        <Button type="button" variant="secondary" class="min-h-11" onclick={saveOnDevice}>
          Save on this device
        </Button>
        {#if savedNotice}
          <p class="text-sm text-muted-foreground" role="status">{savedNotice}</p>
        {/if}
      </div>
    {:else}
      <p class="rounded-md border px-4 py-3 text-sm text-muted-foreground">
        The access key was already shown after submit. If you lost it, check your email or ask the Registrar
        to reissue a key.
      </p>
    {/if}

    <form onsubmit={continueToPortal} class="space-y-4 rounded-md border px-4 py-5">
      <p class="text-sm font-medium text-foreground">Continue to applicant portal</p>
      <p class="text-sm text-muted-foreground">
        Confirm your birthdate (the same date you entered on the application) to sign in with your access key.
      </p>
      {#if !access_key}
        <div class="space-y-2">
          <Label for="access_key">Access key</Label>
          <Input
            id="access_key"
            type="password"
            bind:value={form.access_key}
            required
            autocomplete="off"
            class="min-h-11 font-mono tracking-wider"
            placeholder="ABCD-EFGH"
          />
        </div>
      {/if}
      <div class="space-y-2">
        <Label for="birthdate">Birthdate</Label>
        <Input id="birthdate" type="date" bind:value={form.birthdate} required class="min-h-11" />
        {#if form.errors.birthdate}
          <p class="text-sm text-destructive">{form.errors.birthdate}</p>
        {/if}
        {#if form.errors.access_key}
          <p class="text-sm text-destructive">{form.errors.access_key}</p>
        {/if}
      </div>
      <Button type="submit" class="min-h-11 w-full sm:w-auto" disabled={form.processing || !form.access_key}>
        {form.processing ? 'Signing in…' : 'Go to applicant portal'}
      </Button>
    </form>

    <div class="space-y-2 text-sm leading-6 text-muted-foreground">
      <p class="font-medium text-foreground">Also prepare for Registrar verification</p>
      <ul class="list-disc space-y-1 pl-5">
        <li>Form 138 / TOR, Good Moral Certificate, 2×2 IDs, and PSA birth certificate</li>
      </ul>
    </div>

    <p class="text-sm text-muted-foreground">
      Returning later?
      <Link href="/applicant/login" class="underline underline-offset-4">Applicant sign in</Link>
      ·
      <Link href="/" class="underline underline-offset-4">Home</Link>
    </p>
  </div>
</GuestLayout>
