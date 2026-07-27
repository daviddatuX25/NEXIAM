<script>
  import HomeLayout from '@/Layouts/HomeLayout.svelte';
  import { Badge } from '@/Components/ui/badge';
  import Benefits from '@/Components/blocks/Benefits.svelte';
  import PerspectiveSection from '@/Components/blocks/PerspectiveSection.svelte';
  import StudentPerspectiveSection from '@/Components/blocks/StudentPerspectiveSection.svelte';
  import FutureVision from '@/Components/blocks/FutureVision.svelte';

  // Data passed from controller
  let { systemName = 'SecureCAT' } = $props();
</script>

<svelte:head>
  <title>About {systemName} - Computerized Admission & Testing</title>
  <meta name="description" content="Discover SecureCAT. From application to consultation, digitizing the complete student admission lifecycle." />
</svelte:head>

<HomeLayout>
  <!-- Replace the existing giant Hero with a simpler informational Header for the About page -->
  <div class="bg-card border-b border-border py-16 px-4">
    <div class="max-w-4xl mx-auto text-center">
      <Badge class="mb-4 bg-primary/10 text-primary hover:bg-primary/20 border-transparent shadow-sm">About The System</Badge>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-foreground mb-6">
        SecureCAT Architecture
      </h1>
      <p class="text-lg md:text-xl text-muted-foreground max-w-2xl mx-auto">
        From application to consultation—digitizing the complete student admission lifecycle. Information Assurance and Security Academic Project.
      </p>
    </div>
  </div>

  <Benefits />
  <PerspectiveSection />
  <StudentPerspectiveSection />
  <FutureVision />

</HomeLayout>
