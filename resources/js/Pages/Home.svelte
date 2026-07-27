<script>
  import { Link } from '@inertiajs/svelte';
  import Hero from '@/Components/blocks/Hero.svelte';
  import Benefits from '@/Components/blocks/Benefits.svelte';
  import PerspectiveSection from '@/Components/blocks/PerspectiveSection.svelte';
  import StudentPerspectiveSection from '@/Components/blocks/StudentPerspectiveSection.svelte';
  import ProcessFlow from '@/Components/blocks/ProcessFlow.svelte';
  import FutureVision from '@/Components/blocks/FutureVision.svelte';
  import CallToAction from '@/Components/blocks/CallToAction.svelte';

  // Data passed from controller
  let { systemName = 'SecureCAT' } = $props();
</script>

<svelte:head>
  <title>{systemName} - Computerized Admission & Testing</title>
  <meta name="description" content="From application to consultation—apply and track your progress as a student, or manage admissions end-to-end as staff. Digitizing the complete student admission lifecycle." />
</svelte:head>

<main class="min-h-screen">
  <Hero
    title="SecureCAT: Computerized Admission & Testing"
    subtitle="From application to consultation—for students: apply and track your progress. For staff: manage admissions end-to-end."
    primaryAction={{ label: 'Apply Online', href: '/apply' }}
    secondaryAction={{ label: 'Staff Login', href: '/login' }}
  />

  <Benefits />

  <PerspectiveSection />

  <StudentPerspectiveSection />

  <ProcessFlow />

  <FutureVision />

  <CallToAction
    title="Ready to get started?"
    description="Apply online, log in to the applicant portal, or sign in as staff."
    actions={[
      { label: 'Apply Online', href: '/apply', variant: 'default' },
      { label: 'Applicant Portal', href: '/login', variant: 'outline' },
      { label: 'Staff Login', href: '/login', variant: 'outline' },
    ]}
  />
</main>

<footer class="bg-card border-t border-border py-8 px-4">
  <div class="max-w-7xl mx-auto text-center text-sm text-muted-foreground">
    <p class="mb-2">
      <span class="font-semibold text-foreground">SecureCAT</span> - Computerized Admission & Testing System
    </p>
    <p class="mb-2">
      Information Assurance and Security Academic Project
    </p>
    <p>
      <Link href="/apply" class="text-primary hover:underline">Apply online</Link>
      {' | '}
      <Link href="/login" class="text-primary hover:underline">Applicant portal</Link>
      {' | '}
      <Link href="/login" class="text-primary hover:underline">Staff login</Link>
    </p>
  </div>
</footer>
