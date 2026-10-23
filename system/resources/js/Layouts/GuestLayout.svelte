<script>
  import { Link, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Sun, Moon } from 'lucide-svelte';
  import ToastManager from '@/Components/kit/ToastManager.svelte';
  import InstitutionalBackdrop from '@/Components/kit/InstitutionalBackdrop.svelte';
  import { isDarkMode, toggleTheme } from '@/lib/theme.svelte';
  import PublicMovingLauncher from '@/Components/public/PublicMovingLauncher.svelte';
  import NexiamLogo from '@/Components/kit/NexiamLogo.svelte';
  let { children, maxWidth = 'max-w-md', showInquiryLauncher = true } = $props();
  const page = usePage();
  const institution = $derived(
    page.props.institution ?? {
      name: 'Ilocos Sur Polytechnic State College',
      short_name: 'ISPSC',
      campus: 'Tagudin Campus',
    }
  );
  let logoLoadError = $state(false);
  const user = $derived(page.props.auth?.user ?? null);
</script>

<svelte:head>
  <title>{institution.short_name ?? 'ISPSC'} · Portal</title>
</svelte:head>

<div class="relative flex min-h-dvh flex-col items-center justify-center overflow-hidden px-4 py-12 sm:px-6 lg:px-8">
  <InstitutionalBackdrop fixed={true} opacity="opacity-[0.12] sm:opacity-[0.15] dark:opacity-[0.26]" />
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
    aria-label={isDarkMode() ? 'Switch to light mode' : 'Switch to dark mode'}
    class="absolute top-4 right-4 z-20 rounded-full min-h-[44px] min-w-[44px]"
  >
    <Sun class="h-5 w-5 dark:hidden" />
    <Moon class="h-5 w-5 hidden dark:block" />
  </Button>

  <Link
    href="/"
    class="group relative z-10 mb-8 flex items-center gap-3.5 no-underline transition-transform hover:scale-[1.01]"
    aria-label="{institution.name} home"
  >
    {#if institution.logo_url && !logoLoadError}
      <img
        src={institution.logo_url}
        alt="{institution.name} logo"
        class="size-11 shrink-0 rounded-2xl object-contain shadow-md shadow-primary/25 transition-transform group-hover:-rotate-3"
        onerror={() => (logoLoadError = true)}
      />
    {:else}
      <span
        class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground font-extrabold text-xl shadow-md shadow-primary/25 transition-transform group-hover:-rotate-3"
        aria-hidden="true"
      >{institution.short_name?.slice(0, 1) ?? 'I'}</span>
    {/if}
    <span class="text-left">
      <span class="block text-lg font-bold tracking-tight text-foreground sm:text-xl leading-tight">{institution.name}</span>
      <span class="block text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-muted-foreground">{institution.campus}</span>
    </span>
  </Link>

  <div class="relative z-10 w-full {maxWidth}">
    {@render children?.()}
  </div>

  <div class="relative z-10 mt-8 flex flex-col items-center gap-2 text-center text-xs text-muted-foreground">
    <Link href="/" class="text-xs font-medium text-foreground hover:text-primary transition-colors underline underline-offset-4">
      &larr; Back to {institution.short_name ?? 'institutional'} home
    </Link>
    <p class="mt-1 flex items-center gap-1.5 text-muted-foreground/75 text-[11px]">
      <span>Admissions & testing powered by</span>
      <span class="inline-flex items-center gap-1.5 font-bold text-foreground/85">
        <NexiamLogo variant="mark" size="xs" class="size-3.5 inline-block" />
        NEXIAM
      </span>
    </p>
  </div>
</div>

{#if !user && showInquiryLauncher}
  <PublicMovingLauncher />
{/if}

<ToastManager />
