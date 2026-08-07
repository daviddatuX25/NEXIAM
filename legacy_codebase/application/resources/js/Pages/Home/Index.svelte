<script>
  import HomeLayout from '@/Layouts/HomeLayout.svelte';
  import { Link, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import ProcessFlow from '@/Components/blocks/ProcessFlow.svelte';

  let { activeSeason = null } = $props();
  
  const page = usePage();
  const auth = $derived($page.props.auth ?? {});
  const isAuthenticated = $derived(!!auth.user || !!auth.applicant);
</script>

<svelte:head>
  <title>Welcome - Ilocos Sur Polytechnic State College</title>
</svelte:head>

<HomeLayout>
  <!-- One-screener enforced container -->
  <div class="relative overflow-hidden bg-background min-h-[calc(100vh-theme(spacing.16))] flex flex-col justify-between">
    <!-- Dynamic Glassmorphic Background Objects -->
    <div class="absolute top-[-10%] left-[-10%] h-[50vw] w-[50vw] rounded-full bg-gradient-to-tr from-primary/10 to-blue-500/5 blur-[120px] pointer-events-none animate-in fade-in duration-1000" aria-hidden="true"></div>
    <div class="absolute bottom-[-10%] right-[-5%] h-[40vw] w-[40vw] rounded-full bg-gradient-to-bl from-emerald-500/10 to-primary/10 blur-[120px] pointer-events-none animate-in fade-in duration-1000 delay-300" aria-hidden="true"></div>
    
    <!-- Top Half: Hero and CTA -->
    <div class="relative z-10 flex flex-col items-center justify-center flex-grow px-4 sm:px-6 lg:px-8 mt-8 md:mt-12">
      
      {#if activeSeason}
        <Badge variant="outline" class="mb-8 px-5 py-2 text-sm font-bold tracking-wide border-primary/40 bg-primary/10 text-primary shadow-sm animate-in fade-in slide-in-from-top-4 duration-500 inline-flex items-center backdrop-blur-md">
          <span class="mr-2 shrink-0 h-2 w-2 rounded-full bg-primary animate-pulse"></span>
          Admissions are open for {activeSeason.name}
        </Badge>
      {/if}

      <div class="text-center max-w-4xl mx-auto">
        <h2 class="text-base md:text-lg font-bold text-muted-foreground uppercase tracking-[0.2em] mb-4 animate-in fade-in slide-in-from-bottom-3 duration-700">
          Ilocos Sur Polytechnic State College<br class="sm:hidden" /> <span class="hidden sm:inline">&mdash;</span> Tagudin Campus
        </h2>

        <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight text-foreground mb-8 text-balance drop-shadow-sm leading-tight animate-in fade-in slide-in-from-bottom-4 duration-700 delay-100">
          Secure Your Future.<br class="hidden sm:block" />
          <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-emerald-500">Apply for Admissions Today.</span>
        </h1>

        <div class="mt-8 w-full flex justify-center z-20 animate-in fade-in slide-in-from-bottom-5 duration-700 delay-150">
            <Link href="/apply">
              <Button size="lg" class="h-14 px-12 text-lg font-extrabold shadow-xl shadow-primary/30 hover:shadow-primary/50 hover:-translate-y-1 transition-all rounded-full bg-gradient-to-r from-primary to-emerald-600 hover:from-primary/95 hover:to-emerald-500 border-0 uppercase tracking-widest text-primary-foreground">
                Admissions
              </Button>
            </Link>
        </div>
      </div>
    </div>

    <!-- Bottom Half: Process Flow (Journey) -->
    <div class="relative z-20 w-full bg-background/40 backdrop-blur-sm animate-in fade-in slide-in-from-bottom-10 duration-1000 delay-300">
       <ProcessFlow />
    </div>

  </div>
</HomeLayout>
