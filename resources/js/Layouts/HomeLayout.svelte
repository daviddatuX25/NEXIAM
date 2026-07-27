<script>
  import { Link, usePage } from '@inertiajs/svelte';
  import ToastManager from '@/Components/ToastManager.svelte';

  let { children } = $props();
  const page = usePage();
  const auth = $derived($page.props.auth ?? {});
  const isAuthenticated = $derived(!!auth.user || !!auth.applicant);
</script>

<ToastManager />

<div class="min-h-screen bg-background flex flex-col font-sans">
  <!-- Minimal Header -->
  <header class="border-b border-border/40 bg-card/50 backdrop-blur sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center h-16">
        <div class="flex items-center gap-2">
          <img src="/img/securecat-logo.png" alt="SecureCAT" class="w-8 h-8 rounded-lg shadow-sm" />
          <span class="font-bold text-lg tracking-tight text-foreground hidden sm:block">SecureCAT</span>
        </div>
        
        <nav class="flex items-center gap-4 sm:gap-6 text-sm font-medium">
          <Link href="/" class="text-muted-foreground hover:text-foreground transition-colors {page.url === '/' ? 'text-primary' : ''}">Home</Link>
          <Link href="/apply" class="text-muted-foreground hover:text-foreground transition-colors {page.url === '/apply' ? 'text-primary' : ''}">Application</Link>
          <Link href="/about" class="text-muted-foreground hover:text-foreground transition-colors {page.url === '/about' ? 'text-primary' : ''}">About</Link>
          {#if isAuthenticated}
            <Link href={auth.user ? '/dashboard' : '/portal'} class="px-5 py-2 rounded-full bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-sm font-semibold inline-flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
              View Portal
            </Link>
          {:else}
            <Link href="/login" class="px-5 py-2 rounded-full bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-sm font-semibold">
              Login
            </Link>
          {/if}
        </nav>
      </div>
    </div>
  </header>

  <!-- Page Content -->
  <main class="flex-grow">
    {@render children()}
  </main>

  <!-- Minimal Footer -->
  <footer class="bg-card border-t border-border mt-auto py-8">
    <div class="max-w-7xl mx-auto px-4 text-center">
      <p class="text-muted-foreground text-sm">
        &copy; {new Date().getFullYear()} Ilocos Sur Polytechnic State College - Tagudin Campus.<br/>
        <span class="text-muted-foreground/60">Powered by SecureCAT.</span>
      </p>
    </div>
  </footer>
</div>
