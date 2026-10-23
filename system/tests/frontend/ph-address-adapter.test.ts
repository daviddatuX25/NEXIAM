import { describe, expect, it } from 'vitest';
import {
  formatAddressLine,
  PhAddressAdapter,
} from '../../resources/js/lib/address/ph-address-adapter';

describe('PhAddressAdapter', () => {
  const adapter = new PhAddressAdapter();

  it('resolves all official Philippine regions', async () => {
    const regions = await adapter.getRegions();
    expect(regions.length).toBeGreaterThanOrEqual(17);

    const region1 = regions.find((r) => r.code === '0100000000');
    expect(region1).toBeDefined();
    expect(region1?.name).toContain('Region I');

    const ncr = regions.find((r) => r.code === '1300000000');
    expect(ncr).toBeDefined();
    expect(ncr?.name).toContain('National Capital Region');
  });

  it('resolves provinces for a region', async () => {
    const provinces = await adapter.getProvinces('0100000000');
    expect(provinces.length).toBeGreaterThanOrEqual(4);

    const ilocosSur = provinces.find((p) => p.name === 'Ilocos Sur');
    expect(ilocosSur).toBeDefined();
    expect(ilocosSur?.code).toBe('0102900000');
  });

  it('handles NCR by providing Metro Manila as virtual province', async () => {
    const provinces = await adapter.getProvinces('1300000000');
    expect(provinces).toHaveLength(1);
    expect(provinces[0].name).toBe('Metro Manila');
    expect(provinces[0].code).toBe('1300000000');
  });

  it('resolves municipalities and cities for a province', async () => {
    const muns = await adapter.getCities('0102900000'); // Ilocos Sur
    expect(muns.length).toBeGreaterThanOrEqual(30);

    const tagudin = muns.find((m) => m.name === 'Tagudin');
    expect(tagudin).toBeDefined();
    expect(tagudin?.code).toBe('0102933000');
  });

  it('resolves cities for NCR', async () => {
    const ncrCities = await adapter.getCities('1300000000');
    expect(ncrCities.length).toBeGreaterThanOrEqual(16);

    const manila = ncrCities.find((c) => c.name.includes('Manila') || c.name === 'City of Manila');
    expect(manila).toBeDefined();
  });

  it('resolves barangays for a municipality', async () => {
    const barangays = await adapter.getBarangays('0102933000'); // Tagudin
    expect(barangays.length).toBeGreaterThanOrEqual(40);

    const names = barangays.map((b) => b.name);
    expect(names).toContain('Bitalag');
    expect(names).toContain('Ambalayat');
  });

  it('resolves postal codes for a municipality', async () => {
    const zip = await adapter.getZipCode('0102933000'); // Tagudin
    expect(zip).toBe('2714');
  });

  it('formats address_line combining street and barangay', () => {
    expect(formatAddressLine('123 Rizal Street', 'Poblacion')).toBe('123 Rizal Street, Brgy. Poblacion');
    expect(formatAddressLine('', 'Poblacion')).toBe('Brgy. Poblacion');
    expect(formatAddressLine('Purok 4, Brgy. Ambalayat', 'Ambalayat')).toBe('Purok 4, Brgy. Ambalayat');
    expect(formatAddressLine('Unit 2B, Sunshine Bldg', '')).toBe('Unit 2B, Sunshine Bldg');
    expect(formatAddressLine('', '')).toBe('');
  });
});
