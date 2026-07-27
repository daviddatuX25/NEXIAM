<script>
  import PortalGuestLayout from '@/Layouts/PortalGuestLayout.svelte';
  import { useForm, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';

  const form = useForm({
    email: '',
  });

  const page = usePage();
  const flash = $derived($page.props.flash ?? {});

  function handleSubmit(e) {
    e.preventDefault();
    $form.post('/portal/forgot-password');
  }
</script>

<PortalGuestLayout>
  <Card.Root class="shadow-lg">
    <Card.Header>
      <Card.Title class="text-xl">Forgot password</Card.Title>
      <Card.Description>Enter the email you used for your application. If an account exists, we will send reset instructions.</Card.Description>
    </Card.Header>
    <Card.Content>
      <form onsubmit={handleSubmit} class="space-y-4">
        <div class="space-y-2">
          <label for="email" class="text-sm font-medium leading-none">Email</label>
          <Input
            id="email"
            type="email"
            bind:value={$form.email}
            placeholder="you@example.com"
            autocomplete="email"
            required
            aria-invalid={!!$form.errors?.email}
          />
          {#if $form.errors?.email}
            <p class="text-sm text-destructive">
              {typeof $form.errors.email === 'string' ? $form.errors.email : $form.errors.email[0]}
            </p>
          {/if}
        </div>

        <Button type="submit" class="w-full min-h-[44px]" disabled={$form.processing}>
          {$form.processing ? 'Sending...' : 'Send reset link'}
        </Button>
      </form>

      <p class="mt-4 text-center text-sm text-muted-foreground">
        <Link href="/login" class="text-primary hover:underline">Back to sign in</Link>
      </p>
    </Card.Content>
  </Card.Root>
</PortalGuestLayout>
