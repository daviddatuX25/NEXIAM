<script>
  /* global route */
  import { usePage, router } from '@inertiajs/svelte';
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import ApplicantLookupSearch from '@/Components/Admission/ApplicantLookupSearch.svelte';
  import { groupApplicantDestinations } from '@/lib/applicant-actions';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
  import { Input } from '@/Components/ui/input';
  import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogDescription,
  } from '@/Components/ui/dialog';
  import { Camera, QrCode, ShieldCheck, ArrowRight, AlertCircle } from 'lucide-svelte';
  import { UniversalQrModal } from '@/Components/qr';

  let { page_state = 'ready', scan_result: initialScanResult = null, scanned_attempt: initialAttempt = null, destinations: initialDestinations = [], default_destination: initialDefault = null, requires_choice = true } = $props();
  const page = usePage();
  let scanResult = $state(initialScanResult);
  let scannedAttempt = $state(initialAttempt);
  let destinations = $state(Array.isArray(initialDestinations) ? initialDestinations : []);
  let defaultDestination = $state(initialDefault);
  let requiresChoice = $state(requires_choice);
  let modalOpen = $state(false);
  let qrScannerOpen = $state(false);
  let code = $state('');
  let isResolving = $state(false);
  let errorMessage = $state('');
  let resolveVersion = 0;
  let handledExternalCode = '';
  let previousLookupContext = '';

  const resolveUrl = typeof route === 'function' ? route('staff.scan.resolve') : '/staff/scan/resolve';
  const lookupContextKey = $derived([
    page.props.auth?.user?.id ?? '',
    page.props.auth?.staff_inactivity_generation ?? '',
    page.props.practice_workspace?.id ?? page.props.practice_workspace?.key ?? '',
    JSON.stringify(page.props.auth?.user?.scope ?? {}),
  ].join(':'));
  const resolvedAttempt = $derived(scanResult?.state === 'resolved' ? scannedAttempt : null);
  const groupedDestinations = $derived(groupApplicantDestinations(destinations));

  async function resolveCode(value = code) {
    const submitted = String(value).trim();
    if (!submitted || isResolving) return;
    const version = ++resolveVersion;
    isResolving = true;
    errorMessage = '';
    modalOpen = false;
    try {
      const { data } = await window.axios.post(resolveUrl, { code: submitted });
      if (version !== resolveVersion) return;
      scanResult = data.scan_result ?? null;
      scannedAttempt = data.scanned_attempt ?? null;
      destinations = Array.isArray(data.destinations) ? data.destinations : [];
      defaultDestination = data.default_destination ?? null;
      requiresChoice = data.requires_choice === true;
      if (scanResult?.state === 'resolved' && scannedAttempt) {
        const authorizedDefault = defaultDestination
          ? destinations.find((destination) => destination.key === defaultDestination.key && destination.url === defaultDestination.url)
          : null;
        if (!requiresChoice && authorizedDefault) {
          router.visit(authorizedDefault.url);
          return;
        }
        modalOpen = true;
      }
    } catch (error) {
      if (version === resolveVersion && error?.response?.status !== 419) {
        scanResult = { state: 'invalid', message: error?.response?.data?.scan_result?.message ?? 'Applicant code could not be resolved.' };
        scannedAttempt = null;
        destinations = [];
        defaultDestination = null;
        errorMessage = scanResult.message;
      }
    } finally {
      if (version === resolveVersion) {
        isResolving = false;
        code = '';
      }
    }
  }

  function startCamera() {
    qrScannerOpen = true;
  }

  function goTo(destination) {
    if (!destinations.some((authorized) => authorized.key === destination.key && authorized.url === destination.url)) return;
    modalOpen = false;
    if (destination.kind === 'document') window.open(destination.url, '_blank', 'noopener,noreferrer');
    else router.visit(destination.url);
  }

  $effect(() => {
    const context = lookupContextKey;
    if (previousLookupContext && context !== previousLookupContext) {
      resolveVersion++;
      isResolving = false;
      scanResult = null;
      scannedAttempt = null;
      destinations = [];
      defaultDestination = null;
      modalOpen = false;
      errorMessage = '';
      code = '';
    }
    previousLookupContext = context;
  });

  $effect(() => {
    const params = new URL(page.url, window.location.origin).searchParams;
    const externalCode = params.get('code')?.trim() ?? '';
    const signature = `${externalCode}:${lookupContextKey}`;
    if (externalCode && signature !== handledExternalCode) {
      handledExternalCode = signature;
      code = externalCode;
      void resolveCode(externalCode);
    }
  });
</script>

<svelte:head><title>Scan Applicant QR</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Scan Applicant QR' }]}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if page_state !== 'ready'}
      <E2PageState state={page_state} />
    {:else}
      <PageHeaderBar
        title="Scan Applicant QR"
        description="Scan an applicant or examinee QR code to quickly look up their profile, exam schedule, and admission status."
      />

      <Card.Root>
        <Card.Content class="space-y-4 p-4">
          <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
              <Input bind:value={code} placeholder="Applicant locator or reference code" onkeydown={(event) => { if (event.key === 'Enter') resolveCode(); }} />
            </div>
            <Button type="button" class="min-h-10" onclick={() => resolveCode()} disabled={isResolving || !code.trim()}>
              <QrCode class="size-4" />{isResolving ? 'Resolving…' : 'Resolve'}
            </Button>
            <Button type="button" variant="outline" class="min-h-10" onclick={startCamera}><Camera class="size-4" />Start camera</Button>
          </div>
          {#if errorMessage}
            <p class="flex items-start gap-2 text-sm text-destructive" role="alert"><AlertCircle class="mt-0.5 size-4 shrink-0" />{errorMessage}</p>
          {/if}
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Content class="p-4">
          <ApplicantLookupSearch onSelect={(result) => resolveCode(result.reference_code)} contextKey={lookupContextKey} />
        </Card.Content>
      </Card.Root>

      {#if scanResult && scanResult.state !== 'resolved'}
        <Card.Root>
          <Card.Content class="flex items-center gap-2 p-4" role="status">
            <Badge variant={scanResult.state === 'no_destination' ? 'warning' : 'danger'}>{scanResult.state}</Badge>
            <p class="text-sm text-muted-foreground">{scanResult.message}</p>
          </Card.Content>
        </Card.Root>
      {/if}

      <Dialog open={modalOpen} onOpenChange={(value) => (modalOpen = value)}>
        <DialogContent class="sm:max-w-lg">
          <DialogTitle>Applicant destinations</DialogTitle>
          <DialogDescription>{scannedAttempt?.name} · {scannedAttempt?.reference_code}. Choose an authorized destination.</DialogDescription>
          <div class="max-h-[60dvh] space-y-3 overflow-y-auto">
            {#each groupedDestinations as [group, links] (group)}
              <section class="space-y-1.5">
                <h3 class="text-xs font-semibold text-muted-foreground">{group}</h3>
                {#each links as destination (destination.key + destination.url)}
                  <button type="button" class="flex min-h-11 w-full items-center justify-between gap-3 rounded-md border border-border px-3 py-2 text-left text-sm font-medium hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onclick={() => goTo(destination)}>
                    <span>{destination.label}</span><ArrowRight class="size-4 shrink-0 text-muted-foreground" />
                  </button>
                {/each}
              </section>
            {/each}
            {#if destinations.length === 0}
              <p class="text-sm text-muted-foreground">No applicant destinations are available for your current workspace.</p>
            {/if}
            <PrintDocumentsSlot slotKey="applicant_qr_scan_modal" attemptIds={resolvedAttempt?.id ? [resolvedAttempt.id] : []} autoStart={true} />
          </div>
          <Button variant="outline" onclick={() => (modalOpen = false)}>Close</Button>
        </DialogContent>
      </Dialog>


      <Card.Root>
        <Card.Content class="space-y-3 p-4">
          <div class="flex items-center gap-2">
            <ShieldCheck class="size-4 text-muted-foreground" />
            <p class="font-medium">What a scan reveals</p>
          </div>
          <p class="text-sm text-muted-foreground">
            The payload contains no name, scores, action, or destination. Resolving a
            code verifies identity only — copying it grants nothing.
          </p>
        </Card.Content>
      </Card.Root>
    {/if}
  </div>

  <UniversalQrModal
    open={qrScannerOpen}
    onOpenChange={(v) => (qrScannerOpen = v)}
    mode="callback"
    title="Scan Applicant Pass QR"
    description="Align the applicant's digital QR or printed pass within the camera viewfinder."
    onScan={(scannedCode, { close }) => {
      close();
      resolveCode(scannedCode);
    }}
  />
</StaffLayout>
