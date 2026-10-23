<script>
  import { onMount } from 'svelte';
  import { Link, router, usePage } from '@inertiajs/svelte';
  import ToastManager from '@/Components/kit/ToastManager.svelte';
  import { Button } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';
  import NotificationBell from '@/Components/kit/NotificationBell.svelte';
  import PreferencesMenu from '@/Components/kit/PreferencesMenu.svelte';
  import BrandTitle from '@/Components/kit/BrandTitle.svelte';
  import InstitutionalBackdrop from '@/Components/kit/InstitutionalBackdrop.svelte';
  import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
  import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
  import PublicFaqInquiryModal from '@/Components/public/PublicFaqInquiryModal.svelte';
  import ApplicantHome from '@/Pages/Applicant/ApplicantHome.svelte';
  import { notifyRealtimePulseObservation } from '@/lib/realtime-pulse.svelte';
  import QRCode from 'qrcode';
  import { downloadApplicantQrPdf, printApplicantQr } from '@/lib/applicant-qr-print.js';
  import { Compass, Download, HelpCircle, House, Printer, QrCode, Sparkles, X } from 'lucide-svelte';

  let { children, title = 'Your status', breadcrumbs = [], wide = false } = $props();
  const page = usePage();
  const user = $derived(page.props.auth?.user ?? null);
  const rawNav = $derived(page.props.applicant_navigation ?? page.props.navigation ?? []);
  const currentPath = $derived(page.url.split('?')[0]);
  const qrData = $derived(page.props.applicant_qr ?? null);
  const homeProjection = $derived(page.props.applicant_home ?? null);
  const isHome = $derived(currentPath === '/applicant/portal');
  const showHomePane = $derived(!isHome && homeProjection !== null);
  const institution = $derived(
    page.props.institution ?? {
      name: 'Ilocos Sur Polytechnic State College',
      short_name: 'ISPSC',
      campus: 'Tagudin Campus',
    }
  );
  const pageTitle = $derived(breadcrumbs.length > 0 ? breadcrumbs[breadcrumbs.length - 1].label : title);
  const secondaryKeys = ['release_schedule', 'release_day', 'results', 'program_choice', 'admission_support', 'office_inquiries', 'access_history', 'policies', 'announcements'];
  const secondaryLinks = $derived((rawNav ?? []).filter((item) => secondaryKeys.includes(item.key)));

  let logoLoadError = $state(false);
  let qrModalOpen = $state(false);
  let qrTourOpen = $state(false);
  let faqInquiryModalOpen = $state(false);
  let toolsOpen = $state(false);
  let toolsTriggerEl = $state(null);
  let toolsMenuEl = $state(null);
  let qrCanvasEl = $state(null);
  let qrPrinting = $state(false);
  let qrActionError = $state('');
  let logoutConfirmOpen = $state(false);

  let navigationPending = $state(false);
  let navigationFailed = $state(false);
  let attemptedNavigationUrl = $state(null);
  let failedNavigationUrl = $state(null);

  onMount(() => {
    const removeStart = router.on('start', (event) => {
      const visit = event.detail.visit;
      if (visit?.poll || visit?.prefetch || (Array.isArray(visit?.only) && !visit.only.includes('page_state'))) {
        return;
      }
      attemptedNavigationUrl = visit.method === 'get' ? String(visit.url) : null;
      failedNavigationUrl = null;
      navigationPending = true;
      navigationFailed = false;
    });
    const removeFinish = router.on('finish', () => {
      navigationPending = false;
    });
    const fail = () => {
      navigationPending = false;
      navigationFailed = true;
      failedNavigationUrl = attemptedNavigationUrl;
    };
    const removeException = router.on('exception', fail);
    const removeHttpException = router.on('httpException', fail);
    return () => {
      removeStart();
      removeFinish();
      removeException();
      removeHttpException();
    };
  });

  let homeScrollEl = $state(null);

  function rememberHomeScroll(event) {
    try {
      window.sessionStorage.setItem('applicant-home-scroll', String(event.currentTarget.scrollTop));
    } catch {
      // Scroll restoration remains usable when browser storage is unavailable.
    }
  }

  function rememberPortalScroll() {
    if (!isHome) return;
    try {
      window.sessionStorage.setItem('applicant-portal-scroll', String(window.scrollY));
    } catch {
      // Browser history remains the fallback when storage is unavailable.
    }
  }

  function goHome(event) {
    if (isHome) return;
    event.preventDefault();
    let scrollY = 0;
    try {
      scrollY = Number(window.sessionStorage.getItem('applicant-portal-scroll') ?? 0);
    } catch {
      scrollY = 0;
    }
    router.visit('/applicant/portal', {
      preserveScroll: false,
      onSuccess: () => window.scrollTo({ top: scrollY }),
    });
  }

  function retryNavigation() {
    if (!failedNavigationUrl) return;
    const url = failedNavigationUrl;
    failedNavigationUrl = null;
    navigationFailed = false;
    router.visit(url);
  }

  function handleWindowPointerdown(event) {
    if (toolsOpen && !toolsMenuEl?.contains(event.target) && !toolsTriggerEl?.contains(event.target)) {
      toolsOpen = false;
      window.setTimeout(() => toolsTriggerEl?.focus(), 0);
    }
  }

  function promptLogout() {
    logoutConfirmOpen = true;
  }

  function confirmLogout() {
    logoutConfirmOpen = false;
    router.post('/logout');
  }

  function closeTools() {
    toolsOpen = false;
    toolsTriggerEl?.focus();
  }

  function handleWindowKeydown(event) {
    if (event.key === 'Escape' && toolsOpen) {
      event.preventDefault();
      closeTools();
    }
  }


  function qrPrintUrl() {
    return qrData?.print_url || '/applicant/qr/print';
  }

  async function printQrPass() {
    if (qrPrinting) return;
    qrPrinting = true;
    qrActionError = '';
    try {
      await printApplicantQr(qrPrintUrl());
    } catch (error) {
      qrActionError = error instanceof Error ? error.message : 'The QR document could not be loaded. Please try again.';
    } finally {
      qrPrinting = false;
    }
  }

  function downloadQrPassPdf() {
    qrActionError = '';
    try {
      downloadApplicantQrPdf(qrPrintUrl());
    } catch (error) {
      qrActionError = error instanceof Error ? error.message : 'The PDF could not be downloaded. Please try again.';
    }
  }

  function openWayfinder() {
    closeTools();
    window.dispatchEvent(new CustomEvent('nexiam:open-wayfinding'));
  }

  function openXiamChat() {
    closeTools();
    window.dispatchEvent(new CustomEvent('nexiam:open-xiam-chat'));
  }

  function openFaqInquiryModal() {
    closeTools();
    faqInquiryModalOpen = true;
  }

  const qrWalkthroughContext = $derived.by(() => {
    const hasQ = Boolean(qrData?.qr);
    const attemptId = qrData?.attempt_id ? Number(qrData.attempt_id) : null;
    return {
      entityKey: attemptId ? `attempt-${attemptId}` : 'my-qr',
      safetyKey: `ready:${attemptId ?? 'noatt'}:${hasQ ? 'qr' : 'noqr'}`,
      freshness: 'current',
      pageState: 'ready',
      hasQr: hasQ,
      attemptId,
    };
  });

  $effect(() => {
    notifyRealtimePulseObservation(page.props);
  });

  $effect(() => {
    if (qrModalOpen && qrCanvasEl && qrData?.entry_url) {
      QRCode.toCanvas(qrCanvasEl, qrData.entry_url, { width: 220, margin: 2 }).catch((err) => {
        console.error('QR render error:', err);
      });
    }
  });

  $effect(() => {
    if (!showHomePane || !homeScrollEl) return;
    try {
      homeScrollEl.scrollTop = Number(window.sessionStorage.getItem('applicant-home-scroll') ?? 0);
    } catch {
      homeScrollEl.scrollTop = 0;
    }
  });
</script>

<svelte:head>
  <title>{pageTitle} - Applicant Portal</title>
</svelte:head>

<svelte:window onkeydown={handleWindowKeydown} onpointerdown={handleWindowPointerdown} onscroll={rememberPortalScroll} />
<div class="min-h-dvh text-foreground relative overflow-x-clip">
  <div class="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(circle_at_82%_15%,oklch(0.82_0.12_145_/_0.05),transparent_35%),radial-gradient(circle_at_12%_85%,oklch(0.9_0.08_85_/_0.06),transparent_35%)] dark:bg-[radial-gradient(circle_at_82%_15%,oklch(0.38_0.1_145_/_0.18),transparent_35%),radial-gradient(circle_at_12%_85%,oklch(0.35_0.07_85_/_0.15),transparent_35%)]"></div>
  <InstitutionalBackdrop fixed={true} opacity="opacity-[0.11] sm:opacity-[0.14] dark:opacity-[0.22]" />
  <ToastManager />

  <header class="sticky top-0 z-30 flex min-h-16 shrink-0 items-center justify-between gap-3 border-b border-border/80 bg-background/95 px-4 pt-[env(safe-area-inset-top)] supports-[backdrop-filter]:bg-background/85 supports-[backdrop-filter]:backdrop-blur-xl sm:px-6 print:hidden">
    <Link href="/applicant/portal" onclick={goHome} class="flex min-w-0 items-center gap-2" aria-label="{institution.name} applicant Home">
      {#if institution.logo_url && !logoLoadError}
        <img src={institution.logo_url} alt="{institution.name} logo" class="size-8 shrink-0 rounded-lg object-contain shadow-xs" onerror={() => (logoLoadError = true)} />
      {:else}
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-xs font-extrabold text-primary-foreground shadow-xs" aria-hidden="true">{institution.short_name?.slice(0, 1) ?? 'I'}</span>
      {/if}
      <BrandTitle {institution} />
    </Link>

    <div class="flex shrink-0 items-center gap-1 sm:gap-2">
      <div class="hidden items-center gap-1 md:flex" aria-label="Applicant tools">
        <Button type="button" variant="ghost" size="sm" class="gap-1.5" aria-label="Wayfinder" onclick={openWayfinder}><Compass class="size-4" /><span class="hidden xl:inline">Wayfinder</span></Button>
        <Button type="button" variant="ghost" size="sm" class="gap-1.5" aria-label="Xiam Chat" onclick={openXiamChat}><Sparkles class="size-4" /><span class="hidden xl:inline">Xiam Chat</span></Button>
        <Button type="button" variant="ghost" size="sm" class="gap-1.5" aria-label="FAQs & Office Inquiry" onclick={openFaqInquiryModal}><HelpCircle class="size-4" /><span class="hidden xl:inline">FAQs & Inquiry</span></Button>
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        class="hidden md:inline-flex items-center gap-1.5 border-emerald-600/30 bg-emerald-500/10 text-emerald-800 hover:bg-emerald-500/20 hover:text-emerald-900 dark:border-emerald-500/40 dark:bg-emerald-500/15 dark:text-emerald-300 dark:hover:bg-emerald-500/25 font-semibold transition-colors shadow-2xs"
        aria-label="Open my applicant QR pass"
        onclick={() => (qrModalOpen = true)}
      >
        <QrCode class="size-4 shrink-0 text-emerald-700 dark:text-emerald-400" aria-hidden="true" />
        <span>QR Pass</span>
      </Button>
      <NotificationBell />
      <details class="relative">
        <summary class="flex min-h-10 cursor-pointer list-none items-center gap-2 rounded-md px-2 text-sm hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-label="Applicant account">
          <span class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{(user?.name ?? 'A').split(/\s+/).map((name) => name[0]).join('').slice(0, 2).toUpperCase()}</span>
          <span class="hidden max-w-32 truncate sm:inline">{user?.name ?? 'Applicant'}</span>
        </summary>
        <div class="absolute right-0 top-full z-50 mt-2 flex w-56 flex-col gap-1 rounded-xl border border-border bg-popover p-2 shadow-lg">
          <span class="px-2 py-1 text-xs text-muted-foreground">Applicant account</span>
          <PreferencesMenu variant="menu-item" />
          <div class="my-1 border-t border-border/60"></div>
          {#each secondaryLinks.filter((item) => ['access_history', 'policies', 'announcements'].includes(item.key)) as item (item.key)}
            <Link href={item.href} class="rounded-md px-2 py-2 text-sm hover:bg-accent">{item.label}</Link>
          {/each}
          <div class="my-1 border-t border-border/60"></div>
          <button type="button" class="w-full rounded-md px-2 py-2 text-left text-sm text-destructive hover:bg-destructive/10" onclick={promptLogout}>Sign out</button>
        </div>
      </details>
    </div>
  </header>

  <main class="relative z-10 mx-auto w-full px-4 py-5 pb-24 sm:px-6 sm:py-7 md:pb-12 {(showHomePane || isHome) ? 'max-w-none' : (wide ? 'max-w-7xl' : 'max-w-5xl')}">
    {#if showHomePane}
      <div class="space-y-8">
        <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(16rem,30%)_minmax(0,1fr)] lg:gap-8">
          <aside class="hidden min-w-0 lg:block" aria-label="Applicant Home">
            <div bind:this={homeScrollEl} onscroll={rememberHomeScroll} class="sticky top-20 max-h-[calc(100dvh-6rem)] overflow-y-auto overscroll-contain pr-2">
              <ApplicantHome {...homeProjection} compact={true} showTour={false} selectedHref={currentPath} {secondaryLinks} hideFooter={true} />
            </div>
          </aside>
          <section class="min-w-0" aria-label="{pageTitle} details" aria-busy={navigationPending}>
            {#if navigationFailed}
              <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3" role="alert">
                <p class="text-sm">This page could not be loaded. Your Home and current information are still available.</p>
                {#if failedNavigationUrl}<Button type="button" variant="outline" size="sm" onclick={retryNavigation}>Retry</Button>{/if}
              </div>
            {/if}
            <div class="mx-auto w-full max-w-5xl">
              {@render children?.()}
            </div>
          </section>
        </div>
        <div class="hidden w-full lg:block">
          <ApplicantHome {...homeProjection} compact={false} showTour={false} selectedHref={currentPath} {secondaryLinks} onlyFooter={true} />
        </div>
      </div>
    {:else}
      {#if isHome && navigationFailed}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 lg:hidden" role="alert">
          <p class="text-sm">The selected details could not be loaded. Your Home remains available.</p>
          {#if failedNavigationUrl}<Button type="button" variant="outline" size="sm" onclick={retryNavigation}>Retry</Button>{/if}
        </div>
      {/if}
      {@render children?.()}
    {/if}
  </main>

  <nav aria-label="Applicant mobile navigation" class="fixed inset-x-0 bottom-0 z-40 border-t border-border/80 bg-card/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden print:hidden">
    <div class="mx-auto grid h-16 max-w-md grid-cols-3 items-center px-4">
      <Link href="/applicant/portal" onclick={goHome} class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" aria-current={isHome ? 'page' : undefined}>
        <House class="size-5" aria-hidden="true" /><span>Home</span>
      </Link>
      <div class="flex flex-col items-center justify-center">
        <button type="button" onclick={() => (qrModalOpen = true)} aria-label="Open my applicant QR pass" class="-mt-5 flex size-14 items-center justify-center rounded-full border-4 border-background bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          <QrCode class="size-6" aria-hidden="true" />
        </button>
        <span class="mt-0.5 text-[11px] font-medium text-muted-foreground">QR Pass</span>
      </div>
      <div class="relative flex justify-end">
        {#if toolsOpen}
          <div bind:this={toolsMenuEl} id="applicant-tools-menu" data-tools-menu aria-label="Tools" class="absolute bottom-16 right-0 z-50 mb-2 flex w-64 flex-col gap-1 rounded-2xl border border-border/80 bg-card/95 p-1.5 shadow-xl backdrop-blur-md">
            <button type="button" onclick={openWayfinder} class="flex min-h-11 w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-xs font-semibold hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><Compass class="size-4 text-primary" />Wayfinder</button>
            <button type="button" onclick={openXiamChat} class="flex min-h-11 w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-xs font-semibold hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><Sparkles class="size-4 text-primary" />Xiam Chat</button>
            <button type="button" onclick={openFaqInquiryModal} class="flex min-h-11 w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-xs font-semibold hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><HelpCircle class="size-4 text-primary" />FAQs & Office Inquiry</button>
            <button type="button" class="mt-1 min-h-10 rounded-lg border-t border-border text-xs text-muted-foreground" onclick={closeTools}>Close Tools</button>
          </div>
        {/if}
        <button bind:this={toolsTriggerEl} type="button" onclick={() => (toolsOpen = !toolsOpen)} aria-label="Open Tools" aria-expanded={toolsOpen} aria-controls="applicant-tools-menu" class="flex min-h-12 min-w-16 flex-col items-center justify-center gap-0.5 rounded-lg text-[11px] font-medium text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          <span class="inline-flex items-center gap-1"><Compass class="size-4 text-primary" aria-hidden="true" /><Sparkles class="size-4 text-primary" aria-hidden="true" /><HelpCircle class="size-4 text-primary" aria-hidden="true" /></span><span>Tools</span>
        </button>
      </div>
    </div>
  </nav>
  <!-- APPLICANT QR CODE MODAL DIALOG -->
  <Dialog.Root bind:open={qrModalOpen}>
    <Dialog.Content
      class="max-h-[calc(100dvh-env(safe-area-inset-top)-env(safe-area-inset-bottom)-1rem)] w-[calc(100vw-1rem)] max-w-md flex flex-col gap-0 overflow-hidden rounded-xl p-0 sm:max-w-md"
      showCloseButton={false}
    >
      <Dialog.Header class="flex shrink-0 flex-row items-start justify-between gap-3 border-b border-border/70 px-4 py-3 sm:px-5">
        <div class="min-w-0 space-y-1">
          <div class="flex items-center gap-2">
            <Dialog.Title class="text-xl font-bold tracking-tight text-foreground">My QR Pass</Dialog.Title>
            <TourTriggerGlyph
              tourKey="applicant-qr"
              label="Need tips on using your QR pass? Ask Kuya Xiam!"
              onclick={() => (qrTourOpen = true)}
            />
          </div>
          <Dialog.Description class="text-xs text-muted-foreground">
            {#if qrData?.attempt_id}
              Attempt #{qrData.attempt_id} {#if qrData?.reference_code}· Ref {qrData.reference_code}{/if}
            {:else}
              Permanent universal applicant locator
            {/if}
          </Dialog.Description>
        </div>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          class="min-h-[44px] min-w-[44px] shrink-0"
          aria-label="Close QR pass"
          onclick={() => (qrModalOpen = false)}
        >
          <X class="size-4" aria-hidden="true" />
        </Button>
      </Dialog.Header>

      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 sm:px-5">
        {#if qrData?.qr}
          <div class="flex flex-col items-center gap-4">
            <div class="rounded-2xl border-2 border-border/80 bg-white p-3.5 shadow-sm">
              <canvas
                bind:this={qrCanvasEl}
                class="block aspect-square size-[min(13rem,65vw)]"
                role="img"
                aria-label="Your applicant QR code"
              ></canvas>
            </div>

            <div class="space-y-1 px-2 text-center">
              <p class="text-sm font-semibold text-foreground">Show this QR when requested at an official university service point</p>
              <p class="text-xs text-muted-foreground">
                One permanent code for your admission attempt — never changes or expires.
              </p>
            </div>

            <div class="flex w-full flex-col gap-2">
              <Button
                variant="outline"
                class="min-h-11 w-full gap-2 text-sm font-medium"
                type="button"
                onclick={printQrPass}
                disabled={qrPrinting}
                data-tour="qr-print-button"
              >
                <Printer class="size-4" aria-hidden="true" />
                <span>{qrPrinting ? 'Preparing print…' : 'Print approved copy'}</span>
              </Button>
              <Button
                variant="outline"
                class="min-h-11 w-full gap-2 text-sm font-medium"
                type="button"
                onclick={downloadQrPassPdf}
                disabled={qrPrinting}
              >
                <Download class="size-4" aria-hidden="true" />
                <span>Download PDF</span>
              </Button>
            </div>
            {#if qrActionError}
              <p class="w-full text-center text-sm text-destructive" role="alert">{qrActionError}</p>
            {/if}
            <a
              href="/applicant/qr"
              class="text-xs font-medium text-primary hover:underline"
              onclick={() => (qrModalOpen = false)}
            >
              View full pass page
            </a>
          </div>
        {:else}
          <div class="my-1 space-y-2 rounded-2xl border border-border/70 bg-muted/20 p-6 text-center">
            <QrCode class="mx-auto size-12 text-muted-foreground/40" />
            <p class="text-sm font-semibold text-foreground">QR Pass Pending</p>
            <p class="text-xs text-muted-foreground">
              Your official applicant QR pass will be active once your admission attempt is accepted by the Registrar.
            </p>
          </div>
        {/if}

        <div class="mt-4 space-y-1.5 rounded-xl border border-border/60 bg-muted/30 p-3.5 text-xs text-muted-foreground">
          <p class="font-medium text-foreground">About this QR pass:</p>
          <ul class="list-disc space-y-1 pl-4">
            <li>Valid at the Release Day desk, registrar counter, and exam entry.</li>
            <li>Printing is recommended; the printed copy works identically.</li>
          </ul>
        </div>
      </div>

      <Dialog.Footer class="mx-0 mb-0 shrink-0 border-t border-border/70 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-5">
        <Button type="button" variant="ghost" class="min-h-[44px] w-full" onclick={() => (qrModalOpen = false)}>
          Close
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  <!-- KUYA XIAM NPC DIALOG FOR QR TOUR -->
  <XiamNpcDialog tourKey="applicant-qr" context={qrWalkthroughContext} bind:open={qrTourOpen} />

  <PublicFaqInquiryModal bind:open={faqInquiryModalOpen} />
  <Dialog.Root bind:open={logoutConfirmOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Sign out of applicant portal?</Dialog.Title>
        <Dialog.Description>
          Are you sure you want to sign out? You can sign back in at any time with your applicant access credentials.
        </Dialog.Description>
      </Dialog.Header>
      <Dialog.Footer class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <Button type="button" variant="outline" onclick={() => (logoutConfirmOpen = false)}>
          Cancel
        </Button>
        <Button type="button" variant="destructive" onclick={confirmLogout}>
          Sign out
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</div>
