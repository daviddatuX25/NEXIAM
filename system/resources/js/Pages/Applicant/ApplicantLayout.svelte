<script>
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';

  let { children, title = 'Applicant portal' } = $props();

  const page = usePage();
  const user = $derived(page.props.auth?.user ?? null);

  function logout() {
    router.post('/logout');
  }
</script>

<svelte:head>
  <title>{title} - NEXIAM</title>
</svelte:head>

<div class="min-h-dvh bg-background text-foreground">
  <header class="border-b border-border/60 bg-card/40">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-6 py-4">
      <div class="min-w-0 space-y-0.5">
        <Link href="/applicant/portal" class="text-lg font-semibold tracking-tight">NEXIAM</Link>
        <p class="truncate text-sm text-muted-foreground">{user?.name ?? 'Applicant'}</p>
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <Button type="button" variant="outline" class="min-h-11" onclick={logout}>Sign out</Button>
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-3xl px-6 py-8">
    {@render children?.()}
  </main>
</div>
