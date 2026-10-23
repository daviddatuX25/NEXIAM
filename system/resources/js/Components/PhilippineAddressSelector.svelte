<script lang="ts">
    import { onMount } from 'svelte';
    import {
        defaultAddressProvider,
        formatAddressLine,
    } from '@/lib/address/ph-address-adapter';
    import type { AddressItem, AddressProvider } from '@/lib/address/types';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';

    interface Props {
        address_line?: string;
        city?: string;
        province?: string;
        zip_code?: string;
        provider?: AddressProvider;
        idPrefix?: string;
        disabled?: boolean;
        allowManual?: boolean;
        required?: boolean;
        label?: string;
        errors?: Record<string, string | undefined>;
        onChange?: (payload: {
            address_line: string;
            city: string;
            province: string;
            zip_code: string;
        }) => void;
    }

    let {
        address_line = $bindable(''),
        city = $bindable(''),
        province = $bindable(''),
        zip_code = $bindable(''),
        provider = defaultAddressProvider,
        idPrefix = 'ph-addr',
        disabled = false,
        required = false,
        allowManual = true,
        label = 'Address',
        errors = {},
        onChange,
    }: Props = $props();
    let regions = $state<AddressItem[]>([]);
    let provinces = $state<AddressItem[]>([]);
    let cities = $state<AddressItem[]>([]);
    let barangays = $state<AddressItem[]>([]);

    let selectedRegionCode = $state('');
    let selectedProvinceCode = $state('');
    let selectedCityCode = $state('');
    let selectedBarangayCode = $state('');
    let street = $state('');
    let manualMode = $state(false);
    let addressDataLoadFailed = $state(false);
    let isLoading = $state(false);

    let isNCR = $derived(selectedRegionCode === '1300000000');

    function syncPayload(barangayName = ''): void {
        const currentBarangay =
            barangayName ||
            barangays.find((b) => b.code === selectedBarangayCode)?.name ||
            '';

        address_line = formatAddressLine(street, currentBarangay);

        if (onChange) {
            onChange({
                address_line,
                city,
                province,
                zip_code,
            });
        }
    }

    async function handleRegionChange(e: Event): Promise<void> {
        const code = (e.target as HTMLSelectElement).value;
        selectedRegionCode = code;
        addressDataLoadFailed = false;

        // Reset downstream
        selectedProvinceCode = '';
        selectedCityCode = '';
        selectedBarangayCode = '';
        provinces = [];
        cities = [];
        barangays = [];
        city = '';

        if (!code) {
            province = '';
            syncPayload();
            return;
        }

        isLoading = true;
        try {
            if (code === '1300000000') {
                // NCR: Province is Metro Manila, fetch cities directly
                province = 'Metro Manila';
                selectedProvinceCode = '1300000000';
                cities = await provider.getCities('1300000000');
            } else {
                province = '';
                provinces = await provider.getProvinces(code);
            }
        } catch (err) {
            addressDataLoadFailed = true;
            console.error('Failed to load Philippine address options:', err);
        } finally {
            isLoading = false;
            syncPayload();
        }
    }

    async function handleProvinceChange(e: Event): Promise<void> {
        const code = (e.target as HTMLSelectElement).value;
        addressDataLoadFailed = false;
        selectedProvinceCode = code;

        selectedCityCode = '';
        selectedBarangayCode = '';
        cities = [];
        barangays = [];
        city = '';

        if (!code) {
            province = '';
            syncPayload();
            return;
        }

        const foundProv = provinces.find((p) => p.code === code);
        province = foundProv?.name ?? '';

        isLoading = true;
        try {
            cities = await provider.getCities(code);
        } catch (err) {
            addressDataLoadFailed = true;
            console.error('Failed to load Philippine address options:', err);
        } finally {
            isLoading = false;
            syncPayload();
        }
    }

    async function handleCityChange(e: Event): Promise<void> {
        const code = (e.target as HTMLSelectElement).value;
        addressDataLoadFailed = false;
        selectedCityCode = code;

        selectedBarangayCode = '';
        barangays = [];

        if (!code) {
            city = '';
            syncPayload();
            return;
        }

        const foundCity = cities.find((c) => c.code === code);
        city = foundCity?.name ?? '';

        isLoading = true;
        try {
            const [fetchedBarangays, zip] = await Promise.all([
                provider.getBarangays(code),
                provider.getZipCode(code),
            ]);
            barangays = fetchedBarangays;
            if (zip && !zip_code) {
                zip_code = zip;
            }
        } catch (err) {
            addressDataLoadFailed = true;
            console.error('Failed to load Philippine address options:', err);
        } finally {
            isLoading = false;
            syncPayload();
        }
    }

    function handleBarangayChange(e: Event): void {
        const code = (e.target as HTMLSelectElement).value;
        selectedBarangayCode = code;
        const foundBarangay = barangays.find((b) => b.code === code);
        syncPayload(foundBarangay?.name ?? '');
    }

    function handleStreetInput(e: Event): void {
        street = (e.target as HTMLInputElement).value;
        syncPayload();
    }

    function toggleManualMode(): void {
        manualMode = !manualMode;
    }

    onMount(async () => {
        try {
            regions = await provider.getRegions();
        } catch (err) {
            addressDataLoadFailed = true;
            console.error('Failed to load Philippine regions:', err);
        }
    });
</script>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <span
            class="text-muted-foreground text-xs font-medium tracking-wider uppercase"
        >
            {manualMode ? 'Manual Address Entry' : label}
        </span>
        {#if allowManual}
            <button
                type="button"
                onclick={toggleManualMode}
                class="text-primary text-xs hover:underline focus:outline-none"
            >
                {manualMode
                    ? 'Use address picker'
                    : 'Enter address manually'}
            </button>
        {/if}
    </div>
    {#if addressDataLoadFailed}
        <p class="text-destructive text-sm" role="alert">
            Philippine address choices are temporarily unavailable.
            {#if allowManual}
                You can switch to manual address entry.
            {:else}
                You may leave this optional address blank and continue.
            {/if}
        </p>
    {/if}

    {#if allowManual && manualMode}
        <!-- Fallback Manual Entry Mode -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2 sm:col-span-2">
                <Label for="{idPrefix}-manual-address" class=""
                    >Address Line / Street / Barangay</Label
                >
                <Input
                    id="{idPrefix}-manual-address"
                    type="text"
                    bind:value={address_line}
                    {disabled}
                    {required}
                    placeholder="e.g. 123 Main St, Brgy. Central"
                    class="min-h-11"
                />
                {#if errors.address_line}
                    <p class="text-destructive text-xs">
                        {errors.address_line}
                    </p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="{idPrefix}-manual-city" class=""
                    >City / Municipality</Label
                >
                <Input
                    id="{idPrefix}-manual-city"
                    type="text"
                    bind:value={city}
                    {disabled}
                    {required}
                    placeholder="e.g. Tagudin"
                    class="min-h-11"
                />
                {#if errors.city}
                    <p class="text-destructive text-xs">{errors.city}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="{idPrefix}-manual-province" class="">Province</Label
                >
                <Input
                    id="{idPrefix}-manual-province"
                    type="text"
                    bind:value={province}
                    {disabled}
                    {required}
                    placeholder="e.g. Ilocos Sur"
                    class="min-h-11"
                />
                {#if errors.province}
                    <p class="text-destructive text-xs">{errors.province}</p>
                {/if}
            </div>

            <div class="space-y-2">
                <Label for="{idPrefix}-manual-zip" class=""
                    >ZIP / Postal Code</Label
                >
                <Input
                    id="{idPrefix}-manual-zip"
                    type="text"
                    bind:value={zip_code}
                    {disabled}
                    placeholder="e.g. 2714"
                    class="min-h-11"
                />
                {#if errors.zip_code}
                    <p class="text-destructive text-xs">{errors.zip_code}</p>
                {/if}
            </div>
        </div>
    {:else}
        <!-- Structured PSGC Cascading Selector -->
        <div class="grid gap-4 sm:grid-cols-2">
            <!-- 1. Region -->
            <div class="space-y-2">
                <Label for="{idPrefix}-region" class="">Region</Label>
                <select
                    id="{idPrefix}-region"
                    value={selectedRegionCode}
                    onchange={handleRegionChange}
                    {disabled}
                    class="border-input bg-background focus-visible:ring-ring flex min-h-11 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                >
                    <option value="">Select Region</option>
                    {#each regions as r (r.code)}
                        <option value={r.code}>{r.name}</option>
                    {/each}
                </select>
            </div>

            <!-- 2. Province (Bypassed if NCR) -->
            <div class="space-y-2">
                <Label for="{idPrefix}-province" class="">Province</Label>
                {#if isNCR}
                    <div
                        class="border-input bg-muted text-muted-foreground flex min-h-11 w-full items-center rounded-md border px-3 text-sm"
                    >
                        Metro Manila (NCR)
                    </div>
                {:else}
                    <select
                        id="{idPrefix}-province"
                        value={selectedProvinceCode}
                        onchange={handleProvinceChange}
                        disabled={disabled ||
                            !selectedRegionCode ||
                            provinces.length === 0}
                        class="border-input bg-background focus-visible:ring-ring flex min-h-11 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value=""
                            >{selectedRegionCode
                                ? 'Select Province'
                                : 'Select Region first'}</option
                        >
                        {#each provinces as p (p.code)}
                            <option value={p.code}>{p.name}</option>
                        {/each}
                    </select>
                {/if}
                {#if errors.province}
                    <p class="text-destructive text-xs">{errors.province}</p>
                {/if}
            </div>

            <!-- 3. City / Municipality -->
            <div class="space-y-2">
                <Label for="{idPrefix}-city" class="">City / Municipality</Label
                >
                <select
                    id="{idPrefix}-city"
                    value={selectedCityCode}
                    onchange={handleCityChange}
                    disabled={disabled ||
                        (!selectedProvinceCode && !isNCR) ||
                        cities.length === 0}
                    class="border-input bg-background focus-visible:ring-ring flex min-h-11 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value=""
                        >{selectedProvinceCode || isNCR
                            ? 'Select City / Municipality'
                            : 'Select Province first'}</option
                    >
                    {#each cities as c (c.code)}
                        <option value={c.code}>{c.name}</option>
                    {/each}
                </select>
                {#if errors.city}
                    <p class="text-destructive text-xs">{errors.city}</p>
                {/if}
            </div>

            <!-- 4. Barangay -->
            <div class="space-y-2">
                <Label for="{idPrefix}-barangay" class="">Barangay</Label>
                <select
                    id="{idPrefix}-barangay"
                    value={selectedBarangayCode}
                    onchange={handleBarangayChange}
                    disabled={disabled ||
                        !selectedCityCode ||
                        barangays.length === 0}
                    class="border-input bg-background focus-visible:ring-ring flex min-h-11 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value=""
                        >{selectedCityCode
                            ? 'Select Barangay'
                            : 'Select City first'}</option
                    >
                    {#each barangays as b (b.code)}
                        <option value={b.code}>{b.name}</option>
                    {/each}
                </select>
            </div>

            <!-- 5. Street / House No. / Purok / Sitio -->
            <div class="space-y-2">
                <Label for="{idPrefix}-street" class="">
                    House No. / Street / Purok / Sitio <span
                        class="text-muted-foreground text-xs font-normal"
                        >(optional)</span
                    >
                </Label>
                <Input
                    id="{idPrefix}-street"
                    type="text"
                    value={street}
                    oninput={handleStreetInput}
                    {disabled}
                    placeholder="e.g. Purok 3, 45 Mabini St."
                    class="min-h-11"
                />
                {#if errors.address_line}
                    <p class="text-destructive text-xs">
                        {errors.address_line}
                    </p>
                {/if}
            </div>

            <!-- 6. ZIP / Postal Code -->
            <div class="space-y-2">
                <Label for="{idPrefix}-zip" class="">
                    ZIP Code <span
                        class="text-muted-foreground text-xs font-normal"
                        >(auto-filled)</span
                    >
                </Label>
                <Input
                    id="{idPrefix}-zip"
                    type="text"
                    bind:value={zip_code}
                    {disabled}
                    placeholder="e.g. 2714"
                    class="min-h-11"
                />
                {#if errors.zip_code}
                    <p class="text-destructive text-xs">{errors.zip_code}</p>
                {/if}
            </div>
        </div>
    {/if}
</div>
