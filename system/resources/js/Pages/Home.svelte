<script>
  import { Link } from '@inertiajs/svelte';
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { CheckCircle2, Circle } from 'lucide-svelte';

  let { institutionName = 'ISPSC Tagudin', activeSeason = null } = $props();

  const steps = [
    { label: 'Apply', summary: 'Submit your application' },
    { label: 'Review', summary: 'Registrar checks it' },
    { label: 'Exam', summary: 'Reserve your slot' },
    { label: 'Results', summary: 'See your scores' },
    { label: 'Enroll', summary: 'Confirm your program' },
  ];
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

      {#if activeSeason}
        <Badge variant="outline" class="gap-2 border-primary/40 bg-primary/10 px-4 py-1.5 text-primary">
          <span class="size-1.5 shrink-0 animate-pulse rounded-full bg-current" aria-hidden="true"></span>
          Admissions open for {activeSeason.name}{#if activeSeason.window} · {activeSeason.window}{/if}
        </Badge>
      {/if}
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

    <ol class="grid gap-3 sm:grid-cols-5" aria-label="Admissions process">
      {#each steps as step, i (step.label)}
        <li class="flex items-start gap-2">
          <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary" aria-hidden="true">
            <CheckCircle2 class="size-4" />
          </span>
          <span class="min-w-0">
            <span class="block text-sm font-semibold">{i + 1}. {step.label}</span>
            <span class="block text-xs leading-relaxed text-muted-foreground">{step.summary}</span>
          </span>
        </li>
      {/each}
    </ol>
  </div>
</GuestLayout>
