<script>
  import { Button } from '@/Components/ui/button';
  
  let {
    title = 'Ready to streamline your admission process?',
    description = 'Join the digital transformation of student admissions with SecureCAT',
    actions = [
      { label: 'Staff Portal', href: '#', variant: 'default' },
      { label: 'Applicant Portal', href: '#', variant: 'outline' }
    ]
  } = $props();
</script>

<section class="relative py-20 md:py-28 px-4 overflow-hidden bg-gradient-to-br from-primary/20 via-accent/10 to-primary/10">
  <!-- Background Pattern -->
  <div class="absolute inset-0 bg-grid-pattern opacity-5"></div>
  
  <div class="relative z-10 max-w-4xl mx-auto text-center">
    <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-6">
      {title}
    </h2>
    
    <p class="text-lg md:text-xl text-muted-foreground mb-10 max-w-2xl mx-auto">
      {description}
    </p>
    
    <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
      {#each actions as action}
        <Button 
          href={action.href}
          variant={action.variant || 'default'}
          size="lg"
          class="min-h-[44px] px-8 text-base"
        >
          {#snippet children()}
            {action.label}
          {/snippet}
        </Button>
      {/each}
    </div>
    
    <!-- Additional Info -->
    <div class="mt-16 pt-8 border-t border-border/50">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-sm">
        <div>
          <p class="font-semibold mb-2">For Registrar Office</p>
          <p class="text-muted-foreground">Manage applications and scheduling</p>
        </div>
        <div>
          <p class="font-semibold mb-2">For Guidance Office</p>
          <p class="text-muted-foreground">Handle examinations and consultation</p>
        </div>
        <div>
          <p class="font-semibold mb-2">For Applicants</p>
          <p class="text-muted-foreground">Track your admission progress</p>
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
