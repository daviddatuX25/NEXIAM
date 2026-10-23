<script lang="ts">
    import { onMount } from 'svelte';
    import * as Popover from '@/Components/ui/popover';
    import { Button } from '@/Components/ui/button';
    import { Switch } from '@/Components/ui/switch';
    import {
        SlidersHorizontal,
        Sun,
        Moon,
        Laptop,
        Volume2,
        VolumeX,
        ZoomIn,
        ZoomOut,
        RotateCcw,
        Bell,
        BellOff,
        Clock,
    } from 'lucide-svelte';
    import {
        disableStaffIdleAlerts,
        enableStaffIdleAlertsFromUserGesture,
        getStaffIdleAlertStatus,
        type StaffIdleAlertStatus,
    } from '@/lib/staff-idle-alerts.svelte';
    import { getThemePreference, setThemePreference } from '@/lib/theme.svelte';
    import { sound } from '@/lib/sound.js';
    import { success, error } from '@/lib/toast';
    import {
        getScale,
        resetScale,
        zoomIn,
        zoomOut,
        canZoomIn,
        canZoomOut,
    } from '@/lib/app-scale.svelte';
    import {
        readWorkstationTimeoutPreference,
        writeWorkstationTimeoutPreference,
        type TimeoutPreset,
    } from '@/lib/staff-session-lease';
    import {
        isPushSupported,
        isIosSafari,
        getCurrentSubscription,
        subscribeToPush,
        unsubscribeFromPush,
        getNotificationPermission,
    } from '@/lib/push-notifications';
    import {
        walkthroughCoordinator,
        type WalkthroughPlaybackMode,
    } from '@/lib/walkthrough/coordinator';

    let {
        variant = 'default',
        triggerClass = '',
        isStaff = false,
    }: {
        variant?: 'default' | 'public' | 'outline' | 'menu-item';
        triggerClass?: string;
        isStaff?: boolean;
    } = $props();
    let soundEnabled = $state(sound.isEnabled());
    let alertStatus = $state<StaffIdleAlertStatus>(getStaffIdleAlertStatus());
    let workstationTimeout = $state<TimeoutPreset>(
        readWorkstationTimeoutPreference(),
    );
    let pushSubscribed = $state(false);
    let subscriptionVersion = 0;
    let pushLoading = $state(false);
    const isIosTab = $derived(isIosSafari());
    const pushSupported = $derived(isPushSupported());
    const notificationPermission = $derived(getNotificationPermission());
    let preferencesOpen = $state(false);
    const currentTheme = $derived(getThemePreference());
    const currentScale = $derived(getScale());
    let walkthroughMode = $state<WalkthroughPlaybackMode>(walkthroughCoordinator.getPlaybackMode());
    const walkthroughModes: { value: WalkthroughPlaybackMode; label: string; description: string }[] = [
        { value: 'auto', label: 'Auto', description: 'Introduce pages you have not completed or skipped.' },
        { value: 'always', label: 'Always', description: 'Show an introduction once per eligible page visit, including completed pages.' },
        { value: 'off', label: 'Off', description: 'No automatic introductions.' },
    ];

    const themes = [
        { value: 'light', label: 'Light', Icon: Sun },
        { value: 'dark', label: 'Dark', Icon: Moon },
        { value: 'system', label: 'Auto', Icon: Laptop },
    ];

    const timeoutPresets: {
        value: TimeoutPreset;
        label: string;
    }[] = [
        { value: 15, label: '15 min — Standard (Recommended for Shared Desks)' },
        { value: 30, label: '30 min — Extended' },
        { value: 60, label: '60 min — Desk Work' },
        { value: 480, label: '8 hours — Full Shift (Dedicated Desks)' },
    ];

    const alertStatusText = $derived.by(() => {
        if (pushSubscribed || alertStatus === 'enabled') {
            return 'Enabled. Device alerts and background notifications active.';
        }
        if (isIosTab) {
            return 'On iOS Safari, tap Share and "Add to Home Screen" to enable background alerts.';
        }
        if (notificationPermission === 'denied' || alertStatus === 'permission-denied') {
            return 'Browser permission is denied. Enable notifications in your browser settings.';
        }
        if (!pushSupported && alertStatus === 'unsupported') {
            return 'Alerts are unavailable in this browser or context.';
        }
        return 'Off. Turn on to receive background alerts for exam schedules and clearance.';
    });

    function selectWorkstationTimeout(preset: TimeoutPreset): void {
        writeWorkstationTimeoutPreference(preset);
        workstationTimeout = readWorkstationTimeoutPreference();
    }

    async function enableBrowserAlertsFromClick(): Promise<void> {
        subscriptionVersion += 1;
        pushLoading = true;
        try {
            if (isStaff) {
                alertStatus = await enableStaffIdleAlertsFromUserGesture();
            }
            if (pushSupported && !isIosTab) {
                const res = await subscribeToPush();
                if (res.success) {
                    pushSubscribed = true;
                    success('Device alerts enabled.');
                } else if (res.error) {
                    error(res.error);
                }
            }
            if (!sound.isEnabled()) {
                sound.enable(false);
                soundEnabled = true;
            }
            void sound.warning();
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Could not enable alerts.';
            error(message);
        } finally {
            pushLoading = false;
        }
    }

    async function disableBrowserAlertsFromClick(): Promise<void> {
        subscriptionVersion += 1;
        pushLoading = true;
        try {
            if (isStaff) {
                disableStaffIdleAlerts();
                alertStatus = getStaffIdleAlertStatus();
            }
            await unsubscribeFromPush();
            pushSubscribed = false;
            success('Device alerts disabled.');
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Could not disable alerts.';
            error(message);
        } finally {
            pushLoading = false;
        }
    }
    $effect(() => {
        if (preferencesOpen) {
            alertStatus = getStaffIdleAlertStatus();
            workstationTimeout = readWorkstationTimeoutPreference();
            if (pushSupported) {
                const version = ++subscriptionVersion;
                let cancelled = false;
                void getCurrentSubscription().then((sub) => {
                    if (!cancelled && version === subscriptionVersion) {
                        pushSubscribed = Boolean(sub);
                    }
                });
                return () => { cancelled = true; };
            }
        }
    });

    onMount(() => {
        const unsubscribeWalkthrough = walkthroughCoordinator.subscribe(() => {
            walkthroughMode = walkthroughCoordinator.getPlaybackMode();
        });
        walkthroughMode = walkthroughCoordinator.getPlaybackMode();
        const syncTimeout = (event: Event) => {
            const custom = event as CustomEvent<TimeoutPreset>;
            workstationTimeout = custom.detail ?? readWorkstationTimeoutPreference();
        };
        window.addEventListener('nexiam:workstation_timeout_changed', syncTimeout);
        window.addEventListener('storage', syncTimeout);
        return () => {
            unsubscribeWalkthrough();
            window.removeEventListener('nexiam:workstation_timeout_changed', syncTimeout);
            window.removeEventListener('storage', syncTimeout);
        };
    });
</script>

<Popover.Root bind:open={preferencesOpen}>
    <Popover.Trigger>
        {#if variant === 'menu-item'}
            <button
                type="button"
                class="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm text-foreground hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {triggerClass}"
                aria-label="Accessibility and display preferences"
                aria-haspopup="dialog"
            >
                <SlidersHorizontal class="size-4 shrink-0 text-muted-foreground" />
                <span>Accessibility &amp; Display</span>
            </button>
        {:else}
            <Button
                variant={variant === 'public' || variant === 'outline' ? 'outline' : 'ghost'}
                size="icon"
                class="min-h-[48px] min-w-[48px] size-12 rounded-full motion-safe:active:scale-[0.97] transition-transform {variant === 'public' || variant === 'outline' ? 'border-border/80 bg-background/90 hover:bg-accent/80 hover:border-primary/40 active:bg-accent shadow-2xs backdrop-blur-md' : ''} {triggerClass}"
                aria-label="Accessibility and display preferences"
                aria-haspopup="dialog"
            >
                <SlidersHorizontal class="size-5" />
            </Button>
        {/if}
    </Popover.Trigger>
    <Popover.Content
        data-walkthrough-blocker="true"
        class="max-h-[calc(100dvh-2rem)] w-84 max-w-[calc(100vw-2rem)] space-y-4 overflow-y-auto p-4 rounded-xl border-border/80 shadow-xl backdrop-blur-md"
        align="end"
        sideOffset={8}
    >
        <header class="border-border/60 flex items-center gap-3 border-b pb-3.5">
            <div class="bg-primary/10 text-primary flex size-8 shrink-0 items-center justify-center rounded-lg shadow-2xs">
                <SlidersHorizontal class="size-4" />
            </div>
            <div class="space-y-0.5">
                <h2 class="text-foreground text-sm font-semibold tracking-tight">
                    Display &amp; Accessibility
                </h2>
                <p class="text-muted-foreground text-xs">
                    Preferences for theme, guidance, sound, scale, and session
                </p>
            </div>
        </header>

        {#if isStaff}
            <section
                class="space-y-2"
                aria-labelledby="preferences-workstation-timeout-label"
            >
                <h3
                    id="preferences-workstation-timeout-label"
                    class="text-foreground flex items-center gap-1.5 text-xs font-semibold"
                >
                    <Clock class="size-3.5 text-muted-foreground" aria-hidden="true" />
                    <span>Workstation Idle Timeout</span>
                </h3>
                <div
                    class="bg-muted/60 border-border/40 grid gap-1 rounded-lg border p-1"
                    role="group"
                    aria-label="Workstation Idle Timeout"
                >
                    {#each timeoutPresets as preset (preset.value)}
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-auto justify-start whitespace-normal text-left py-2 px-2.5 rounded-md transition-colors {workstationTimeout ===
                            preset.value
                                ? 'bg-background text-foreground shadow-xs border border-border/50 font-medium'
                                : 'text-muted-foreground hover:bg-muted/80 hover:text-foreground'}"
                            aria-pressed={workstationTimeout === preset.value}
                            onclick={() => selectWorkstationTimeout(preset.value)}
                        >
                            <Clock class="size-3.5 shrink-0 mr-2 {workstationTimeout === preset.value ? 'text-primary' : 'text-muted-foreground/60'}" aria-hidden="true" />
                            <span class="flex-1">{preset.label}</span>
                        </Button>
                    {/each}
                </div>
            </section>
        {/if}

        <section class="space-y-2" aria-labelledby="preferences-theme-label">
            <h3
                id="preferences-theme-label"
                class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
            >
                Theme
            </h3>
            <div
                class="bg-muted/60 border-border/40 grid grid-cols-3 gap-1 rounded-lg border p-1"
                role="group"
                aria-label="Theme"
            >
                {#each themes as theme (theme.value)}
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-auto flex-col gap-1 py-2 {currentTheme ===
                        theme.value
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground'}"
                        aria-pressed={currentTheme === theme.value}
                        onclick={() => setThemePreference(theme.value)}
                    >
                        <theme.Icon class="h-4 w-4" aria-hidden="true" />
                        <span>{theme.label}</span>
                    </Button>
                {/each}
            </div>
        </section>

        <section class="space-y-2" aria-labelledby="preferences-walkthrough-label">
            <h3 id="preferences-walkthrough-label" class="text-foreground text-xs font-semibold">
                Xiam walkthroughs
            </h3>
            <div
                class="bg-muted/60 border-border/40 grid grid-cols-3 gap-1 rounded-lg border p-1"
                role="group"
                aria-label="Xiam walkthroughs"
            >
                {#each walkthroughModes as mode (mode.value)}
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-auto py-2 {walkthroughMode === mode.value
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground'}"
                        aria-pressed={walkthroughMode === mode.value}
                        aria-describedby="preferences-walkthrough-description"
                        onclick={() => walkthroughCoordinator.setPlaybackMode(mode.value)}
                    >
                        {mode.label}
                    </Button>
                {/each}
            </div>
            <p id="preferences-walkthrough-description" class="text-muted-foreground text-[11px] leading-tight" aria-live="polite">
                {walkthroughModes.find((mode) => mode.value === walkthroughMode)?.description}
                Manual page guides stay available. Saved for this account and workspace in this browser.
            </p>
        </section>

        <section
            class="flex items-center justify-between gap-3"
            aria-labelledby="preferences-sound-label"
        >
            <div class="space-y-1">
                <h3
                    id="preferences-sound-label"
                    class="text-foreground flex items-center gap-1.5 text-xs font-semibold"
                >
                    {#if soundEnabled}
                        <Volume2 class="h-3.5 w-3.5" aria-hidden="true" />
                    {:else}
                        <VolumeX class="h-3.5 w-3.5" aria-hidden="true" />
                    {/if}
                    Interface Sounds
                </h3>
                <p class="text-muted-foreground text-[11px] leading-tight">
                    Tactile clicks, inactivity alerts, and new notification cues
                </p>
            </div>
            <Switch
                checked={soundEnabled}
                aria-label="Interface sounds"
                onCheckedChange={() => {
                    soundEnabled = sound.toggle();
                    if (soundEnabled) sound.press();
                }}
            />
        </section>

        <section
            class="space-y-2"
            aria-labelledby="preferences-browser-alert-label"
        >
            <div class="space-y-1">
                <h3
                    id="preferences-browser-alert-label"
                    class="text-foreground flex items-center gap-1.5 text-xs font-semibold"
                >
                    {#if pushSubscribed || alertStatus === 'enabled'}
                        <Bell class="h-3.5 w-3.5" aria-hidden="true" />
                    {:else}
                        <BellOff class="h-3.5 w-3.5" aria-hidden="true" />
                    {/if}
                    Device & browser alerts
                </h3>
                <p
                    class="text-muted-foreground text-[11px] leading-tight"
                    aria-live="polite"
                >
                    {alertStatusText}
                </p>
            </div>
            {#if pushSubscribed || alertStatus === 'enabled'}
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="flex-1"
                        disabled={pushLoading}
                        onclick={disableBrowserAlertsFromClick}
                    >
                        {pushLoading ? 'Turning off…' : 'Turn off alerts'}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-xs text-muted-foreground hover:text-foreground"
                        onclick={() => {
                            if (!sound.isEnabled()) {
                                sound.enable(false);
                                soundEnabled = true;
                            }
                            void sound.warning();
                        }}
                    >
                        Demo sound
                    </Button>
                </div>
            {:else}
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full"
                    disabled={pushLoading ||
                        (!pushSupported && alertStatus === 'unsupported') ||
                        notificationPermission === 'denied' ||
                        alertStatus === 'permission-denied' ||
                        isIosTab}
                    onclick={enableBrowserAlertsFromClick}
                >
                    {pushLoading
                        ? 'Enabling…'
                        : notificationPermission === 'default' || alertStatus === 'permission-required'
                            ? 'Enable alerts'
                            : 'Turn on alerts'}
                </Button>
            {/if}
        </section>

        <section
            class="flex items-center justify-between gap-3"
            aria-labelledby="preferences-scale-label"
        >
            <div class="space-y-1">
                <h3
                    id="preferences-scale-label"
                    class="text-foreground text-xs font-semibold"
                >
                    Interface Scale
                </h3>
                <p
                    class="text-primary font-mono text-xs font-bold"
                    aria-live="polite"
                >
                    {currentScale}%
                </p>
            </div>
            <div
                class="flex items-center gap-1"
                aria-label="Interface scale controls"
            >
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Decrease interface scale"
                    disabled={!canZoomOut()}
                    onclick={() => zoomOut()}
                >
                    <ZoomOut class="h-4 w-4" aria-hidden="true" />
                </Button>
                <Button
                    variant="outline"
                    size="icon-sm"
                    aria-label="Increase interface scale"
                    disabled={!canZoomIn()}
                    onclick={() => zoomIn()}
                >
                    <ZoomIn class="h-4 w-4" aria-hidden="true" />
                </Button>
                {#if currentScale !== 100}
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Reset interface scale"
                        onclick={() => resetScale()}
                    >
                        <RotateCcw class="h-4 w-4" aria-hidden="true" />
                    </Button>
                {/if}
            </div>
        </section>
    </Popover.Content>
</Popover.Root>
