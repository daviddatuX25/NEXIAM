<script>
  /* global route */
  /**
   * Applicant QR scan (staff).
   * Resolves the universal applicant locator. Scanning never performs an
   * action and never grants authority — destinations are shown on the
   * Applicant action hub only after the server authorizes them.
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Input } from '@/Components/ui/input';
  import { Link, useForm } from '@inertiajs/svelte';
  import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
  } from '@/Components/ui/dialog';
  import { Camera, QrCode, ShieldCheck, ArrowRight } from 'lucide-svelte';

  let { page_state = 'ready', scan_result = null, scanned_attempt = null, destinations = [] } = $props();

  let modalOpen = $state(false);

  const resolveUrl =
    typeof route === 'function'
      ? route('staff.scan.resolve')
      : '/staff/scan/resolve';

  const form = useForm({
    code: '',
  });

  function resolveCode() {
    form.post(resolveUrl, { onSuccess: () => form.reset('code') });
  }

  function startCamera() {
    // Decorative only: real capture is a device-browser capability, not wired here.
  }

  const resultVariant = {
    invalid: 'danger',
    no_destination: 'warning',
    resolved: 'muted',
  };

  $effect(() => {
    if (scan_result?.state === 'resolved') {
      modalOpen = true;
    }
  });
</script>

<svelte:head><title>Scan applicant QR</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Scan applicant QR' }]}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    {#if page_state !== 'ready'}
      <E2PageState state={page_state} />
    {:else}
      <PageHeaderBar
        title="Scan applicant QR"
        description="Resolve the universal applicant locator. Scanning never performs an action and never grants authority."
      />

      <Card.Root>
        <Card.Content class="space-y-4 p-4">
          <div class="flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
              <Input
                bind:value={form.code}
                placeholder="Applicant locator code"
                onkeydown={(event) => {
                  if (event.key === 'Enter') resolveCode();
                }}
              />
            </div>
            <Button
              type="button"
              class="min-h-10"
              onclick={resolveCode}
              disabled={form.processing || !form.code.trim()}
            >
              <QrCode class="size-4" />
              Resolve
            </Button>
            <Button
              type="button"
              variant="outline"
              class="min-h-10"
              onclick={startCamera}
            >
              <Camera class="size-4" />
              Start camera
            </Button>
          </div>

          <p class="text-xs text-muted-foreground">
            Camera capture is available on device browsers.
          </p>

          {#if form.errors.code}
            <p class="text-sm text-destructive">{form.errors.code}</p>
          {/if}
        </Card.Content>
      </Card.Root>

      {#if scan_result}
        <Card.Root>
          <Card.Content class="space-y-2 p-4">
            <div class="flex items-center gap-2">
              <Badge variant={resultVariant[scan_result.state] ?? 'muted'}>
                {scan_result.state}
              </Badge>
              {#if scan_result.message}
                <p class="text-sm text-muted-foreground">{scan_result.message}</p>
              {/if}
            </div>
          </Card.Content>
        </Card.Root>
      {/if}

      <!-- Action modal after a successful scan -->
      <Dialog open={modalOpen} onOpenChange={(v) => (modalOpen = v)}>
        <DialogContent class="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>What would you like to do?</DialogTitle>
            <DialogDescription>
              {scanned_attempt?.reference_code} · resolved. Choose an action — nothing runs automatically.
            </DialogDescription>
          </DialogHeader>
          <div class="space-y-2">
            {#each destinations as d (d.key)}
              <Link
                href={d.url}
                class="flex items-center justify-between gap-3 rounded-md border p-3 text-sm font-medium hover:bg-muted/60"
              >
                <span>{d.label}</span>
                <ArrowRight class="size-4 text-muted-foreground" />
              </Link>
            {/each}
          </div>
          <DialogFooter>
            <Button variant="outline" onclick={() => (modalOpen = false)}>Cancel</Button>
          </DialogFooter>
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
</StaffLayout>
