export interface AddressItem {
  code: string;
  name: string;
  designation?: string;
}

export interface AddressProvider {
  getRegions(): Promise<AddressItem[]>;
  getProvinces(regionCode: string): Promise<AddressItem[]>;
  getCities(provinceOrRegionCode: string): Promise<AddressItem[]>;
  getBarangays(cityCode: string): Promise<AddressItem[]>;
  getZipCode(cityCode: string): Promise<string | null>;
}

export interface AddressFormValues {
  region_code: string;
  region_name: string;
  province_code: string;
  province_name: string;
  city_code: string;
  city_name: string;
  barangay_code: string;
  barangay_name: string;
  street: string;
  zip_code: string;
}

export interface LegacyAddressPayload {
  address_line: string;
  city: string;
  province: string;
  zip_code: string;
}
