// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

afterEach(() => {
    cleanup();
});
import PhilippineAddressSelector from '../../resources/js/Components/PhilippineAddressSelector.svelte';
import type {
    AddressItem,
    AddressProvider,
} from '../../resources/js/lib/address/types';

class MockAddressProvider implements AddressProvider {
    async getRegions(): Promise<AddressItem[]> {
        return [
            { code: '0100000000', name: 'Region I (Ilocos Region)' },
            { code: '1300000000', name: 'National Capital Region (NCR)' },
        ];
    }

    async getProvinces(regionCode: string): Promise<AddressItem[]> {
        if (regionCode === '0100000000') {
            return [{ code: '0102900000', name: 'Ilocos Sur' }];
        }
        if (regionCode === '1300000000') {
            return [{ code: '1300000000', name: 'Metro Manila' }];
        }
        return [];
    }

    async getCities(provinceOrRegionCode: string): Promise<AddressItem[]> {
        if (provinceOrRegionCode === '0102900000') {
            return [{ code: '0102933000', name: 'Tagudin' }];
        }
        if (provinceOrRegionCode === '1300000000') {
            return [{ code: '1380600000', name: 'City of Manila' }];
        }
        return [];
    }

    async getBarangays(cityCode: string): Promise<AddressItem[]> {
        if (cityCode === '0102933000') {
            return [
                { code: '0102933001', name: 'Ambalayat' },
                { code: '0102933002', name: 'Bitalag' },
            ];
        }
        return [];
    }

    async getZipCode(cityCode: string): Promise<string | null> {
        if (cityCode === '0102933000') return '2714';
        if (cityCode === '1380600000') return '1000';
        return null;
    }
}

class FailingAddressProvider extends MockAddressProvider {
    async getRegions(): Promise<AddressItem[]> {
        throw new Error('PSGC data unavailable');
    }
}

describe('PhilippineAddressSelector Component', () => {
    it('renders and cascades from Region to Barangay with ZIP auto-fill', async () => {
        const provider = new MockAddressProvider();
        const onChange = vi.fn();

        const { container } = render(PhilippineAddressSelector, {
            provider,
            idPrefix: 'test-addr',
            onChange,
        });

        const regionSelect = container.querySelector(
            '#test-addr-region',
        ) as HTMLSelectElement;
        expect(regionSelect).not.toBeNull();

        // 1. Wait for regions to load
        await waitFor(() => {
            expect(regionSelect.options.length).toBe(3); // placeholder + 2 regions
        });

        // 2. Select Region I
        await fireEvent.change(regionSelect, {
            target: { value: '0100000000' },
        });

        const provinceSelect = container.querySelector(
            '#test-addr-province',
        ) as HTMLSelectElement;
        await waitFor(() => {
            expect(provinceSelect.disabled).toBe(false);
            expect(provinceSelect.options.length).toBe(2); // placeholder + Ilocos Sur
        });

        // 3. Select Ilocos Sur
        await fireEvent.change(provinceSelect, {
            target: { value: '0102900000' },
        });

        const citySelect = container.querySelector(
            '#test-addr-city',
        ) as HTMLSelectElement;
        await waitFor(() => {
            expect(citySelect.disabled).toBe(false);
            expect(citySelect.options.length).toBe(2); // placeholder + Tagudin
        });

        // 4. Select Tagudin
        await fireEvent.change(citySelect, { target: { value: '0102933000' } });

        const barangaySelect = container.querySelector(
            '#test-addr-barangay',
        ) as HTMLSelectElement;
        const zipInput = container.querySelector(
            '#test-addr-zip',
        ) as HTMLInputElement;

        await waitFor(() => {
            expect(barangaySelect.disabled).toBe(false);
            expect(barangaySelect.options.length).toBe(3); // placeholder + 2 barangays
            expect(zipInput.value).toBe('2714');
        });

        // 5. Select Ambalayat
        await fireEvent.change(barangaySelect, {
            target: { value: '0102933001' },
        });

        // 6. Enter Street address
        const streetInput = container.querySelector(
            '#test-addr-street',
        ) as HTMLInputElement;
        await fireEvent.input(streetInput, {
            target: { value: 'Purok 4, Mabini Street' },
        });

        expect(onChange).toHaveBeenLastCalledWith({
            address_line: 'Purok 4, Mabini Street, Brgy. Ambalayat',
            city: 'Tagudin',
            province: 'Ilocos Sur',
            zip_code: '2714',
        });
    });

    it('bypasses province dropdown when NCR is selected', async () => {
        const provider = new MockAddressProvider();
        const onChange = vi.fn();

        const { container, getByText } = render(PhilippineAddressSelector, {
            provider,
            idPrefix: 'ncr-addr',
            onChange,
        });

        const regionSelect = container.querySelector(
            '#ncr-addr-region',
        ) as HTMLSelectElement;
        await waitFor(() => expect(regionSelect.options.length).toBe(3));

        // Select NCR
        await fireEvent.change(regionSelect, {
            target: { value: '1300000000' },
        });

        // Province select should be replaced by NCR badge/text
        await waitFor(() => {
            expect(getByText('Metro Manila (NCR)')).not.toBeNull();
        });

        const citySelect = container.querySelector(
            '#ncr-addr-city',
        ) as HTMLSelectElement;
        await waitFor(() => {
            expect(citySelect.disabled).toBe(false);
            expect(citySelect.options.length).toBe(2); // placeholder + City of Manila
        });

        // Select City of Manila
        await fireEvent.change(citySelect, { target: { value: '1380600000' } });

        const zipInput = container.querySelector(
            '#ncr-addr-zip',
        ) as HTMLInputElement;
        await waitFor(() => {
            expect(zipInput.value).toBe('1000');
        });

        expect(onChange).toHaveBeenLastCalledWith(
            expect.objectContaining({
                province: 'Metro Manila',
                city: 'City of Manila',
                zip_code: '1000',
            }),
        );
    });

    it('supports toggling manual mode for fallback text inputs', async () => {
        const provider = new MockAddressProvider();
        const { container, getByRole, getByText } = render(
            PhilippineAddressSelector,
            {
                provider,
                idPrefix: 'manual-addr',
            },
        );

        const toggleBtn = getByRole('button', {
            name: /enter address manually/i,
        });
        await fireEvent.click(toggleBtn);

        expect(getByText('Manual Address Entry')).not.toBeNull();
        expect(
            container.querySelector('#manual-addr-manual-address'),
        ).not.toBeNull();
        expect(
            container.querySelector('#manual-addr-manual-city'),
        ).not.toBeNull();
        expect(
            container.querySelector('#manual-addr-manual-province'),
        ).not.toBeNull();
        expect(
            container.querySelector('#manual-addr-manual-zip'),
        ).not.toBeNull();
    });
    it('hides manual entry when PSGC-only mode is requested', async () => {
        const { container, queryByRole } = render(PhilippineAddressSelector, {
            provider: new MockAddressProvider(),
            idPrefix: 'psgc-only',
            allowManual: false,
        });

        await waitFor(() => {
            expect(container.querySelector('#psgc-only-region')).not.toBeNull();
        });

        expect(
            queryByRole('button', { name: /enter address manually/i }),
        ).toBeNull();
    });

    it('shows a non-blocking PSGC unavailable message without enabling manual mode', async () => {
        const consoleError = vi
            .spyOn(console, 'error')
            .mockImplementation(() => {});
        const { container, getByRole, queryByRole } = render(
            PhilippineAddressSelector,
            {
                provider: new FailingAddressProvider(),
                idPrefix: 'psgc-failed',
                allowManual: false,
            },
        );

        await waitFor(() => {
            expect(getByRole('alert').textContent).toContain(
                'Philippine address choices are temporarily unavailable.',
            );
        });

        expect(
            queryByRole('button', { name: /enter address manually/i }),
        ).toBeNull();
        expect(container.querySelector('#psgc-failed-region')).not.toBeNull();
        consoleError.mockRestore();
    });
});
