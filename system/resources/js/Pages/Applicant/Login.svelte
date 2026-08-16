<script>
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { onMount } from 'svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Card from '@/Components/ui/card';

  const STORAGE_KEY = 'nexiam.applicant_access_key';

  let { page_state = 'ready', demo = null } = $props();

  let keyVisible = $state(false);

  const form = useForm({
    access_key: demo?.access_key ?? '',
    birthdate: demo?.birthdate ?? '',
  });

  onMount(() => {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved && !demo?.access_key) {
        form.access_key = saved;
      }
    } catch {
      // ignore
    }
  });

  function submit(event) {
    event.preventDefault();
    form.post('/applicant/login');
  }
</script>

<svelte:head>
  <title>Applicant sign in</title>
</svelte:head>

<GuestLayout>
  <div class="mx-auto max-w-md px-6 py-12">
    <Card.Root>
      <Card.Header>
        <Card.Title class="text-2xl">Applicant sign in</Card.Title>
        <Card.Description>
          Use your short access key (ABCD-EFGH) and birthdate. The key may be saved on this device or in your
          email.
        </Card.Description>
        {#if demo?.access_key}
          <div class="mt-2 space-y-1.5 rounded-md border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
            <p>
              {demo.label ?? 'Synthetic demo'}: key
              <span class="font-mono text-foreground">{demo.access_key}</span>
              · birthdate <span class="font-mono text-foreground">{demo.birthdate}</span>
            </p>
            {#if demo.portals?.length}
              <ul class="space-y-0.5">
                {#each demo.portals as portal (portal.key)}
                  <li>
                    <span class="font-mono text-foreground">{portal.key}</span>
                    — {portal.label}
                  </li>
                {/each}
              </ul>
              <p>Other keys require the portal demo seed (`foundation:reset --force`).</p>
            {/if}
          </div>
        {/if}
      </Card.Header>
      <Card.Content>
        <form onsubmit={submit} class="space-y-4">
          <div class="space-y-2">
            <Label for="access_key">Access key</Label>
            <div class="flex gap-2">
              <Input
                id="access_key"
                type={keyVisible ? 'text' : 'password'}
                bind:value={form.access_key}
                required
                autocomplete="off"
                class="min-h-11 font-mono tracking-wider"
                placeholder="ABCD-EFGH"
              />
              <Button
                type="button"
                variant="outline"
                class="min-h-11 shrink-0"
                onclick={() => (keyVisible = !keyVisible)}
              >
                {keyVisible ? 'Hide' : 'Show'}
              </Button>
            </div>
            {#if form.errors.access_key}
              <p class="text-sm text-destructive">{form.errors.access_key}</p>
            {/if}
          </div>
          <div class="space-y-2">
            <Label for="birthdate">Birthdate</Label>
            <Input id="birthdate" type="date" bind:value={form.birthdate} required class="min-h-11" />
            {#if form.errors.birthdate}
              <p class="text-sm text-destructive">{form.errors.birthdate}</p>
            {/if}
          </div>
          <Button type="submit" class="min-h-11 w-full" disabled={form.processing}>
            {form.processing ? 'Signing in…' : 'Sign in'}
          </Button>
        </form>
      </Card.Content>
    </Card.Root>

    <p class="mt-6 text-center text-sm text-muted-foreground">
      Lost your key? Contact the Registrar’s office to request a reissue.
      <br />
      <Link href="/apply" class="underline underline-offset-4">Apply</Link>
      ·
      <Link href="/login" class="underline underline-offset-4">Staff sign in</Link>
    </p>
  </div>
</GuestLayout>
