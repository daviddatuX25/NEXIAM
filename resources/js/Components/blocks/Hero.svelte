<script>
  import { Button } from '@/Components/ui/button';
  import { Check } from 'lucide-svelte';
  
  let { 
    title = 'SecureCAT: Computerized Admission & Testing',
    subtitle = 'Digitizing the complete student admission lifecycle from application to consultation',
    primaryAction = null,
    secondaryAction = null
  } = $props();
  
  function scrollToFeatures() {
    const featuresSection = document.getElementById('benefits');
    if (featuresSection) {
      featuresSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
</script>

<section class="relative min-h-[600px] flex items-center justify-center overflow-hidden bg-gradient-to-br from-primary/10 via-background to-accent/10">
  <!-- Background Pattern -->
  <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
  
  <div class="relative z-10 max-w-6xl mx-auto px-4 py-20 text-center">
    <div class="space-y-6">
      <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight">
        {title}
      </h1>
      
      <p class="text-lg md:text-xl text-muted-foreground max-w-3xl mx-auto leading-relaxed">
        {subtitle}
      </p>
      
      <div class="flex flex-col sm:flex-row gap-4 justify-center items-center pt-6">
        {#if primaryAction}
          <Button 
            href={primaryAction.href} 
            size="lg"
            class="min-h-[44px] px-8 text-base"
          >
            {#snippet children()}
              {primaryAction.label}
            {/snippet}
          </Button>
        {:else}
          <Button 
            href="#" 
            size="lg"
            class="min-h-[44px] px-8 text-base"
          >
            {#snippet children()}
              Get Started
            {/snippet}
          </Button>
        {/if}
        
        {#if secondaryAction}
          <Button 
            href={secondaryAction.href}
            variant="outline"
            size="lg"
            class="min-h-[44px] px-8 text-base"
          >
            {#snippet children()}
              {secondaryAction.label}
            {/snippet}
          </Button>
        {:else}
          <Button 
            onclick={scrollToFeatures}
            variant="outline"
            size="lg"
            class="min-h-[44px] px-8 text-base"
          >
            {#snippet children()}
              Learn More
            {/snippet}
          </Button>
        {/if}
      </div>
      
      <div class="pt-8 flex justify-center gap-6 text-sm text-muted-foreground">
        <div class="flex items-center gap-2">
          <Check class="w-4 h-4 text-primary" />
          <span>5-Phase Pipeline</span>
        </div>
        <div class="flex items-center gap-2">
          <Check class="w-4 h-4 text-primary" />
          <span>AI-Assisted Scheduling</span>
        </div>
        <div class="flex items-center gap-2">
          <Check class="w-4 h-4 text-primary" />
          <span>Real-Time Monitoring</span>
        </div>
      </div>
    </div>
  </div>
</section>

<style>
  .bg-grid-pattern {
    background-image: 
      linear-gradient(to right, currentColor 1px, transparent 1px),
      linear-gradient(to bottom, currentColor 1px, transparent 1px);
    background-size: 40px 40px;
  }
</style>
