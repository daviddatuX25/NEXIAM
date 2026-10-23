<script>
    import { tick, onDestroy, untrack } from 'svelte';
    import { Button } from '@/Components/ui/button';
    import { Camera, RefreshCw, AlertCircle, CheckCircle2 } from 'lucide-svelte';
    import { createOmrQrDecoder } from '@/lib/omrQrDecoder';

    /**
     * @typedef {Object} Props
     * @property {boolean} [active] - Master toggle for camera stream acquisition.
     * @property {boolean} [paused] - When true, camera keeps running but scan detection is suspended.
     * @property {'environment' | 'user'} [facingMode] - Default camera facing mode.
     * @property {boolean} [showTargetBox] - Render alignment viewfinder reticle overlay.
     * @property {(code: string) => void} [onScan] - Invoked immediately upon reliable QR detection.
     * @property {(error: string) => void} [onError] - Callback for fatal acquisition errors.
     * @property {string} [class] - Custom CSS class for the container.
     */

    let {
        active = true,
        paused = false,
        facingMode = 'environment',
        showTargetBox = true,
        onScan = () => {},
        onError = () => {},
        class: className = '',
    } = $props();

    // DOM References - ALWAYS mounted in the DOM
    let videoEl = $state(null);

    // Reactive State
    let cameraStatus = $state('idle'); // 'idle' | 'initializing' | 'running' | 'error' | 'unsupported'
    let errorMessage = $state('');
    let scannedFeedback = $state(false);

    // Lifecycle Invariants & Race-Condition Guards
    let sessionToken = 0;
    const trackedMediaTracks = new Set();
    let scanLoopRafId = null;
    let feedbackTimer = null;
    let decoderInstance = null;
    let lastScannedCode = '';
    let lastScannedTime = 0;

    /**
     * Stop a specific stream's tracks safely without touching other streams.
     * @param {MediaStream|null} stream
     */
    function stopStreamTracks(stream) {
        if (!stream) return;
        try {
            stream.getTracks().forEach((track) => {
                try {
                    track.stop();
                    track.enabled = false;
                } catch (_) {}
            });
        } catch (_) {}
    }

    /**
     * Terminate all camera tracks, cancel animation frames, and cleanly detach DOM elements.
     * Guaranteed synchronous teardown.
     */
    function terminateCamera() {
        // Invalidate in-flight promises immediately
        sessionToken++;

        if (scanLoopRafId !== null) {
            cancelAnimationFrame(scanLoopRafId);
            scanLoopRafId = null;
        }

        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
            feedbackTimer = null;
        }

        // Stop and release every registered media stream track
        for (const track of trackedMediaTracks) {
            try {
                track.stop();
                track.enabled = false;
            } catch (_) {}
        }
        trackedMediaTracks.clear();

        // Pause video element and detach srcObject
        if (videoEl) {
            try {
                videoEl.pause();
            } catch (_) {}
            videoEl.srcObject = null;
        }

        decoderInstance?.dispose?.();
        decoderInstance = null;
        lastScannedCode = '';
        lastScannedTime = 0;

        if (cameraStatus !== 'error' && cameraStatus !== 'unsupported') {
            cameraStatus = 'idle';
        }
    }

    /**
     * Safe asynchronous camera acquisition with generation tokens.
     */
    async function acquireCamera() {
        if (!active) return;
        if (typeof document !== 'undefined' && document.hidden) return;

        if (typeof window !== 'undefined' && !window.isSecureContext) {
            cameraStatus = 'error';
            errorMessage = 'Camera access requires a secure HTTPS connection (or localhost). Open this page securely, or use manual code entry.';
            onError(errorMessage);
            return;
        }

        if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
            cameraStatus = 'unsupported';
            errorMessage = 'Camera capture is unavailable in this browser. Use manual code entry or open this page in a supported browser.';
            onError(errorMessage);
            return;
        }

        terminateCamera();
        const currentToken = ++sessionToken;
        cameraStatus = 'initializing';
        try {
            let stream = null;
            try {
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: facingMode },
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                    },
                    audio: false,
                });
            } catch (strictErr) {
                if (currentToken !== sessionToken || !active) {
                    stopStreamTracks(stream);
                    return;
                }
                if (strictErr?.name !== 'OverconstrainedError' && strictErr?.name !== 'ConstraintNotSatisfiedError') {
                    throw strictErr;
                }
                stream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: false,
                });
            }

            // CRITICAL INVARIANT: If deactivated, closed, or superseded while getUserMedia was awaiting
            if (currentToken !== sessionToken || !active) {
                stopStreamTracks(stream);
                return;
            }

            // Register stream tracks for verified tracking
            stream.getTracks().forEach((track) => {
                trackedMediaTracks.add(track);
                track.onended = () => {
                    trackedMediaTracks.delete(track);
                };
            });

            await tick();
            // Video element is always mounted in the DOM
            if (currentToken !== sessionToken || !active || !videoEl) {
                stopStreamTracks(stream);
                return;
            }

            videoEl.srcObject = stream;
            await videoEl.play().catch(() => {});

            if (currentToken !== sessionToken || !active) {
                stopStreamTracks(stream);
                if (videoEl) {
                    try { videoEl.pause(); } catch (_) {}
                    videoEl.srcObject = null;
                }
                return;
            }

            cameraStatus = 'running';
            startBarcodeScanning(currentToken);
        } catch (err) {
            if (currentToken !== sessionToken) return;

            terminateCamera();
            cameraStatus = 'error';

            if (err?.name === 'NotAllowedError' || err?.name === 'PermissionDeniedError') {
                errorMessage = 'Camera access was denied. Please allow camera access in browser permissions.';
            } else if (err?.name === 'NotFoundError' || err?.name === 'DevicesNotFoundError') {
                errorMessage = 'No camera device was detected on your hardware.';
            } else if (err?.name === 'NotReadableError' || err?.name === 'TrackStartError') {
                errorMessage = 'The camera is busy or locked by another application. Close other camera apps, then retry.';
            } else {
                errorMessage = err?.message || 'Unable to start camera video stream.';
            }

            onError(errorMessage);
        }
    }

    /**
     * Start continuous, non-blocking barcode detection.
     * @param {number} expectedToken
     */
    function startBarcodeScanning(expectedToken) {
        if (typeof window === 'undefined') return;

        decoderInstance = createOmrQrDecoder();
        if (!decoderInstance.supported) {
            cameraStatus = 'unsupported';
            errorMessage = 'QR decoding is unavailable in this browser. Use manual code entry or open this page in a supported browser.';
            onError(errorMessage);
            terminateCamera();
            return;
        }

        const runScanLoop = async () => {
            if (expectedToken !== sessionToken || !active || cameraStatus !== 'running') {
                return;
            }

            if (!paused && videoEl && videoEl.readyState >= 2) {
                try {
                    const observations = await decoderInstance.detect(videoEl);
                    if (expectedToken !== sessionToken || !active) return;

                    const rawValue = observations.find((observation) => observation.rawValue?.trim())?.rawValue?.trim();
                    if (rawValue) {
                        const now = Date.now();
                        if (rawValue !== lastScannedCode || now - lastScannedTime > 1500) {
                            lastScannedCode = rawValue;
                            lastScannedTime = now;
                            handleSuccessfulDetection(rawValue);
                        }
                    }
                } catch (err) {
                    if (expectedToken !== sessionToken || !active) return;
                    cameraStatus = 'error';
                    errorMessage = err instanceof Error && err.message
                        ? `QR decoding failed: ${err.message} Use manual code entry or retry the camera.`
                        : 'QR decoding failed. Use manual code entry or retry the camera.';
                    onError(errorMessage);
                    terminateCamera();
                    return;
                }
            }

            if (expectedToken === sessionToken && active) {
                scanLoopRafId = requestAnimationFrame(runScanLoop);
            }
        };

        scanLoopRafId = requestAnimationFrame(runScanLoop);
    }

    /**
     * Process reliable barcode detection with feedback and caller notification.
     * @param {string} code
     */
    function handleSuccessfulDetection(code) {
        scannedFeedback = true;

        if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
            try {
                navigator.vibrate([40, 30, 40]);
            } catch (_) {}
        }

        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
        }

        feedbackTimer = setTimeout(() => {
            scannedFeedback = false;
        }, 1200);

        onScan(code);
    }

    // Reactively start or stop camera with untracked execution
    $effect(() => {
        const isCurrentlyActive = active;
        const currentFacing = facingMode;

        untrack(() => {
            if (isCurrentlyActive) {
                acquireCamera();
            } else {
                terminateCamera();
            }
        });

        return () => {
            untrack(() => {
                terminateCamera();
            });
        };
    });

    // Handle tab visibility change to avoid background camera locks
    function handleVisibilityChange() {
        if (typeof document === 'undefined') return;
        if (document.hidden) {
            terminateCamera();
        } else if (active) {
            acquireCamera();
        }
    }

    onDestroy(() => {
        terminateCamera();
    });
</script>

<svelte:document onvisibilitychange={handleVisibilityChange} />

<div
    class="relative overflow-hidden rounded-xl border border-border/80 bg-black aspect-video max-h-64 flex items-center justify-center select-none {className}"
>
    <!-- ALWAYS MOUNTED: Video element remains in DOM to ensure immediate binding -->
    <video
        bind:this={videoEl}
        autoplay
        playsinline
        muted
        class="size-full object-cover {cameraStatus === 'running' ? 'opacity-100' : 'opacity-0'}"
    ></video>

    <!-- Error / Unsupported State Overlay -->
    {#if cameraStatus === 'error' || cameraStatus === 'unsupported'}
        <div class="absolute inset-0 bg-background/95 p-4 text-center text-xs text-muted-foreground space-y-2 flex flex-col items-center justify-center z-20">
            <AlertCircle class="size-6 text-amber-500 mx-auto" />
            <p class="font-medium text-foreground max-w-xs">{errorMessage}</p>
            {#if cameraStatus === 'error'}
                <Button size="sm" variant="outline" class="h-7 text-xs gap-1" onclick={acquireCamera}>
                    <RefreshCw class="size-3" /> Retry Camera
                </Button>
            {/if}
        </div>
    <!-- Initializing Spinner Overlay -->
    {:else if cameraStatus === 'initializing'}
        <div class="absolute inset-0 bg-black/90 flex flex-col items-center justify-center gap-2 p-4 text-xs text-muted-foreground z-20">
            <RefreshCw class="size-5 animate-spin text-primary" />
            <span class="text-white/80">Connecting to camera hardware…</span>
        </div>
    {/if}

    <!-- Viewfinder Reticle Overlay (Active when running or initializing) -->
    {#if showTargetBox && (cameraStatus === 'running' || cameraStatus === 'initializing')}
        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center z-10">
            <div
                class="relative size-36 sm:size-44 rounded-xl border-2 transition-colors duration-300 {scannedFeedback
                    ? 'border-emerald-500 bg-emerald-500/20 shadow-lg shadow-emerald-500/20'
                    : 'border-primary/90 border-dashed bg-primary/5 shadow-2xs'}"
            >
                <!-- Corner Reticles -->
                <div class="absolute -top-1 -left-1 size-3.5 border-t-2 border-l-2 {scannedFeedback ? 'border-emerald-500' : 'border-primary'}"></div>
                <div class="absolute -top-1 -right-1 size-3.5 border-t-2 border-r-2 {scannedFeedback ? 'border-emerald-500' : 'border-primary'}"></div>
                <div class="absolute -bottom-1 -left-1 size-3.5 border-b-2 border-l-2 {scannedFeedback ? 'border-emerald-500' : 'border-primary'}"></div>
                <div class="absolute -bottom-1 -right-1 size-3.5 border-b-2 border-r-2 {scannedFeedback ? 'border-emerald-500' : 'border-primary'}"></div>

                {#if scannedFeedback}
                    <div class="absolute inset-0 flex items-center justify-center text-emerald-400">
                        <CheckCircle2 class="size-10 animate-in zoom-in-75 duration-200" />
                    </div>
                {/if}
            </div>
            <p class="mt-2 text-[11px] font-medium text-white/80 drop-shadow-md">
                {scannedFeedback ? 'QR Code Detected!' : 'Align QR code within reticle'}
            </p>
        </div>
    {/if}
</div>
