<script>
  import PortalGuestLayout from '@/Layouts/PortalGuestLayout.svelte';
  import { useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import PasswordStrengthInput from '@/Components/PasswordStrengthInput.svelte';
  import { allRequirementsMet, passwordsMatch } from '@/lib/password-strength.js';
  import * as Card from '@/Components/ui/card';

  let { token, email } = $props();

  const form = useForm({
    password: '',
    password_confirmation: '',
  });

  const allValid = $derived(
    allRequirementsMet($form.password) && passwordsMatch($form.password, $form.password_confirmation)
  );

  function handleSubmit(e) {
    e.preventDefault();
    $form.post(`/portal/reset/${token}`);
  }
</script>

<PortalGuestLayout>
  <Card.Root class="shadow-lg">
    <Card.Header>
      <Card.Title class="text-xl">Reset your password</Card.Title>
      <Card.Description
        >Create a strong password for {email}. Use at least 8 characters with upper and lower case
        and a number. 12+ characters recommended for stronger security.</Card.Description
      >
    </Card.Header>
    <Card.Content>
      <form onsubmit={handleSubmit} class="space-y-4">
        <PasswordStrengthInput
          bind:password={$form.password}
          bind:passwordConfirmation={$form.password_confirmation}
          id="password"
          label="New password"
          errors={$form.errors}
        />
        <Button
          type="submit"
          class="w-full min-h-[44px]"
          disabled={!allValid || $form.processing}
        >
          {$form.processing ? 'Resetting...' : 'Reset password'}
        </Button>
      </form>
    </Card.Content>
  </Card.Root>
</PortalGuestLayout>
