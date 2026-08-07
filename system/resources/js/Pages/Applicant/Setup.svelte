<script>
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';

  let { page_state = 'ready' } = $props();

  const form = useForm({
    token: '',
    email: '',
    password: '',
    password_confirmation: '',
    name: '',
  });

  function submit(event) {
    event.preventDefault();
    form.post('/applicant/setup');
  }
</script>

<svelte:head><title>Applicant setup</title></svelte:head>

<GuestLayout>
  <div class="mx-auto max-w-md space-y-4 p-6">
    <E2PageState state={page_state} />
    <form onsubmit={submit} class="space-y-3">
      <div><Label for="token">Setup token</Label><Input id="token" bind:value={form.token} required /></div>
      <div><Label for="name">Display name</Label><Input id="name" bind:value={form.name} required /></div>
      <div><Label for="email">Login email</Label><Input id="email" type="email" bind:value={form.email} required /></div>
      <div><Label for="password">Password</Label><Input id="password" type="password" bind:value={form.password} required /></div>
      <div><Label for="password_confirmation">Confirm</Label><Input id="password_confirmation" type="password" bind:value={form.password_confirmation} required /></div>
      <Button type="submit">Complete setup</Button>
    </form>
  </div>
</GuestLayout>
