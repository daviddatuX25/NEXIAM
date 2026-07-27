<script>
  import { Link } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Sun, Moon } from 'lucide-svelte';
  import ToastManager from '@/Components/ToastManager.svelte';

  let { children } = $props();

  function toggleTheme() {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
  }
</script>

<svelte:head>
  <title>Applicant Portal - SecureCAT</title>
</svelte:head>

<ToastManager />

<div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 relative">
  <Button variant="ghost" size="icon" onclick={toggleTheme} aria-label="Toggle theme" class="absolute top-4 right-4 rounded-full min-h-[44px] min-w-[44px]">
    <Sun class="h-5 w-5 dark:hidden" />
    <Moon class="h-5 w-5 hidden dark:block" />
  </Button>
  <Link href="/" class="mb-8 flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground no-underline hover:text-primary transition-colors">
    <span class="text-primary">SecureCAT</span>
    <span class="text-muted-foreground text-base font-normal">Applicant Portal</span>
  </Link>

  <div class="w-full max-w-md">
    {@render children?.()}
  </div>

  <p class="mt-8 text-sm text-muted-foreground">
    <Link href="/" class="text-primary hover:underline">Back to home</Link>
  </p>
</div>
