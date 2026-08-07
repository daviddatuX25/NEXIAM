<script>
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { useForm, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Checkbox } from '@/Components/ui/checkbox';
  import * as Card from '@/Components/ui/card';

  const page = usePage();

  const form = useForm({
    email: 'registrar.clerk@nexiam.test',
    password: 'password',
    remember: false,
  });

  const errors = $derived(page.props.errors ?? {});

  function submit(event) {
    event.preventDefault();
    form.post('/login');
  }
</script>

<svelte:head>
  <title>Staff login</title>
</svelte:head>

<GuestLayout>
  <Card.Root variant="glass">
    <Card.Header>
      <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">NEXIAM</p>
      <Card.Title class="text-2xl">Staff login</Card.Title>
      <Card.Description>
        Synthetic E1 identity seam. Capability grants remain GF-21-enforced.
      </Card.Description>
    </Card.Header>
    <Card.Content>
      <form onsubmit={submit} class="space-y-4">
        <div class="space-y-2">
          <Label for="email">Email</Label>
          <Input
            id="email"
            type="email"
            bind:value={form.email}
            autocomplete="username"
            required
            aria-invalid={!!errors?.email}
          />
          {#if errors?.email}
            <p class="text-sm text-destructive">{errors.email}</p>
          {/if}
        </div>

        <div class="space-y-2">
          <Label for="password">Password</Label>
          <Input
            id="password"
            type="password"
            bind:value={form.password}
            autocomplete="current-password"
            required
          />
        </div>

        <label class="flex items-center gap-2 text-sm text-muted-foreground">
          <Checkbox bind:checked={form.remember} />
          Remember me
        </label>

        <Button type="submit" class="w-full" disabled={form.processing}>
          {form.processing ? 'Signing in…' : 'Sign in'}
        </Button>
      </form>
    </Card.Content>
  </Card.Root>
</GuestLayout>
