<script>
  import { onMount, onDestroy } from 'svelte';
  import { Button, buttonVariants } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';
  import * as Select from '@/Components/ui/select';

  let { onScan, onClose, title = 'Scan QR code', enableBeep = true } = $props();

  let scanner = $state(null);
  let error = $state('');
  let loading = $state(true);
  let cameras = $state([]);
  let selectedCameraId = $state(null);
  let switching = $state(false);
  let cooldownUntil = $state(0);

  const SCAN_COOLDOWN_MS = 1500;

  function playBeep() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.frequency.value = 880;
      osc.type = 'sine';
      gain.gain.setValueAtTime(0.3, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
      osc.start(ctx.currentTime);
      osc.stop(ctx.currentTime + 0.15);
    } catch {
      // Ignore AudioContext errors
    }
  }

  function handleScanSuccess(decodedText) {
    const now = Date.now();
    if (now < cooldownUntil) return;
    cooldownUntil = now + SCAN_COOLDOWN_MS;
    if (enableBeep) playBeep();
    onScan(decodedText);
  }

  const DEFAULT_USER_ID = '__default_user__';
  const DEFAULT_ENVIRONMENT_ID = '__default_environment__';

  /**
   * Build what html5-qrcode start() expects: for default options use facingMode object (one key);
   * for enumerated device id pass the string so the library builds { deviceId: { exact: id } } itself.
   */
  function toStartConfig(idOrConstraint) {
    if (idOrConstraint == null) return { facingMode: 'environment' };
    if (idOrConstraint === DEFAULT_USER_ID) return { facingMode: 'user' };
    if (idOrConstraint === DEFAULT_ENVIRONMENT_ID) return { facingMode: 'environment' };
    return idOrConstraint;
  }

  async function startScanner(cameraIdOrConfig) {
    const { Html5Qrcode } = await import('html5-qrcode');
    const html5Qr = new Html5Qrcode('qr-reader');
    const config = { fps: 5, qrbox: { width: 250, height: 250 } };
    const startWith = toStartConfig(cameraIdOrConfig);
    await html5Qr.start(
      startWith ?? { facingMode: 'user' },
      config,
      handleScanSuccess,
      () => {} // Ignore scan errors
    );
    return html5Qr;
  }

  async function initCamerasAndStart() {
    loading = true;
    error = '';
    try {
      const { Html5Qrcode } = await import('html5-qrcode');
      const devices = await Html5Qrcode.getCameras();
      cameras = devices ?? [];
      let cameraConfig;
      if (cameras.length > 0) {
        const labelMatch = (c, ...terms) => {
          const l = (c.label || '').toLowerCase();
          return terms.some((t) => l.includes(t));
        };
        const back = cameras.find((c) => labelMatch(c, 'back', 'rear', 'environment'));
        const inner = cameras.find((c) =>
          labelMatch(c, 'inner', 'integrated', 'built-in', 'built in', 'internal')
        );
        if (back) {
          selectedCameraId = back.id;
          cameraConfig = toStartConfig(back.id);
        } else if (inner) {
          selectedCameraId = inner.id;
          cameraConfig = toStartConfig(inner.id);
        } else {
          selectedCameraId = DEFAULT_USER_ID;
          cameraConfig = toStartConfig(DEFAULT_USER_ID);
        }
      } else {
        selectedCameraId = DEFAULT_USER_ID;
        cameraConfig = toStartConfig(DEFAULT_USER_ID);
      }
      const html5Qr = await startScanner(cameraConfig);
      scanner = html5Qr;
    } catch (err) {
      error = err?.message ?? 'Could not start camera.';
    } finally {
      loading = false;
    }
  }

  async function switchCamera(newId) {
    if (!scanner || switching || !newId) return;
    const prevId = selectedCameraId;
    switching = true;
    try {
      await scanner.stop();
      scanner = null;
      selectedCameraId = newId;
      const html5Qr = await startScanner(newId);
      scanner = html5Qr;
    } catch (err) {
      error = err?.message ?? 'Could not switch camera.';
      selectedCameraId = prevId;
    } finally {
      switching = false;
    }
  }

  let refreshing = $state(false);

  async function refreshCameras() {
    if (refreshing || loading) return;
    refreshing = true;
    error = '';
    try {
      const { Html5Qrcode } = await import('html5-qrcode');
      const devices = await Html5Qrcode.getCameras();
      const newList = devices ?? [];
      cameras = newList;
      const isDefaultId = selectedCameraId === DEFAULT_USER_ID || selectedCameraId === DEFAULT_ENVIRONMENT_ID;
      const stillInList = isDefaultId || newList.some((c) => c.id === selectedCameraId);
      if (!stillInList) {
        const fallbackId = DEFAULT_USER_ID;
        selectedCameraId = fallbackId;
        if (scanner) {
          try {
            await scanner.stop();
          } catch {}
          scanner = null;
          scanner = await startScanner(toStartConfig(fallbackId));
        }
      }
    } catch (err) {
      error = err?.message ?? 'Could not refresh camera list.';
    } finally {
      refreshing = false;
    }
  }

  onMount(() => {
    initCamerasAndStart();
  });

  onDestroy(async () => {
    if (scanner) {
      try {
        await scanner.stop();
      } catch {
        // Ignore stop errors
      }
    }
  });

  function handleClose() {
    onClose();
  }

  function handleOpenChange(open) {
    if (!open) handleClose();
  }

  /** Options: only what we get from the API (defaults + enumerated devices). No synthetic front/back per device. */
  const defaultCameraOptions = [
    { id: DEFAULT_USER_ID, label: 'Default camera (front/inner)' },
    { id: DEFAULT_ENVIRONMENT_ID, label: 'Default camera (back)' },
  ];
  const cameraOptions = $derived([...defaultCameraOptions, ...cameras]);

  const triggerContent = $derived(
    selectedCameraId === DEFAULT_USER_ID
      ? 'Default camera (front/inner)'
      : selectedCameraId === DEFAULT_ENVIRONMENT_ID
        ? 'Default camera (back)'
        : cameras.find((c) => c.id === selectedCameraId)?.label ?? 'Select camera'
  );

  function onCameraValueChange(v) {
    if (v != null && v !== '') switchCamera(v);
  }
</script>

<Dialog.Root open={true} onOpenChange={handleOpenChange}>
  <Dialog.Content class="sm:max-w-md" showCloseButton={true}>
    <Dialog.Header>
      <Dialog.Title>{title}</Dialog.Title>
    </Dialog.Header>

    {#if error}
      <p class="text-sm text-destructive">{error}</p>
      <Button onclick={handleClose}>Close</Button>
    {:else}
      {#if loading}
        <p class="text-sm text-muted-foreground">Starting camera…</p>
      {:else}
        <div class="flex flex-col gap-2">
          {#if cameraOptions.length > 1}
            <label class="text-sm font-medium" for="camera-select">Camera</label>
            <Select.Root
              type="single"
              bind:value={selectedCameraId}
              onValueChange={onCameraValueChange}
              disabled={switching}
            >
              <Select.Trigger id="camera-select" class="w-full min-h-[44px]">
                {triggerContent}
              </Select.Trigger>
              <Select.Content>
                <Select.Group>
                  {#each cameraOptions as opt (opt.id)}
                    <Select.Item value={opt.id} label={opt.label || opt.id}>
                      {opt.label || 'Camera ' + opt.id}
                    </Select.Item>
                  {/each}
                </Select.Group>
              </Select.Content>
            </Select.Root>
          {:else}
            <p class="text-sm text-muted-foreground">
              {cameraOptions.length === 1 ? triggerContent : 'No cameras found.'}
            </p>
          {/if}
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="w-fit"
            disabled={refreshing || switching}
            onclick={refreshCameras}
          >
            {refreshing ? 'Refreshing…' : 'Refresh camera list'}
          </Button>
          {#if switching}
            <p class="text-xs text-muted-foreground">Switching…</p>
          {/if}
        </div>
      {/if}

      <div
        id="qr-reader"
        class="w-full min-h-[250px] overflow-hidden rounded-lg border border-border {loading ? 'invisible absolute' : ''}"
        aria-hidden={loading}
      ></div>

      <div class="flex justify-end pt-2">
        <Dialog.Close
          class={buttonVariants({ variant: 'outline', size: 'default' }) + ' min-h-[44px]'}
        >
          Close
        </Dialog.Close>
      </div>
    {/if}
  </Dialog.Content>
</Dialog.Root>
