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
  <title>SecureCAT - Staff Login</title>
</svelte:head>

<div class="min-h-screen flex flex-col items-center justify-center px-4 py-12 relative">
  <Button variant="ghost" size="icon" onclick={toggleTheme} aria-label="Toggle theme" class="absolute top-4 right-4 rounded-full min-h-[44px] min-w-[44px]">
    <Sun class="h-5 w-5 dark:hidden" />
    <Moon class="h-5 w-5 hidden dark:block" />
  </Button>
  <!-- Logo / Brand -->
  <Link href="/" class="mb-8 flex items-center gap-2 text-2xl font-bold tracking-tight text-foreground no-underline hover:text-primary transition-colors">
    <img src="/img/securecat-logo.png" alt="SecureCAT" class="w-10 h-10 rounded-lg" />
    <span class="text-primary">SecureCAT</span>
  </Link>

  <!-- Centered card -->
  <div class="w-full max-w-md">
    {@render children?.()}
  </div>

  <!-- Footer link -->
  <p class="mt-8 text-sm text-muted-foreground">
    <Link href="/" class="text-primary hover:underline">Back to home</Link>
  </p>
</div>

<ToastManager />
