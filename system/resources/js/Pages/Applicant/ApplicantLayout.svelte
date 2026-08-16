<script>
  import { Link, router, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import NotificationBell from '@/Components/kit/NotificationBell.svelte';
  import NexiDock from '@/Components/kit/Nexi/NexiDock.svelte';

  let { children, title = 'Your status', breadcrumbs = [], wide = false } = $props();

  const page = usePage();
  const user = $derived(page.props.auth?.user ?? null);
  const nav = $derived(page.props.applicant_navigation ?? []);
  const currentPath = $derived(page.url.split('?')[0]);

  const pageTitle = $derived(breadcrumbs.length > 0 ? breadcrumbs[breadcrumbs.length - 1].label : title);

  function logout() {
    router.post('/logout');
  }
</script>

<svelte:head>
  <title>{pageTitle} - NEXIAM</title>
</svelte:head>

<div class="min-h-dvh bg-background text-foreground">
  <header class="border-b border-border/60 bg-card/40">
    <div class={`mx-auto flex items-center justify-between gap-4 px-6 py-4 ${wide ? 'max-w-5xl' : 'max-w-3xl'}`}>
      <div class="min-w-0 space-y-0.5">
        <Link href="/applicant/portal" class="text-lg font-semibold tracking-tight">NEXIAM</Link>
        <p class="truncate text-sm text-muted-foreground">{user?.name ?? 'Applicant'}</p>
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <NotificationBell />
        <Button type="button" variant="outline" class="min-h-11" onclick={logout}>Sign out</Button>
      </div>
    </div>

    {#if nav.length > 0}
      <nav class="border-t border-border/40 bg-background/50" aria-label="Applicant navigation">
        <div class={`mx-auto flex items-center gap-1 overflow-x-auto px-4 py-2 ${wide ? 'max-w-5xl' : 'max-w-3xl'}`}>
          {#each nav as item (item.key)}
            <Link
              href={item.href}
              class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground {currentPath === item.href ? 'bg-primary/10 text-primary' : ''}"
              aria-current={currentPath === item.href ? 'page' : undefined}
            >
              {item.label}
            </Link>
          {/each}
        </div>
      </nav>
    {/if}
  </header>

  {#if breadcrumbs.length > 0}
    <div class={`mx-auto px-6 pt-6 ${wide ? 'max-w-5xl' : 'max-w-3xl'}`}>
      <nav class="flex items-center gap-2 text-sm text-muted-foreground" aria-label="Breadcrumb">
        <Link href="/applicant/portal" class="hover:text-foreground">Home</Link>
        {#each breadcrumbs as crumb, i (i)}
          <span aria-hidden="true">/</span>
          {#if crumb.href && i < breadcrumbs.length - 1}
            <Link href={crumb.href} class="hover:text-foreground">{crumb.label}</Link>
          {:else}
            <span class="font-medium text-foreground" aria-current="page">{crumb.label}</span>
          {/if}
        {/each}
      </nav>
    </div>
  {/if}

  <main class={`mx-auto px-6 py-8 ${wide ? 'max-w-5xl' : 'max-w-3xl'}`}>
    {@render children?.()}
  </main>
</div>

<NexiDock />
