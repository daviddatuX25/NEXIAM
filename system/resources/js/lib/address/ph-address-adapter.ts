import type * as PhAddressModule from '@aivangogh/ph-address';
import type { AddressItem, AddressProvider } from './types';

export class PhAddressAdapter implements AddressProvider {
  private static libPromise: Promise<typeof PhAddressModule> | null = null;
  private lib: typeof PhAddressModule | null = null;

  private async load(): Promise<typeof PhAddressModule> {
    if (this.lib) {
      return this.lib;
    }
    if (!PhAddressAdapter.libPromise) {
      PhAddressAdapter.libPromise = import('@aivangogh/ph-address');
    }
    this.lib = await PhAddressAdapter.libPromise;
    return this.lib;
  }

  async getRegions(): Promise<AddressItem[]> {
    const lib = await this.load();
    const regions = lib.getAllRegions();
    return regions.map((r) => {
      const displayName = r.designation ? `${r.name} (${r.designation})` : r.name;
      return {
        code: r.psgcCode,
        name: displayName,
        designation: r.designation,
      };
    });
  }

  async getProvinces(regionCode: string): Promise<AddressItem[]> {
    if (!regionCode) return [];
    const lib = await this.load();

    // NCR has no provinces in standard administrative breakdown. Provide Metro Manila virtual province.
    if (regionCode === '1300000000') {
      return [
        {
          code: '1300000000',
          name: 'Metro Manila',
          designation: 'NCR',
        },
      ];
    }

    const provinces = lib.getProvincesByRegion(regionCode);
    return provinces.map((p) => ({
      code: p.psgcCode,
      name: p.name,
    }));
  }

  async getCities(provinceOrRegionCode: string): Promise<AddressItem[]> {
    if (!provinceOrRegionCode) return [];
    const lib = await this.load();

    // NCR special case
    if (provinceOrRegionCode === '1300000000') {
      const allMuns = lib.getAllMunicipalities();
      return allMuns
        .filter((m) => m.psgcCode.startsWith('13'))
        .sort((a, b) => a.name.localeCompare(b.name))
        .map((m) => ({
          code: m.psgcCode,
          name: m.name,
        }));
    }

    const muns = lib.getMunicipalitiesByProvince(provinceOrRegionCode);
    if (muns.length > 0) {
      return muns.map((m) => ({
        code: m.psgcCode,
        name: m.name,
      }));
    }

    // Fallback if code belongs to an independent city or region prefix
    const prefix = provinceOrRegionCode.substring(0, 4);
    const allMuns = lib.getAllMunicipalities();
    return allMuns
      .filter((m) => m.psgcCode.startsWith(prefix) || m.provinceCode === provinceOrRegionCode)
      .sort((a, b) => a.name.localeCompare(b.name))
      .map((m) => ({
        code: m.psgcCode,
        name: m.name,
      }));
  }

  async getBarangays(cityCode: string): Promise<AddressItem[]> {
    if (!cityCode) return [];
    const lib = await this.load();
    const barangays = lib.getBarangaysByMunicipality(cityCode);
    return barangays.map((b) => ({
      code: b.psgcCode,
      name: b.name,
    }));
  }

  async getZipCode(cityCode: string): Promise<string | null> {
    if (!cityCode) return null;
    const lib = await this.load();
    const codes = lib.getPostalCodesByMunicipality(cityCode);
    if (codes && codes.length > 0) {
      return codes[0].postalCode;
    }
    return null;
  }
}

/**
 * Format address_line combining street and barangay.
 * Example: "123 Rizal St., Brgy. Poblacion" or "Brgy. Poblacion"
 */
export function formatAddressLine(street?: string | null, barangayName?: string | null): string {
  const cleanStreet = (street ?? '').trim();
  const cleanBarangay = (barangayName ?? '').trim();

  if (cleanStreet && cleanBarangay) {
    // If street already mentions "Brgy" or "Barangay", don't duplicate
    if (/brgy\.?|barangay/i.test(cleanStreet)) {
      return cleanStreet;
    }
    return `${cleanStreet}, Brgy. ${cleanBarangay}`;
  }

  if (cleanBarangay) {
    return `Brgy. ${cleanBarangay}`;
  }

  return cleanStreet;
}

export const defaultAddressProvider = new PhAddressAdapter();
