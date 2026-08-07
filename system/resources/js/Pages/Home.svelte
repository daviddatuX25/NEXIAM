<script>
  import { Link } from '@inertiajs/svelte';
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { Button } from '@/Components/ui/button';

  let { institutionName = 'ISPSC Tagudin' } = $props();
</script>

<svelte:head>
  <title>NEXIAM — Admissions</title>
</svelte:head>

<GuestLayout>
  <div class="mx-auto flex min-h-[70vh] max-w-3xl flex-col justify-center gap-10 px-6 py-16">
    <div class="space-y-4">
      <p class="text-sm font-medium tracking-wide text-muted-foreground">{institutionName}</p>
      <h1 class="text-4xl font-semibold tracking-tight text-foreground sm:text-5xl">NEXIAM</h1>
      <p class="max-w-xl text-base leading-7 text-muted-foreground">
        Admissions testing for application intake, examination, scoring, and result release.
      </p>
    </div>

    <div class="flex flex-wrap gap-3">
      <Button href="/apply" class="min-h-11 px-5">Apply for admission</Button>
      <Button href="/applicant/login" variant="secondary" class="min-h-11 px-5">Applicant sign in</Button>
      <Button href="/login" variant="outline" class="min-h-11 px-5">Staff sign in</Button>
    </div>

    <p class="text-sm text-muted-foreground">
      Already applied? Sign in with your
      <Link href="/applicant/login" class="underline underline-offset-4">access key and birthdate</Link>.
      Your key was shown after submit and may be saved on this device or in your email.
    </p>
  </div>
</GuestLayout>
