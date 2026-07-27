<script>
  import { ArrowRight } from 'lucide-svelte';
  
  let {
    phases = [
      {
        number: 1,
        name: 'Application',
        description: 'Apply online, then submit your physical requirements at the Registrar to secure a schedule.',
        office: 'Registrar',
        color: '#C8401A'
      },
      {
        number: 2,
        name: 'Slot Confirmation',
        description: 'Receive your verified exam schedule, complete with room assignment and testing guidelines.',
        office: 'Registrar',
        color: '#1A5FC8'
      },
      {
        number: 3,
        name: 'Examination Day',
        description: 'Take your secure admission test in our designated testing centers with trained proctors.',
        office: 'Guidance',
        color: '#1A9C55'
      },
      {
        number: 4,
        name: 'Evaluation',
        description: 'Scores are computed accurately with strict oversight from test administrators to ensure exam security.',
        office: 'Guidance',
        color: '#8B2AC8'
      },
      {
        number: 5,
        name: 'Results & Consultation',
        description: 'Wait for the scheduled release to personally receive academic advice and claim your official result sheet.',
        office: 'Guidance',
        color: '#C87A1A'
      }
    ]
  } = $props();
</script>

<section class="py-4 md:py-8 px-4 w-full relative z-10">
  <div class="max-w-[85rem] mx-auto">
    <!-- Desktop: Premium Glass Cards Timeline -->
    <div class="hidden lg:flex items-stretch justify-between w-full">
      {#each phases as phase, index}
        <div class="flex-1 group relative flex items-center animate-in zoom-in-95 duration-500" style="animation-delay: {index * 100}ms; animation-fill-mode: both;">
          
          <!-- The Step Card -->
          <div class="w-full h-full relative overflow-hidden rounded-[1.5rem] border border-border/40 bg-card/40 backdrop-blur-xl p-6 transition-all duration-500 hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:border-foreground/20 hover:-translate-y-2 flex flex-col">
            <!-- Ambient Colored Glow -->
            <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-[0.08] blur-3xl transition-opacity duration-500 group-hover:opacity-30" style="background-color: {phase.color}; pointer-events: none;"></div>
            
            <!-- Header: Number & Title -->
            <div class="flex items-start gap-4 mb-4 relative z-10">
              <div class="w-12 h-12 shrink-0 rounded-2xl flex items-center justify-center text-white font-extrabold text-xl shadow-lg ring-1 ring-white/20 transition-transform duration-500 group-hover:scale-110" style="background-color: {phase.color}; background-image: linear-gradient(to bottom right, rgba(255,255,255,0.2), transparent);">
                {phase.number}
              </div>
              <h3 class="font-bold text-base text-foreground leading-tight pt-1.5 group-hover:text-primary transition-colors duration-300">
                {phase.name}
              </h3>
            </div>
            
            <!-- Description -->
            <p class="text-[13px] text-muted-foreground flex-grow relative z-10 leading-relaxed group-hover:text-foreground/80 transition-colors">
              {phase.description}
            </p>

            <!-- Footer Badge -->
            <div class="mt-5 relative z-10">
              <span class="inline-flex items-center text-[10px] uppercase tracking-widest font-black px-3 py-1.5 rounded-full border border-transparent group-hover:border-current/20 transition-all duration-300" style="background-color: {phase.color}15; color: {phase.color}">
                {phase.office}
              </span>
            </div>
          </div>
        </div>

        <!-- Inline Flex Connector Arrow -->
        {#if index < phases.length - 1}
          <div class="flex-shrink-0 w-10 flex items-center justify-center text-border/60 animate-in fade-in duration-700" style="animation-delay: {index * 100 + 50}ms;">
            <ArrowRight class="w-5 h-5 stroke-[2.5]" />
          </div>
        {/if}
      {/each}
    </div>
    
    <!-- Mobile/Tablet: Vertical Glass Timeline -->
    <div class="lg:hidden relative space-y-3 pt-2">
      <!-- Continuous vertical line -->
      <div class="absolute left-[17px] top-4 bottom-4 w-0.5 bg-border/40 rounded-full z-0"></div>
      
      {#each phases as phase, index}
        <div class="relative z-10 flex gap-3.5 animate-in fade-in slide-in-from-right-4 duration-500" style="animation-delay: {index * 100}ms; animation-fill-mode: both;">
          <!-- Node -->
          <div class="shrink-0 mt-1.5">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white font-black text-sm shadow-sm ring-[3px] ring-background transition-transform duration-300" style="background-color: {phase.color}; background-image: linear-gradient(to bottom right, rgba(255,255,255,0.2), transparent);">
              {phase.number}
            </div>
          </div>
          
          <!-- Card -->
          <div class="flex-1 relative overflow-hidden rounded-[1rem] border border-border/40 bg-card/40 backdrop-blur-xl p-3.5 shadow-sm">
            <div class="absolute -top-10 -right-10 w-24 h-24 rounded-full opacity-[0.1] blur-2xl pointer-events-none" style="background-color: {phase.color};"></div>
            
            <div class="flex items-center justify-between mb-1 relative z-10 gap-2">
              <h3 class="font-bold text-[15px] text-foreground leading-tight">{phase.name}</h3>
              <span class="shrink-0 inline-flex items-center text-[9px] uppercase tracking-widest font-bold px-2 py-0.5 rounded-full" style="background-color: {phase.color}15; color: {phase.color}">
                {phase.office}
              </span>
            </div>
            
            <p class="text-xs text-muted-foreground relative z-10 leading-snug">{phase.description}</p>
          </div>
        </div>
      {/each}
    </div>
  </div>
</section>
