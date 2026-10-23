<script>
    import { onMount } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { ArrowDown, ArrowUp, ExternalLink, Eye, EyeOff, ImagePlus, Monitor, Plus, RefreshCw, Smartphone, Trash2 } from 'lucide-svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import MediaFrame from '@/Components/public/MediaFrame.svelte';
    import StorySection from '@/Components/public/StorySection.svelte';
    import { csrfHeaders } from '@/lib/utils.js';
    import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
    let { hero = {}, stories = [], stories_section = {} } = $props();
    const breadcrumbs = [
        { label: 'Admin Office', href: '/admin' },
        { label: 'Homepage Studio' },
    ];
    let tab = $state('hero');
    let viewport = $state('editor');
    let previewMode = $state('draft');
    let previewDevice = $state('desktop');
    let iframeKey = $state(1);
    let iframeLoading = $state(true);
    let stageWidth = $state(520);
    let isCompact = $state(false);
    let busy = $state(false);
    let notice = $state('');
    let heroForm = $state({});
    let storyForm = $state(null);
    let selectedStory = $state(null);
    let imageUploading = $state(false);
    let storyImageUploading = $state(false);

    $effect(() => {
        heroForm = {
            eyebrow: hero.eyebrow ?? '', title: hero.title ?? '', lede: hero.lede ?? '',
            badge_text: hero.badge_text ?? '', image_id: hero.image_id ?? hero.image?.id ?? null,
            image_alt: hero.image?.alt ?? '', image: hero.image ?? null, expected_version: hero.version ?? 1,
        };
    });

    onMount(() => {
        const query = window.matchMedia('(max-width: 390px)');
        isCompact = query.matches;
        const update = (event) => { isCompact = event.matches; };
        query.addEventListener('change', update);
        return () => query.removeEventListener('change', update);
    });

    function observeStageWidth(node) {
        const measure = (width) => {
            const rounded = Math.round(width);
            if (rounded > 0 && rounded !== stageWidth) {
                stageWidth = rounded;
            }
        };
        measure(node.clientWidth);
        const observer = new ResizeObserver((entries) => {
            for (const entry of entries) {
                measure(entry.contentRect.width);
            }
        });
        observer.observe(node);
        return {
            destroy() {
                observer.disconnect();
            },
        };
    }

    function refreshLivePreview() {
        iframeLoading = true;
        iframeKey += 1;
    }

    const desktopScale = $derived(Math.max(0.2, stageWidth / 1440));
    const desktopStageHeight = $derived(Math.round(900 * desktopScale));
    const mobileScale = $derived(Math.min(0.68, stageWidth / 410, 530 / 824));
    const mobileStageWidth = $derived(Math.round(410 * mobileScale));
    const mobileStageHeight = $derived(Math.round(824 * mobileScale));

    function startStory(story = null) {
        selectedStory = story;
        storyForm = story ? {
            id: story.id, eyebrow: story.eyebrow ?? '', title: story.title ?? '', body: story.body ?? '',
            image_id: story.image_id ?? story.image?.id ?? null, image: story.image ?? null,
            image_alt: story.image?.alt ?? '', cta_label: story.cta_label ?? '', cta_href: story.cta_href ?? '',
            align: story.align ?? 'right',
        } : { eyebrow: '', title: '', body: '', image_id: null, image: null, image_alt: '', cta_label: '', cta_href: '', align: 'right' };
    }

    function submitHero(event) {
        event.preventDefault();
        busy = true;
        router.put('/communications/homepage/hero', {
            eyebrow: heroForm.eyebrow, title: heroForm.title, lede: heroForm.lede,
            badge_text: heroForm.badge_text, image_id: heroForm.image_id, image_alt: heroForm.image_alt,
            expected_version: heroForm.expected_version,
        }, {
            preserveScroll: true,
            onFinish: () => { busy = false; },
            onSuccess: () => {
                notice = 'Hero banner saved.';
                toastSuccess('Hero banner saved.');
                refreshLivePreview();
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] ?? 'Could not save hero banner.';
                toastError(msg);
            },
        });
    }

    async function stageImage(event, target) {
        const input = event.currentTarget;
        const file = input.files?.[0];
        if (!file) return;
        const isHero = target === 'hero';

        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            const msg = 'Please choose a valid JPEG, PNG, or WebP image.';
            toastError(msg);
            notice = msg;
            input.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            const msg = 'The image exceeds the permitted file size of 5 MB.';
            toastError(msg);
            notice = msg;
            input.value = '';
            return;
        }

        if (isHero) imageUploading = true; else storyImageUploading = true;
        notice = '';
        const form = new FormData();
        form.append('image', file);
        try {
            const response = await fetch('/communications/homepage/images', {
                method: 'POST',
                body: form,
                credentials: 'same-origin',
                headers: {
                    ...csrfHeaders(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errorMsg = payload.errors?.image?.[0]
                    ?? payload.message
                    ?? (response.status === 413 ? 'Image is too large (maximum 5 MB).' : (response.status === 500 ? 'Server error occurred during image processing. Please try again.' : 'Image upload failed.'));
                toastError(errorMsg);
                notice = errorMsg;
                return;
            }
            if (isHero) {
                heroForm.image_id = payload.media.id;
                heroForm.image = payload.media;
                toastSuccess('Hero image uploaded. Save hero to publish.');
            } else {
                storyForm.image_id = payload.media.id;
                storyForm.image = payload.media;
                toastSuccess('Story image uploaded. Save story to publish.');
            }
            notice = 'Image staged. Save to publish changes.';
        } catch (error) {
            const netErr = error instanceof Error ? error.message : 'The upload could not be completed. Please try again.';
            toastError(netErr);
            notice = netErr;
        } finally {
            if (isHero) imageUploading = false; else storyImageUploading = false;
            input.value = '';
        }
    }

    function saveStory(event) {
        event.preventDefault();
        const route = selectedStory ? `/communications/homepage/stories/${selectedStory.id}` : '/communications/homepage/stories';
        const method = selectedStory ? 'put' : 'post';
        router[method](route, {
            eyebrow: storyForm.eyebrow, title: storyForm.title, body: storyForm.body,
            image_id: storyForm.image_id, image_alt: storyForm.image_alt,
            cta_label: storyForm.cta_label, cta_href: storyForm.cta_href, align: storyForm.align,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                storyForm = null;
                selectedStory = null;
                notice = 'Campus story saved.';
                toastSuccess('Campus story saved.');
                refreshLivePreview();
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] ?? 'Could not save campus story.';
                toastError(msg);
            },
        });
    }

    function toggleStory(story) {
        router.patch(`/communications/homepage/stories/${story.id}/toggle-active`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                toastSuccess(`Story "${story.title}" visibility updated.`);
                refreshLivePreview();
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] ?? 'Could not update story visibility.';
                toastError(msg);
            },
        });
    }

    function moveStory(index, delta) {
        const ordered = [...stories];
        const target = index + delta;
        if (target < 0 || target >= ordered.length) return;
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        router.put('/communications/homepage/stories/order', { story_ids: ordered.map((item) => item.id) }, {
            preserveScroll: true,
            onSuccess: () => {
                toastSuccess('Campus stories reordered.');
                refreshLivePreview();
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] ?? 'Could not reorder stories.';
                toastError(msg);
            },
        });
    }

    const selectedPreview = $derived(storyForm ?? stories[0] ?? null);
</script>

<svelte:head><title>Homepage Studio</title></svelte:head>

<StaffLayout {breadcrumbs}>
<div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
        title="Homepage Studio"
        description="Edit the public hero banner and campus stories. Changes appear on the institutional homepage after saving."
    />
    {#if isCompact}
        <div class="bg-muted flex rounded-lg p-1" aria-label="Studio view">
            <button class:active={viewport === 'editor'} class="flex-1 rounded-md px-3 py-2 text-sm font-medium" onclick={() => (viewport = 'editor')}>Editor</button>
            <button class:active={viewport === 'preview'} class="flex-1 rounded-md px-3 py-2 text-sm font-medium" onclick={() => (viewport = 'preview')}>Preview</button>
        </div>
    {/if}
    <div class="border-border flex gap-2 border-b" role="tablist" aria-label="Homepage sections">
        <button class="border-primary min-h-11 border-b-2 px-4 text-sm font-semibold" class:opacity-50={tab !== 'hero'} role="tab" aria-selected={tab === 'hero'} onclick={() => (tab = 'hero')}>Hero Banner</button>
        <button class="border-primary min-h-11 border-b-2 px-4 text-sm font-semibold" class:opacity-50={tab !== 'stories'} role="tab" aria-selected={tab === 'stories'} onclick={() => (tab = 'stories')}>Campus Stories</button>
    </div>

    {#if notice}<p class="bg-primary/8 text-primary rounded-lg px-4 py-3 text-sm" role="status">{notice}</p>{/if}

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="border-border bg-card min-w-0 rounded-xl border p-5 shadow-sm" class:hidden={isCompact && viewport === 'preview'} aria-label="Homepage editor">
            {#if tab === 'hero'}
                <form class="space-y-4" onsubmit={submitHero}>
                    <div><h2 class="text-lg font-semibold">Hero banner</h2><p class="text-muted-foreground mt-1 text-sm">Set the opening message and photo shown to visitors.</p></div>
                    <label class="block space-y-1.5 text-sm font-medium">Eyebrow<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={heroForm.eyebrow} maxlength="255" /></label>
                    <label class="block space-y-1.5 text-sm font-medium">Headline<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={heroForm.title} required maxlength="255" /></label>
                    <label class="block space-y-1.5 text-sm font-medium">Introduction<textarea class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={heroForm.lede} required rows="4" maxlength="2000"></textarea></label>
                    <label class="block space-y-1.5 text-sm font-medium">Photo badge<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={heroForm.badge_text} maxlength="120" /></label>
                    <label class="block space-y-1.5 text-sm font-medium">Image description<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={heroForm.image_alt} maxlength="255" /></label>
                    <div class="space-y-2">
                        <p class="text-sm font-medium">Hero image</p>
                        {#if heroForm.image?.src}
                            <div class="flex items-center gap-3 rounded-lg border border-border p-2 bg-muted/20">
                                <img src={heroForm.image.src} alt={heroForm.image_alt || 'Hero preview'} class="size-14 rounded object-cover border border-border shrink-0" />
                                <div class="min-w-0 flex-1 text-xs">
                                    <p class="font-medium text-foreground">Image staged</p>
                                    <p class="text-muted-foreground truncate">{heroForm.image_alt || 'Save hero to publish'}</p>
                                </div>
                                <button type="button" class="text-xs text-destructive hover:underline px-2 py-1 font-medium inline-flex items-center gap-1" onclick={() => { heroForm.image_id = null; heroForm.image = null; }}><Trash2 class="size-3.5" />Remove</button>
                            </div>
                        {/if}
                        <label class="border-border hover:bg-muted inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm" class:opacity-50={imageUploading}>
                            <ImagePlus class="size-4" />
                            {imageUploading ? 'Processing image…' : (heroForm.image?.src ? 'Replace image' : 'Upload JPEG, PNG, or WebP')}
                            <input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" disabled={imageUploading} onchange={(event) => stageImage(event, 'hero')} />
                        </label>
                        <p class="text-muted-foreground text-xs">Maximum file size 5 MB. Uploads are sanitized before storage.</p>
                    </div>
                    <button class="bg-primary text-primary-foreground min-h-11 rounded-md px-5 text-sm font-semibold disabled:opacity-50" disabled={busy}>{busy ? 'Saving…' : 'Save hero'}</button>
                </form>
            {:else}
                <div class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-semibold">Campus Stories</h2><p class="text-muted-foreground mt-1 text-sm">Create, update, show, hide, and arrange homepage stories.</p></div><button class="bg-primary text-primary-foreground inline-flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-semibold" onclick={() => startStory()}><Plus class="size-4" />New story</button></div>
                    <ol class="divide-border divide-y rounded-lg border">
                        {#each stories as story, index (story.id)}
                            <li class="flex items-center gap-3 p-3">
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{story.title}</p><p class="text-muted-foreground mt-1 text-xs">{story.is_active ? 'Visible on homepage' : 'Hidden'}</p></div>
                                <button class="hover:bg-muted min-h-10 min-w-10 rounded-md" aria-label="Move story up" disabled={index === 0} onclick={() => moveStory(index, -1)}><ArrowUp class="mx-auto size-4" /></button>
                                <button class="hover:bg-muted min-h-10 min-w-10 rounded-md" aria-label="Move story down" disabled={index === stories.length - 1} onclick={() => moveStory(index, 1)}><ArrowDown class="mx-auto size-4" /></button>
                                <button class="hover:bg-muted min-h-10 rounded-md px-3 text-sm" onclick={() => startStory(story)}>Edit</button>
                                <button class="hover:bg-muted min-h-10 min-w-10 rounded-md" aria-label={story.is_active ? 'Hide story' : 'Show story'} onclick={() => toggleStory(story)}>{#if story.is_active}<EyeOff class="mx-auto size-4" />{:else}<Eye class="mx-auto size-4" />{/if}</button>
                            </li>
                        {:else}<li class="text-muted-foreground p-5 text-sm">No campus stories yet. Add a story to begin.</li>{/each}
                    </ol>
                    {#if storyForm}
                        <form class="border-border space-y-4 rounded-lg border p-4" onsubmit={saveStory}>
                            <div class="flex items-center justify-between"><h3 class="font-semibold">{selectedStory ? 'Edit story' : 'New story'}</h3><button type="button" class="text-muted-foreground min-h-10 px-2 text-sm" onclick={() => (storyForm = null)}>Cancel</button></div>
                            <label class="block space-y-1.5 text-sm font-medium">Eyebrow<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={storyForm.eyebrow} maxlength="255" /></label>
                            <label class="block space-y-1.5 text-sm font-medium">Title<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={storyForm.title} required maxlength="255" /></label>
                            <label class="block space-y-1.5 text-sm font-medium">Story text<textarea class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={storyForm.body} required rows="4" maxlength="4000"></textarea></label>
                            <label class="block space-y-1.5 text-sm font-medium">Image description<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={storyForm.image_alt} maxlength="255" /></label>
                            <div class="space-y-2">
                                <p class="text-sm font-medium">Story image</p>
                                {#if storyForm.image?.src}
                                    <div class="flex items-center gap-3 rounded-lg border border-border p-2 bg-muted/20">
                                        <img src={storyForm.image.src} alt={storyForm.image_alt || 'Story preview'} class="size-14 rounded object-cover border border-border shrink-0" />
                                        <div class="min-w-0 flex-1 text-xs">
                                            <p class="font-medium text-foreground">Image staged</p>
                                            <p class="text-muted-foreground truncate">{storyForm.image_alt || 'Save story to publish'}</p>
                                        </div>
                                        <button type="button" class="text-xs text-destructive hover:underline px-2 py-1 font-medium inline-flex items-center gap-1" onclick={() => { storyForm.image_id = null; storyForm.image = null; }}><Trash2 class="size-3.5" />Remove</button>
                                    </div>
                                {/if}
                                <label class="border-border hover:bg-muted inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm" class:opacity-50={storyImageUploading}>
                                    <ImagePlus class="size-4" />
                                    {storyImageUploading ? 'Processing image…' : (storyForm.image?.src ? 'Replace story image' : 'Upload story image')}
                                    <input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" disabled={storyImageUploading} onchange={(event) => stageImage(event, 'story')} />
                                </label>
                                <p class="text-muted-foreground text-xs">Maximum file size 5 MB. Uploads are sanitized before storage.</p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2"><label class="block space-y-1.5 text-sm font-medium">CTA label<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={storyForm.cta_label} maxlength="120" /></label><label class="block space-y-1.5 text-sm font-medium">CTA link<input class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={storyForm.cta_href} maxlength="2048" /></label></div>
                            <label class="block space-y-1.5 text-sm font-medium">Copy alignment<select class="border-input bg-background w-full rounded-md border px-3 py-2.5" bind:value={storyForm.align}><option value="right">Copy first</option><option value="left">Image first</option></select></label>
                            <button class="bg-primary text-primary-foreground min-h-11 rounded-md px-4 text-sm font-semibold">Save story</button>
                        </form>
                    {/if}
                </div>
            {/if}
        </section>
        <section class="border-border bg-muted/30 min-w-0 space-y-4 rounded-xl border p-5" class:hidden={isCompact && viewport === 'editor'} aria-label="Live preview">
            <div class="space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-primary text-xs font-bold tracking-[0.18em] uppercase">
                            {previewMode === 'live' ? 'Live Public Homepage (/)' : 'Instant Draft Preview'}
                        </p>
                        <h2 class="mt-1 text-lg font-semibold">
                            {previewMode === 'live'
                                ? (previewDevice === 'desktop' ? 'Desktop Viewport (1440 × 900)' : 'Mobile Viewport (390 × 780)')
                                : (tab === 'hero' ? 'Hero Banner' : 'Campus Stories')}
                        </h2>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            {previewMode === 'live'
                                ? 'Saved public homepage — updates automatically after saving.'
                                : 'Unsaved section preview — reflects your edits as you type.'}
                        </p>
                    </div>
                    <a
                        href="/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="border-border bg-background hover:bg-muted inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3.5 py-2 text-sm font-medium transition-colors"
                    >
                        <ExternalLink class="size-4 shrink-0" />
                        Open Live ↗
                    </a>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2.5 border-t border-border/60 pt-3">
                    <div class="bg-muted inline-flex max-w-full rounded-lg p-1" role="group" aria-label="Preview mode">
                        <button
                            type="button"
                            aria-pressed={previewMode === 'draft'}
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition-colors {previewMode === 'draft' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                            onclick={() => (previewMode = 'draft')}
                        >
                            Instant Draft
                        </button>
                        <button
                            type="button"
                            aria-pressed={previewMode === 'live'}
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition-colors {previewMode === 'live' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                            onclick={() => {
                                iframeLoading = true;
                                previewMode = 'live';
                            }}
                        >
                            Live Page (/)
                        </button>
                    </div>

                    {#if previewMode === 'live'}
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="bg-muted inline-flex max-w-full rounded-lg p-1" role="group" aria-label="Preview viewport width">
                                <button
                                    type="button"
                                    aria-pressed={previewDevice === 'desktop'}
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition-colors {previewDevice === 'desktop' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                                    onclick={() => (previewDevice = 'desktop')}
                                    title="Simulated 1440 × 900 desktop viewport"
                                >
                                    <Monitor class="size-4 shrink-0" />
                                    Desktop
                                </button>
                                <button
                                    type="button"
                                    aria-pressed={previewDevice === 'mobile'}
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition-colors {previewDevice === 'mobile' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                                    onclick={() => (previewDevice = 'mobile')}
                                    title="Simulated 390 × 780 mobile viewport"
                                >
                                    <Smartphone class="size-4 shrink-0" />
                                    Mobile
                                </button>
                            </div>
                            <button
                                type="button"
                                class="border-border bg-background hover:bg-muted inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3.5 py-2 text-sm font-medium transition-colors"
                                onclick={refreshLivePreview}
                                title="Reload live homepage frame"
                            >
                                <RefreshCw class="size-4 shrink-0" />
                                Refresh
                            </button>
                        </div>
                    {/if}
                </div>
            </div>

            <div class="w-full min-w-0" use:observeStageWidth>
                {#if previewMode === 'live'}
                    <div class="border-border bg-background overflow-hidden rounded-xl border shadow-xs">
                        <div class="border-border bg-muted/50 flex flex-wrap items-center justify-between gap-2 border-b px-3.5 py-2 text-xs text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <span class="inline-block size-2 rounded-full bg-emerald-500"></span>
                                <span class="font-mono font-medium text-foreground">/ (Public Homepage)</span>
                                <span>· {previewDevice === 'desktop' ? `1440 × 900 (${Math.round(desktopScale * 100)}% scale)` : `390 × 780 (${Math.round(mobileScale * 100)}% scale)`}</span>
                            </div>
                            <span>{iframeLoading ? 'Loading live frame…' : 'Interactive live frame'}</span>
                        </div>

                        {#if previewDevice === 'desktop'}
                            <div class="bg-muted/20 p-2 sm:p-3">
                                <div
                                    class="border-border bg-background relative w-full overflow-hidden rounded-lg border shadow-xs"
                                    style={`height: ${desktopStageHeight}px;`}
                                >
                                    <div
                                        style={`position: absolute; left: 0; top: 0; width: 1440px; height: 900px; transform: scale(${desktopScale}); transform-origin: top left;`}
                                    >
                                        {#key iframeKey}
                                            <iframe
                                                src={`/?_preview=${iframeKey}`}
                                                title="Live Public Homepage Desktop Preview (1440 × 900)"
                                                width="1440"
                                                height="900"
                                                onload={() => (iframeLoading = false)}
                                                style="display: block; width: 1440px; height: 900px; max-width: none; border: 0;"
                                            ></iframe>
                                        {/key}
                                    </div>
                                </div>
                            </div>
                        {:else}
                            <div class="flex justify-center bg-muted/25 py-4 px-2">
                                <div
                                    class="relative"
                                    style={`width: ${mobileStageWidth}px; height: ${mobileStageHeight}px;`}
                                >
                                    <div
                                        class="rounded-[36px] border border-zinc-700/80 bg-zinc-900 shadow-xl"
                                        style={`position: absolute; left: 0; top: 0; width: 410px; height: 824px; padding: 34px 10px 10px 10px; transform: scale(${mobileScale}); transform-origin: top left;`}
                                    >
                                        <div
                                            aria-hidden="true"
                                            class="absolute inset-x-0 top-0 flex h-[34px] items-center justify-between px-6 text-[11px] font-semibold text-zinc-300"
                                        >
                                            <span>9:41</span>
                                            <span class="h-1.5 w-16 rounded-full bg-zinc-700"></span>
                                            <span>5G · 100%</span>
                                        </div>
                                        <div class="h-[780px] w-[390px] overflow-hidden rounded-[28px] bg-background">
                                            {#key iframeKey}
                                                <iframe
                                                    src={`/?_preview=${iframeKey}`}
                     title="Live Public Homepage Mobile Preview (390 × 780)"
                                                    width="390"
                                                    height="780"
                                                    onload={() => (iframeLoading = false)}
                                                    style="display: block; width: 390px; height: 780px; max-width: none; border: 0;"
                                                ></iframe>
                                            {/key}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {/if}
                    </div>
                {:else if tab === 'hero'}
                    <div class="relative space-y-4 rounded-xl bg-background p-4">
                        <p class="text-primary text-xs font-bold uppercase">{heroForm.eyebrow || 'Institution · Campus'}</p>
                        <h3 class="text-3xl font-semibold tracking-tight">{heroForm.title || 'Your homepage headline'}</h3>
                        <p class="text-muted-foreground leading-7">{heroForm.lede || 'Your introduction will appear here.'}</p>
                        <span class="absolute top-4 right-4 rounded-full border bg-background/90 px-3 py-2 text-xs font-semibold">{heroForm.badge_text || 'Institution badge'}</span>
                        <MediaFrame image={heroForm.image ?? { src: null, alt: heroForm.image_alt }} ratio="aspect-[4/3]" />
                    </div>
                {:else if selectedPreview}
                    <div class="space-y-8 rounded-xl bg-background p-4">
                        <StorySection story={selectedPreview} />
                    </div>
                {:else}<p class="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">Create a story to preview the Campus Stories section.</p>{/if}
            </div>
        </section>
    </div>
</div>
</StaffLayout>

<style>
    @media (max-width: 390px) { :global(.hidden) { display: none; } }
</style>
