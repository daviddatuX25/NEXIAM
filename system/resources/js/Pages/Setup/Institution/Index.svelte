<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { router } from '@inertiajs/svelte';
    import {
        Building2,
        MapPin,
        Phone,
        Mail,
        Globe,
        ImagePlus,
        Trash2,
        RotateCcw,
        Save,
        Sparkles,
        Eye,
    } from 'lucide-svelte';
    import {
        success as toastSuccess,
        error as toastError,
    } from '@/lib/toast.js';

    let {
        institution = {
            id: null,
            code: 'SYN-INST-001',
            name: '',
            short_name: '',
            campus_name: '',
            address: '',
            location: '',
            contact_number: '',
            email: '',
            website: '',
            logo_url: null,
            logo_byte_size: null,
            logo_mime: null,
            fallback_monogram: 'I',
        },
        env_defaults = {},
        can_manage = false,
    } = $props();

    const breadcrumbs = [
        { label: 'Admin Office', href: '/admin' },
        { label: 'Institution Profile & Logo' },
    ];

    let form = $state({
        name: institution.name || '',
        short_name: institution.short_name || '',
        campus_name: institution.campus_name || '',
        address: institution.address || '',
        location: institution.location || '',
        contact_number: institution.contact_number || '',
        email: institution.email || '',
        website: institution.website || '',
    });

    let saving = $state(false);
    let uploadingLogo = $state(false);
    let showResetDialog = $state(false);
    let showDeleteLogoDialog = $state(false);
    let logoFileInput;

    const monogram = $derived.by(() => {
        const short = form.short_name?.trim();
        if (short) return short.slice(0, 1).toUpperCase();
        const full = form.name?.trim();
        if (full) return full.slice(0, 1).toUpperCase();
        return institution.fallback_monogram || 'N';
    });

    const isDirty = $derived.by(() => {
        return (
            form.name !== (institution.name || '') ||
            form.short_name !== (institution.short_name || '') ||
            form.campus_name !== (institution.campus_name || '') ||
            form.address !== (institution.address || '') ||
            form.location !== (institution.location || '') ||
            form.contact_number !== (institution.contact_number || '') ||
            form.email !== (institution.email || '') ||
            form.website !== (institution.website || '')
        );
    });

    function saveProfile(e) {
        if (e) e.preventDefault();
        if (!can_manage) return;

        saving = true;
        router.put('/setup/institution', form, {
            preserveScroll: true,
            onSuccess: () => {
                saving = false;
                toastSuccess('Institution profile updated successfully.');
            },
            onError: (errors) => {
                saving = false;
                const msg =
                    Object.values(errors)[0] ||
                    'Failed to update institution profile.';
                toastError(msg);
            },
        });
    }

    function handleLogoSelect(e) {
        const file = e.target.files?.[0];
        if (!file || !can_manage) return;

        uploadingLogo = true;
        const data = new FormData();
        data.append('logo', file);

        router.post('/setup/institution/logo', data, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                uploadingLogo = false;
                if (logoFileInput) logoFileInput.value = '';
                toastSuccess(
                    'Official institutional logo uploaded successfully.',
                );
            },
            onError: (errors) => {
                uploadingLogo = false;
                if (logoFileInput) logoFileInput.value = '';
                const msg =
                    errors.logo ||
                    Object.values(errors)[0] ||
                    'Failed to upload logo.';
                toastError(msg);
            },
        });
    }

    function confirmDeleteLogo() {
        if (!can_manage) return;
        showDeleteLogoDialog = false;

        router.delete('/setup/institution/logo', {
            preserveScroll: true,
            onSuccess: () => {
                toastSuccess(
                    'Institutional logo removed. Monogram fallback is now active.',
                );
            },
            onError: (errors) => {
                const msg =
                    Object.values(errors)[0] || 'Failed to delete logo.';
                toastError(msg);
            },
        });
    }

    function confirmReset() {
        if (!can_manage) return;
        showResetDialog = false;

        router.post(
            '/setup/institution/reset',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.name = env_defaults.name || '';
                    form.short_name = env_defaults.short_name || '';
                    form.campus_name = env_defaults.campus_name || '';
                    form.address = env_defaults.address || '';
                    form.location = env_defaults.location || '';
                    form.contact_number = env_defaults.contact_number || '';
                    form.email = env_defaults.email || '';
                    form.website = env_defaults.website || '';
                    toastSuccess(
                        'Institution profile reset to environment defaults.',
                    );
                },
                onError: (errors) => {
                    const msg =
                        Object.values(errors)[0] || 'Failed to reset defaults.';
                    toastError(msg);
                },
            },
        );
    }

    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
</script>

<svelte:head>
    <title>Institution Profile & Logo - NEXIAM Admin Office</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto max-w-7xl space-y-6 pb-16">
        <!-- Header Bar -->
        <PageHeaderBar>
            {#snippet title()}
                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="text-foreground text-xl font-bold tracking-tight sm:text-2xl sm:text-3xl"
                    >
                        Institution Profile & Logo
                    </h1>
                    <Badge
                        variant="outline"
                        class="font-mono text-xs font-semibold"
                    >
                        {institution.code}
                    </Badge>
                </div>
            {/snippet}

            {#snippet subtitle()}
                <p class="text-muted-foreground mt-0.5 text-xs sm:text-sm">
                    Official corporate identity, campus location, admissions
                    contact channels, and visual insignia.
                </p>
            {/snippet}

            {#snippet actions()}
                <div class="flex items-center gap-2.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!can_manage || saving}
                        onclick={() => (showResetDialog = true)}
                        class="gap-1.5 text-xs font-semibold"
                    >
                        <RotateCcw class="text-muted-foreground size-3.5" />
                        <span>Reset Defaults</span>
                    </Button>

                    <Button
                        type="button"
                        variant="default"
                        size="sm"
                        disabled={!can_manage || saving || !isDirty}
                        onclick={saveProfile}
                        class="gap-1.5 text-xs font-semibold shadow-sm"
                    >
                        <Save class="size-3.5" />
                        <span>{saving ? 'Saving…' : 'Save Changes'}</span>
                    </Button>
                </div>
            {/snippet}
        </PageHeaderBar>

        <!-- 2-Column Responsive Layout (0px horizontal overflow on 390px mobile) -->
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            <!-- Left Rail: Visual Branding & Insignia (lg:col-span-5 xl:col-span-4) -->
            <div class="space-y-6 lg:col-span-5 xl:col-span-4">
                <!-- Brand Logo Card -->
                <div
                    class="border-border/80 bg-card space-y-5 rounded-xl border p-5 shadow-sm"
                >
                    <div
                        class="border-border/50 flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <Sparkles class="text-primary size-4" />
                            <h2
                                class="text-foreground text-sm font-semibold sm:text-base"
                            >
                                Official Logo & Insignia
                            </h2>
                        </div>
                        {#if institution.logo_url}
                            <Badge
                                variant="default"
                                class="border-emerald-600/20 bg-emerald-600/15 text-[10px] font-semibold text-emerald-700 dark:text-emerald-400"
                            >
                                Active Logo
                            </Badge>
                        {:else}
                            <Badge
                                variant="secondary"
                                class="text-[10px] font-semibold"
                            >
                                Monogram Fallback
                            </Badge>
                        {/if}
                    </div>

                    <!-- Logo Viewport -->
                    <div class="space-y-3">
                        <div
                            class="border-border/80 bg-muted/20 group relative mx-auto flex size-44 flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed p-4 sm:size-52"
                        >
                            {#if institution.logo_url}
                                <img
                                    src={institution.logo_url}
                                    alt="Official Institution Logo"
                                    class="max-h-full max-w-full object-contain drop-shadow-md transition-transform duration-300 group-hover:scale-105"
                                />
                            {:else}
                                <div
                                    class="flex flex-col items-center justify-center gap-2 text-center"
                                >
                                    <span
                                        class="bg-primary text-primary-foreground shadow-primary/25 flex size-16 items-center justify-center rounded-2xl text-3xl font-black shadow-lg transition-transform group-hover:-rotate-3 sm:size-20 sm:text-4xl"
                                    >
                                        {monogram}
                                    </span>
                                    <p
                                        class="text-muted-foreground mt-1 text-[11px] font-medium"
                                    >
                                        No logo uploaded
                                    </p>
                                </div>
                            {/if}
                        </div>

                        {#if institution.logo_url}
                            <div
                                class="text-muted-foreground flex items-center justify-center gap-2 text-center font-mono text-[11px]"
                            >
                                <span
                                    >{institution.logo_mime ||
                                        'image/webp'}</span
                                >
                                <span>·</span>
                                <span
                                    >{formatBytes(
                                        institution.logo_byte_size,
                                    )}</span
                                >
                            </div>
                        {/if}
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2 pt-1">
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            bind:this={logoFileInput}
                            onchange={handleLogoSelect}
                        />

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="w-full gap-2 text-xs font-semibold"
                            disabled={!can_manage || uploadingLogo}
                            onclick={() => logoFileInput?.click()}
                        >
                            <ImagePlus class="text-primary size-4" />
                            <span
                                >{uploadingLogo
                                    ? 'Processing…'
                                    : institution.logo_url
                                      ? 'Replace Logo'
                                      : 'Upload Official Logo'}</span
                            >
                        </Button>

                        {#if institution.logo_url}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive hover:bg-destructive/10 w-full gap-2 text-xs"
                                disabled={!can_manage || uploadingLogo}
                                onclick={() => (showDeleteLogoDialog = true)}
                            >
                                <Trash2 class="size-4" />
                                <span>Remove Logo</span>
                            </Button>
                        {/if}
                    </div>

                    <p
                        class="text-muted-foreground text-center text-[11px] leading-relaxed"
                    >
                        Supports PNG, JPG, or WebP up to 2 MB. Insignia is
                        sanitized and fit into 512×512 resolution.
                    </p>
                </div>

                <!-- Live Interface Simulation Card -->
                <div
                    class="border-border/80 bg-card space-y-3 rounded-xl border p-5 shadow-sm"
                >
                    <div
                        class="border-border/50 flex items-center gap-2 border-b pb-2.5"
                    >
                        <Eye class="text-muted-foreground size-3.5" />
                        <h3
                            class="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                        >
                            Live Header Simulation
                        </h3>
                    </div>

                    <div
                        class="border-border/60 bg-background/80 space-y-2 rounded-lg border p-3.5"
                    >
                        <div class="flex items-center gap-3">
                            {#if institution.logo_url}
                                <img
                                    src={institution.logo_url}
                                    alt="Logo preview"
                                    class="size-9 shrink-0 rounded-lg object-contain drop-shadow-sm"
                                />
                            {:else}
                                <span
                                    class="bg-primary text-primary-foreground shadow-primary/20 flex size-9 shrink-0 items-center justify-center rounded-xl text-base font-extrabold shadow-md"
                                >
                                    {monogram}
                                </span>
                            {/if}

                            <div class="min-w-0 flex-1">
                                <span
                                    class="text-foreground block truncate text-xs font-bold tracking-tight sm:text-sm"
                                >
                                    {form.name || 'Institution Name'}
                                </span>
                                <span
                                    class="text-muted-foreground block truncate text-[10px] font-semibold tracking-wider uppercase"
                                >
                                    {form.campus_name ||
                                        form.location ||
                                        'Main Campus'}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p
                        class="text-muted-foreground text-[11px] leading-relaxed"
                    >
                        Simulates how the institutional brand renders across
                        public banners, student portals, and report headers.
                    </p>
                </div>
            </div>

            <!-- Right Rail: Identity & Contact Channels (lg:col-span-7 xl:col-span-8) -->
            <div class="space-y-6 lg:col-span-7 xl:col-span-8">
                <form onsubmit={saveProfile} class="space-y-6">
                    <!-- Card 1: Official Corporate Identity -->
                    <div
                        class="border-border/80 bg-card space-y-4 rounded-xl border p-5 shadow-sm"
                    >
                        <div
                            class="border-border/50 flex items-center gap-2 border-b pb-3"
                        >
                            <Building2 class="text-primary size-4" />
                            <h2
                                class="text-foreground text-sm font-semibold sm:text-base"
                            >
                                Corporate Identity
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-12">
                            <div class="space-y-1.5 sm:col-span-8">
                                <label
                                    for="name"
                                    class="text-foreground block text-xs font-semibold"
                                >
                                    Full Official Name <span
                                        class="text-destructive">*</span
                                    >
                                </label>
                                <input
                                    id="name"
                                    type="text"
                                    required
                                    bind:value={form.name}
                                    placeholder="e.g. Ilocos Sur Polytechnic State College"
                                    class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                />
                            </div>

                            <div class="space-y-1.5 sm:col-span-4">
                                <label
                                    for="short_name"
                                    class="text-foreground block text-xs font-semibold"
                                >
                                    Short Name / Acronym
                                </label>
                                <input
                                    id="short_name"
                                    type="text"
                                    bind:value={form.short_name}
                                    placeholder="e.g. ISPSC"
                                    class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 font-mono text-sm font-semibold uppercase focus-visible:ring-2 focus-visible:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Campus & Location -->
                    <div
                        class="border-border/80 bg-card space-y-4 rounded-xl border p-5 shadow-sm"
                    >
                        <div
                            class="border-border/50 flex items-center gap-2 border-b pb-3"
                        >
                            <MapPin class="text-primary size-4" />
                            <h2
                                class="text-foreground text-sm font-semibold sm:text-base"
                            >
                                Campus & Physical Location
                            </h2>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label
                                        for="campus_name"
                                        class="text-foreground block text-xs font-semibold"
                                    >
                                        Campus Name
                                    </label>
                                    <input
                                        id="campus_name"
                                        type="text"
                                        bind:value={form.campus_name}
                                        placeholder="e.g. Tagudin Campus"
                                        class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <label
                                        for="location"
                                        class="text-foreground block text-xs font-semibold"
                                    >
                                        City / Province Locator
                                    </label>
                                    <input
                                        id="location"
                                        type="text"
                                        bind:value={form.location}
                                        placeholder="e.g. Tagudin, Ilocos Sur"
                                        class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                    />
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    for="address"
                                    class="text-foreground block text-xs font-semibold"
                                >
                                    Street / Physical Address
                                </label>
                                <textarea
                                    id="address"
                                    rows="2"
                                    bind:value={form.address}
                                    placeholder="e.g. Quirino, Tagudin, Ilocos Sur, Philippines 2714"
                                    class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                ></textarea>
                                <p class="text-muted-foreground text-[11px]">
                                    Printed on official examination permits,
                                    admission clearance slips, and results
                                    certifications.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: Contact Channels -->
                    <div
                        class="border-border/80 bg-card space-y-4 rounded-xl border p-5 shadow-sm"
                    >
                        <div
                            class="border-border/50 flex items-center gap-2 border-b pb-3"
                        >
                            <Phone class="text-primary size-4" />
                            <h2
                                class="text-foreground text-sm font-semibold sm:text-base"
                            >
                                Admissions & Inquiries Contact
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label
                                    for="contact_number"
                                    class="text-foreground block flex items-center gap-1.5 text-xs font-semibold"
                                >
                                    <Phone
                                        class="text-muted-foreground size-3"
                                    />
                                    <span>Telephone / Mobile</span>
                                </label>
                                <input
                                    id="contact_number"
                                    type="text"
                                    bind:value={form.contact_number}
                                    placeholder="e.g. +63 (77) 674-1234"
                                    class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    for="email"
                                    class="text-foreground block flex items-center gap-1.5 text-xs font-semibold"
                                >
                                    <Mail
                                        class="text-muted-foreground size-3"
                                    />
                                    <span>Official Inquiries Email</span>
                                </label>
                                <input
                                    id="email"
                                    type="email"
                                    bind:value={form.email}
                                    placeholder="e.g. admissions.tagudin@ispsc.edu.ph"
                                    class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                />
                            </div>

                            <div class="space-y-1.5 sm:col-span-2">
                                <label
                                    for="website"
                                    class="text-foreground block flex items-center gap-1.5 text-xs font-semibold"
                                >
                                    <Globe
                                        class="text-muted-foreground size-3"
                                    />
                                    <span>Institutional Website URL</span>
                                </label>
                                <input
                                    id="website"
                                    type="url"
                                    bind:value={form.website}
                                    placeholder="e.g. https://ispsc.edu.ph"
                                    class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Bar CTA -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Button
                            type="submit"
                            variant="default"
                            size="default"
                            disabled={!can_manage || saving || !isDirty}
                            class="gap-2 px-6 font-semibold shadow-sm"
                        >
                            <Save class="size-4" />
                            <span
                                >{saving
                                    ? 'Saving changes…'
                                    : 'Save Profile Changes'}</span
                            >
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</StaffLayout>

<!-- Confirmation Modal: Delete Logo -->
{#if showDeleteLogoDialog}
    <div
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm duration-200"
    >
        <div
            class="border-border bg-card w-full max-w-md space-y-4 rounded-xl border p-6 shadow-xl"
        >
            <div class="flex items-start gap-3">
                <div
                    class="bg-destructive/10 text-destructive flex size-10 shrink-0 items-center justify-center rounded-full"
                >
                    <Trash2 class="size-5" />
                </div>
                <div>
                    <h3 class="text-foreground text-base font-semibold">
                        Remove official logo?
                    </h3>
                    <p
                        class="text-muted-foreground mt-1 text-xs leading-relaxed"
                    >
                        This will permanently delete the uploaded institutional
                        logo asset from storage. The application will instantly
                        fall back to the institutional monogram.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onclick={() => (showDeleteLogoDialog = false)}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    size="sm"
                    onclick={confirmDeleteLogo}
                >
                    Yes, remove logo
                </Button>
            </div>
        </div>
    </div>
{/if}

<!-- Confirmation Modal: Reset Defaults -->
{#if showResetDialog}
    <div
        class="animate-in fade-in fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm duration-200"
    >
        <div
            class="border-border bg-card w-full max-w-md space-y-4 rounded-xl border p-6 shadow-xl"
        >
            <div class="flex items-start gap-3">
                <div
                    class="bg-warning/10 text-warning flex size-10 shrink-0 items-center justify-center rounded-full"
                >
                    <RotateCcw class="size-5" />
                </div>
                <div>
                    <h3 class="text-foreground text-base font-semibold">
                        Reset to environment defaults?
                    </h3>
                    <p
                        class="text-muted-foreground mt-1 text-xs leading-relaxed"
                    >
                        This will revert the institution name, campus, address,
                        and contact details to the baseline values configured in <code
                            class="font-mono text-xs">public-site.php</code
                        >.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onclick={() => (showResetDialog = false)}
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    variant="default"
                    size="sm"
                    onclick={confirmReset}
                >
                    Confirm reset
                </Button>
            </div>
        </div>
    </div>
{/if}
