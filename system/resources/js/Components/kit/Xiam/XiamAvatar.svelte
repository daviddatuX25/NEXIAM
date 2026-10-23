<script module lang="ts">
    import type { XiamAvatarEmotion } from '@/lib/xiam-avatar-reducer';

    type XiamAvatarManifestEntry = {
        keyframes?: string[];
        durations_ms?: number[];
    };

    type XiamAvatarManifest = {
        emotions?: Partial<Record<XiamAvatarEmotion, XiamAvatarManifestEntry>>;
    };

    const FALLBACK_FRAME = '/assets/xiam/emotions/v2/frames/idle-open.png';
    const FALLBACK_IMAGE = '/assets/xiam/emotions/v2/frames/idle-open.png';
    let cachedManifest: XiamAvatarManifest | null = null;
    let manifestPromise: Promise<XiamAvatarManifest | null> | null = null;
    const thinkingFrameUrls = new Map<string, string>();
    let thinkingArtworkPromise: Promise<void> | null = null;

    export function resetAvatarManifestCache(): void {
        cachedManifest = null;
        manifestPromise = null;
        for (const url of thinkingFrameUrls.values()) {
            URL.revokeObjectURL(url);
        }
        thinkingFrameUrls.clear();
        thinkingArtworkPromise = null;
    }

    function loadManifest(): Promise<XiamAvatarManifest | null> {
        if (cachedManifest !== null) {
            return Promise.resolve(cachedManifest);
        }

        manifestPromise ??= fetch('/assets/xiam/emotions/v2/manifest.json')
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to load Xiam avatar manifest.');
                }

                return response.json() as Promise<XiamAvatarManifest>;
            })
            .then((data) => {
                cachedManifest = data;
                return data;
            })
            .catch(() => {
                manifestPromise = null;
                return null;
            });

        return manifestPromise;
    }

    export function prepareXiamThinkingArtwork(): Promise<void> {
        if (typeof URL.createObjectURL !== 'function') {
            return Promise.resolve();
        }

        thinkingArtworkPromise ??= loadManifest()
            .then(async (manifest) => {
                const frames = manifest?.emotions?.thinking?.keyframes ?? [];
                await Promise.all(frames.map(async (frame) => {
                    if (thinkingFrameUrls.has(frame)) return;
                    const response = await fetch(frame, {
                        cache: 'force-cache',
                        signal: AbortSignal.timeout(5000),
                    });
                    if (!response.ok) throw new Error('Unable to load thinking artwork.');
                    const url = URL.createObjectURL(await response.blob());
                    try {
                        const image = new Image();
                        image.src = url;
                        await image.decode();
                        thinkingFrameUrls.set(frame, url);
                    } catch (error) {
                        URL.revokeObjectURL(url);
                        throw error;
                    }
                }));
            })
            .catch(() => {
                thinkingArtworkPromise = null;
            });

        return thinkingArtworkPromise;
    }
</script>
<script lang="ts">
    import { untrack } from 'svelte';
    import {
        completeXiamAvatar,
        INITIAL_XIAM_AVATAR_STATE,
        isXiamAvatarToolPose,
        reduceXiamAvatar,
        type XiamAvatarEmotion,
        type XiamAvatarEvent,
    } from '@/lib/xiam-avatar-reducer';

    let {
        event = null,
        heldEmotion = null,
        playbackKey = null,
        label = 'Xiam assistant',
        size = 64,
        busy = false,
    }: {
        event?: XiamAvatarEvent | null;
        heldEmotion?: XiamAvatarEmotion | null;
        playbackKey?: string | null;
        label?: string;
        size?: number;
        busy?: boolean;
    } = $props();

    let state = $state(INITIAL_XIAM_AVATAR_STATE);
    let handledEvent = $state<XiamAvatarEvent | null>(null);
    let displayedFrame = $state(FALLBACK_FRAME);
    let reducedMotion = $state(false);
    let imageFailed = $state(false);
    let effectiveEmotion = $derived(
        heldEmotion ??
            (busy &&
            !isXiamAvatarToolPose(state.emotion) &&
            !state.emotion.startsWith('talking')
                ? 'thinking'
                : state.emotion),
    );

    $effect(() => {
        void prepareXiamThinkingArtwork();
    });

    $effect(() => {
        const incoming = event;
        if (incoming === null) {
            return;
        }

        untrack(() => {
            if (incoming === handledEvent) {
                return;
            }

            handledEvent = incoming;
            const previous = state;
            const next = reduceXiamAvatar(previous, incoming);
            if (next === previous) {
                return;
            }

            state = next;

        });
    });

    $effect(() => {
        const generation = state.generation;
        if (
            heldEmotion !== null ||
            busy ||
            state.emotion === 'idle' ||
            isXiamAvatarToolPose(state.emotion) ||
            state.incidentActive === true
        ) {
            return;
        }

        const timer = window.setTimeout(() => {
            untrack(() => {
                if (!busy && heldEmotion === null) {
                    state = completeXiamAvatar(state, generation);
                }
            });
        }, 2800);

        return () => window.clearTimeout(timer);
    });

    $effect(() => {
        const mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        const updatePreference = () => {
            reducedMotion = mediaQuery.matches;
        };

        updatePreference();
        mediaQuery.addEventListener('change', updatePreference);

        return () => mediaQuery.removeEventListener('change', updatePreference);
    });

    $effect(() => {
        const emotion = effectiveEmotion;
        void state.generation;
        void playbackKey;
        const shouldReduceMotion = reducedMotion;
        let timer: number | undefined;
        let active = true;

        const clearTimer = () => {
            if (timer !== undefined) {
                window.clearTimeout(timer);
                timer = undefined;
            }
        };

        const play = (loadedManifest: XiamAvatarManifest | null) => {
            if (!active) {
                return;
            }

            const entry = loadedManifest?.emotions?.[emotion];
            const frames = (entry?.keyframes ?? []).map(
                (frame) => thinkingFrameUrls.get(frame) ?? frame,
            );
            const durations = entry?.durations_ms ?? [];
            const firstFrame = frames[0] ?? FALLBACK_FRAME;
            const heldFrame = frames[1] ?? firstFrame;

            imageFailed = false;

            if (shouldReduceMotion) {
                displayedFrame = emotion === 'idle' ? firstFrame : heldFrame;
                return;
            }

            displayedFrame = firstFrame;

            const isContinuousPose =
                heldEmotion === null &&
                (emotion.startsWith('talking') || emotion === 'thinking' || emotion.startsWith('searching'));

            if (isContinuousPose && frames.length >= 2) {
                let frameIdx = 0;
                const advanceContinuous = () => {
                    if (!active) return;
                    frameIdx = (frameIdx + 1) % frames.length;
                    displayedFrame = frames[frameIdx] ?? firstFrame;
                    const nextDuration =
                        durations[frameIdx] ?? (emotion === 'thinking' ? 700 : 300);
                    timer = window.setTimeout(advanceContinuous, nextDuration);
                };
                const firstDuration =
                    durations[0] ?? (emotion === 'thinking' ? 700 : 300);
                timer = window.setTimeout(advanceContinuous, firstDuration);
                return;
            }

            if (emotion !== 'idle') {
                timer = window.setTimeout(() => {
                    displayedFrame = heldFrame;
                    timer = undefined;
                }, 180);

                return;
            }

            let frameIndex = 0;
            const advanceIdle = () => {
                if (!active || frames.length === 0) {
                    return;
                }

                frameIndex = (frameIndex + 1) % frames.length;
                displayedFrame = frames[frameIndex] ?? FALLBACK_FRAME;
                timer = window.setTimeout(
                    advanceIdle,
                    Math.max(0, durations[frameIndex] ?? 0),
                );
            };

            timer = window.setTimeout(
                advanceIdle,
                Math.max(0, durations[0] ?? 0),
            );
        };

        const prepareAndPlay = async () => {
            const manifest = cachedManifest ?? await loadManifest();
            if (emotion === 'thinking') await prepareXiamThinkingArtwork();
            play(manifest);
        };
        void prepareAndPlay();

        return () => {
            active = false;
            clearTimer();
        };
    });

    function handleImageError(): void {
        if (!imageFailed) {
            imageFailed = true;
            displayedFrame = FALLBACK_IMAGE;
        }
    }
</script>

<img
    class="xiam-avatar"
    src={imageFailed ? FALLBACK_IMAGE : displayedFrame}
    width={size}
    height={size}
    alt={label}
    onerror={handleImageError}
/>

<style>
    .xiam-avatar {
        display: block;
        object-fit: contain;
        image-rendering: crisp-edges;
        image-rendering: -webkit-optimize-contrast;
    }
</style>
