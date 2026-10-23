<script>
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { router, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Card from '@/Components/ui/card';
  import { PinInput } from '@/Components/ui/pin-input';

  const form = useForm({
    code: '',
    recovery_code: '',
  });
  let useRecoveryCode = $state(false);
  let formElement = $state(null);
  const activeField = $derived(useRecoveryCode ? 'recovery_code' : 'code');
  const activeError = $derived(form.errors[activeField]);

  function submit(event) {
    event.preventDefault();
    form.transform((data) => ({
      code: useRecoveryCode ? null : data.code,
      recovery_code: useRecoveryCode ? data.recovery_code : null,
    }));
    form.post('/two-factor-challenge');
  }

  function cancel() {
    router.delete('/two-factor-challenge');
  }
</script>

<svelte:head>
  <title>Two-Factor Authentication</title>
</svelte:head>

<GuestLayout>
  <Card.Root variant="glass" class="rounded-lg shadow-xl backdrop-blur-xl" style="font-family: 'IBM Plex Sans', sans-serif">
    <Card.Header>
      <Card.Title class="text-2xl">Two-Factor Authentication</Card.Title>
      <Card.Description>Enter the 6-digit code from your authenticator app</Card.Description>
    </Card.Header>
    <Card.Content>
      <form bind:this={formElement} onsubmit={submit} class="space-y-5">
        {#if useRecoveryCode}
          <div class="space-y-2">
            <Label for="recovery-code">Recovery code</Label>
            <Input
              id="recovery-code"
              bind:value={form.recovery_code}
              autocomplete="off"
              autofocus
              aria-invalid={!!activeError}
              aria-describedby={activeError ? 'challenge-error' : undefined}
              required
            />
          </div>
        {:else}
          <div class="space-y-2">
            <Label for="code">Authentication code</Label>
            <PinInput
              inputId="code"
              bind:value={form.code}
              disabled={form.processing}
              autofocus
              onComplete={() => formElement?.requestSubmit()}
              class="justify-center"
            />
          </div>
        {/if}

        {#if activeError}
          <p id="challenge-error" class="text-sm text-destructive" role="alert">{activeError}</p>
        {/if}

        <button
          type="button"
          class="text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4"
          onclick={() => {
            useRecoveryCode = !useRecoveryCode;
            form.clearErrors();
          }}
          aria-label={useRecoveryCode ? 'Use an authenticator code instead' : 'Use a recovery code instead'}
        >
          {useRecoveryCode ? 'Use an authenticator code' : 'Use a recovery code instead'}
        </button>

        <div class="flex flex-col gap-2.5">
          <Button type="submit" class="w-full" disabled={form.processing} aria-busy={form.processing}>
            {form.processing ? 'Verifying…' : 'Verify and continue'}
          </Button>
          <Button type="button" variant="outline" class="w-full" disabled={form.processing} onclick={cancel}>
            Cancel
          </Button>
        </div>
      </form>
    </Card.Content>
  </Card.Root>
</GuestLayout>
