<script>
  import { Link } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Sun, Moon } from 'lucide-svelte';
  import ToastManager from '@/Components/kit/ToastManager.svelte';

  let { children } = $props();

  function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
  }
</script>

<svelte:head>
  <title>NEXIAM - Staff Login</title>
</svelte:head>

<div class="relative flex min-h-dvh flex-col items-center justify-center overflow-hidden px-4 py-12 sm:px-6 lg:px-8">
  <!-- Atmospheric wash (legacy-like, restrained) -->
  <div
    class="pointer-events-none absolute -top-[10%] -right-[10%] h-[380px] w-[380px] rounded-full bg-gradient-to-tr from-primary/15 to-emerald-400/10 blur-3xl"
    aria-hidden="true"
  ></div>
  <div
    class="pointer-events-none absolute bottom-[5%] -left-[12%] h-[420px] w-[420px] rounded-full bg-gradient-to-bl from-primary/10 to-amber-200/10 blur-3xl"
    aria-hidden="true"
  ></div>

  <Button
    variant="ghost"
    size="icon"
    onclick={toggleTheme}
    aria-label="Toggle theme"
    class="absolute top-4 right-4 z-20 rounded-full min-h-[44px] min-w-[44px]"
  >
    <Sun class="h-5 w-5 dark:hidden" />
    <Moon class="h-5 w-5 hidden dark:block" />
  </Button>

  <Link
    href="/"
    class="relative z-10 mb-8 flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground no-underline hover:text-primary transition-colors"
  >
    <span
      class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground font-bold text-lg"
      aria-hidden="true"
    >N</span>
    <span class="text-primary">NEXIAM</span>
  </Link>

  <div class="relative z-10 w-full max-w-md">
    {@render children?.()}
  </div>

  <p class="relative z-10 mt-8 text-sm text-muted-foreground">
    <Link href="/" class="text-primary hover:underline">Back to home</Link>
  </p>
</div>

<ToastManager />
