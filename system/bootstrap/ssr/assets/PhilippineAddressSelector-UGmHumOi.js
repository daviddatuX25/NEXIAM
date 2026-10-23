import { l as bind_props, e as escape_html, s as stringify, a as ensure_array_like, d as derived } from "../app.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { L as Label } from "./label-dGY6L9vm.js";
class PhAddressAdapter {
  static libPromise = null;
  lib = null;
  async load() {
    if (this.lib) {
      return this.lib;
    }
    if (!PhAddressAdapter.libPromise) {
      PhAddressAdapter.libPromise = import("@aivangogh/ph-address");
    }
    this.lib = await PhAddressAdapter.libPromise;
    return this.lib;
  }
  async getRegions() {
    const lib = await this.load();
    const regions = lib.getAllRegions();
    return regions.map((r) => {
      const displayName = r.designation ? `${r.name} (${r.designation})` : r.name;
      return {
        code: r.psgcCode,
        name: displayName,
        designation: r.designation
      };
    });
  }
  async getProvinces(regionCode) {
    if (!regionCode) return [];
    const lib = await this.load();
    if (regionCode === "1300000000") {
      return [
        {
          code: "1300000000",
          name: "Metro Manila",
          designation: "NCR"
        }
      ];
    }
    const provinces = lib.getProvincesByRegion(regionCode);
    return provinces.map((p) => ({
      code: p.psgcCode,
      name: p.name
    }));
  }
  async getCities(provinceOrRegionCode) {
    if (!provinceOrRegionCode) return [];
    const lib = await this.load();
    if (provinceOrRegionCode === "1300000000") {
      const allMuns2 = lib.getAllMunicipalities();
      return allMuns2.filter((m) => m.psgcCode.startsWith("13")).sort((a, b) => a.name.localeCompare(b.name)).map((m) => ({
        code: m.psgcCode,
        name: m.name
      }));
    }
    const muns = lib.getMunicipalitiesByProvince(provinceOrRegionCode);
    if (muns.length > 0) {
      return muns.map((m) => ({
        code: m.psgcCode,
        name: m.name
      }));
    }
    const prefix = provinceOrRegionCode.substring(0, 4);
    const allMuns = lib.getAllMunicipalities();
    return allMuns.filter((m) => m.psgcCode.startsWith(prefix) || m.provinceCode === provinceOrRegionCode).sort((a, b) => a.name.localeCompare(b.name)).map((m) => ({
      code: m.psgcCode,
      name: m.name
    }));
  }
  async getBarangays(cityCode) {
    if (!cityCode) return [];
    const lib = await this.load();
    const barangays = lib.getBarangaysByMunicipality(cityCode);
    return barangays.map((b) => ({
      code: b.psgcCode,
      name: b.name
    }));
  }
  async getZipCode(cityCode) {
    if (!cityCode) return null;
    const lib = await this.load();
    const codes = lib.getPostalCodesByMunicipality(cityCode);
    if (codes && codes.length > 0) {
      return codes[0].postalCode;
    }
    return null;
  }
}
function formatAddressLine(street, barangayName) {
  const cleanStreet = (street ?? "").trim();
  const cleanBarangay = (barangayName ?? "").trim();
  if (cleanStreet && cleanBarangay) {
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
const defaultAddressProvider = new PhAddressAdapter();
function PhilippineAddressSelector($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      address_line = "",
      city = "",
      province = "",
      zip_code = "",
      provider = defaultAddressProvider,
      idPrefix = "ph-addr",
      disabled = false,
      required = false,
      errors = {},
      onChange
    } = $$props;
    let regions = [];
    let provinces = [];
    let cities = [];
    let barangays = [];
    let selectedRegionCode = "";
    let selectedProvinceCode = "";
    let selectedCityCode = "";
    let selectedBarangayCode = "";
    let street = "";
    let isNCR = derived(() => selectedRegionCode === "1300000000");
    function syncPayload(barangayName = "") {
      const currentBarangay = barangayName || barangays.find((b) => b.code === selectedBarangayCode)?.name || "";
      address_line = formatAddressLine(street, currentBarangay);
      if (onChange) {
        onChange({ address_line, city, province, zip_code });
      }
    }
    async function handleRegionChange(e) {
      const code = e.target.value;
      selectedRegionCode = code;
      selectedProvinceCode = "";
      selectedCityCode = "";
      selectedBarangayCode = "";
      provinces = [];
      cities = [];
      barangays = [];
      city = "";
      if (!code) {
        province = "";
        syncPayload();
        return;
      }
      try {
        if (code === "1300000000") {
          province = "Metro Manila";
          selectedProvinceCode = "1300000000";
          cities = await provider.getCities("1300000000");
        } else {
          province = "";
          provinces = await provider.getProvinces(code);
        }
      } finally {
        syncPayload();
      }
    }
    async function handleProvinceChange(e) {
      const code = e.target.value;
      selectedProvinceCode = code;
      selectedCityCode = "";
      selectedBarangayCode = "";
      cities = [];
      barangays = [];
      city = "";
      if (!code) {
        province = "";
        syncPayload();
        return;
      }
      const foundProv = provinces.find((p) => p.code === code);
      province = foundProv?.name ?? "";
      try {
        cities = await provider.getCities(code);
      } finally {
        syncPayload();
      }
    }
    async function handleCityChange(e) {
      const code = e.target.value;
      selectedCityCode = code;
      selectedBarangayCode = "";
      barangays = [];
      if (!code) {
        city = "";
        syncPayload();
        return;
      }
      const foundCity = cities.find((c) => c.code === code);
      city = foundCity?.name ?? "";
      try {
        const [fetchedBarangays, zip] = await Promise.all([provider.getBarangays(code), provider.getZipCode(code)]);
        barangays = fetchedBarangays;
        if (zip && !zip_code) {
          zip_code = zip;
        }
      } finally {
        syncPayload();
      }
    }
    function handleBarangayChange(e) {
      const code = e.target.value;
      selectedBarangayCode = code;
      const foundBarangay = barangays.find((b) => b.code === code);
      syncPayload(foundBarangay?.name ?? "");
    }
    function handleStreetInput(e) {
      street = e.target.value;
      syncPayload();
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<div class="space-y-4"><div class="flex items-center justify-between"><span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">${escape_html("Philippine Standard Address (PSGC)")}</span> <button type="button" class="text-xs text-primary hover:underline focus:outline-none">${escape_html("Enter address manually")}</button></div> `);
      {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<div class="grid gap-4 sm:grid-cols-2"><div class="space-y-2">`);
        Label($$renderer3, {
          for: `${stringify(idPrefix)}-region`,
          class: "",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Region`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        $$renderer3.select(
          {
            id: `${stringify(idPrefix)}-region`,
            value: selectedRegionCode,
            onchange: handleRegionChange,
            disabled,
            class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "" }, ($$renderer5) => {
              $$renderer5.push(`Select Region`);
            });
            $$renderer4.push(`<!--[-->`);
            const each_array = ensure_array_like(regions);
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let r = each_array[$$index];
              $$renderer4.option({ value: r.code }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(r.name)}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(`</div> <div class="space-y-2">`);
        Label($$renderer3, {
          for: `${stringify(idPrefix)}-province`,
          class: "",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Province`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        if (isNCR()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="border-input bg-muted text-muted-foreground flex min-h-11 w-full items-center rounded-md border px-3 text-sm">Metro Manila (NCR)</div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.select(
            {
              id: `${stringify(idPrefix)}-province`,
              value: selectedProvinceCode,
              onchange: handleProvinceChange,
              disabled: disabled || !selectedRegionCode || provinces.length === 0,
              class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
            },
            ($$renderer4) => {
              $$renderer4.option({ value: "" }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(selectedRegionCode ? "Select Province" : "Select Region first")}`);
              });
              $$renderer4.push(`<!--[-->`);
              const each_array_1 = ensure_array_like(provinces);
              for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                let p = each_array_1[$$index_1];
                $$renderer4.option({ value: p.code }, ($$renderer5) => {
                  $$renderer5.push(`${escape_html(p.name)}`);
                });
              }
              $$renderer4.push(`<!--]-->`);
            }
          );
        }
        $$renderer3.push(`<!--]--> `);
        if (errors.province) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-xs text-destructive">${escape_html(errors.province)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <div class="space-y-2">`);
        Label($$renderer3, {
          for: `${stringify(idPrefix)}-city`,
          class: "",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->City / Municipality`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        $$renderer3.select(
          {
            id: `${stringify(idPrefix)}-city`,
            value: selectedCityCode,
            onchange: handleCityChange,
            disabled: disabled || !selectedProvinceCode && !isNCR() || cities.length === 0,
            class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "" }, ($$renderer5) => {
              $$renderer5.push(`${escape_html(selectedProvinceCode || isNCR() ? "Select City / Municipality" : "Select Province first")}`);
            });
            $$renderer4.push(`<!--[-->`);
            const each_array_2 = ensure_array_like(cities);
            for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
              let c = each_array_2[$$index_2];
              $$renderer4.option({ value: c.code }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(c.name)}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(` `);
        if (errors.city) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-xs text-destructive">${escape_html(errors.city)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <div class="space-y-2">`);
        Label($$renderer3, {
          for: `${stringify(idPrefix)}-barangay`,
          class: "",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->Barangay`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        $$renderer3.select(
          {
            id: `${stringify(idPrefix)}-barangay`,
            value: selectedBarangayCode,
            onchange: handleBarangayChange,
            disabled: disabled || !selectedCityCode || barangays.length === 0,
            class: "border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
          },
          ($$renderer4) => {
            $$renderer4.option({ value: "" }, ($$renderer5) => {
              $$renderer5.push(`${escape_html(selectedCityCode ? "Select Barangay" : "Select City first")}`);
            });
            $$renderer4.push(`<!--[-->`);
            const each_array_3 = ensure_array_like(barangays);
            for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
              let b = each_array_3[$$index_3];
              $$renderer4.option({ value: b.code }, ($$renderer5) => {
                $$renderer5.push(`${escape_html(b.name)}`);
              });
            }
            $$renderer4.push(`<!--]-->`);
          }
        );
        $$renderer3.push(`</div> <div class="space-y-2">`);
        Label($$renderer3, {
          for: `${stringify(idPrefix)}-street`,
          class: "",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->House No. / Street / Purok / Sitio <span class="text-xs font-normal text-muted-foreground">(optional)</span>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Input($$renderer3, {
          id: `${stringify(idPrefix)}-street`,
          type: "text",
          value: street,
          oninput: handleStreetInput,
          disabled,
          placeholder: "e.g. Purok 3, 45 Mabini St.",
          class: "min-h-11"
        });
        $$renderer3.push(`<!----> `);
        if (errors.address_line) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-xs text-destructive">${escape_html(errors.address_line)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <div class="space-y-2">`);
        Label($$renderer3, {
          for: `${stringify(idPrefix)}-zip`,
          class: "",
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->ZIP Code <span class="text-xs font-normal text-muted-foreground">(auto-filled)</span>`);
          },
          $$slots: { default: true }
        });
        $$renderer3.push(`<!----> `);
        Input($$renderer3, {
          id: `${stringify(idPrefix)}-zip`,
          type: "text",
          disabled,
          placeholder: "e.g. 2714",
          class: "min-h-11",
          get value() {
            return zip_code;
          },
          set value($$value) {
            zip_code = $$value;
            $$settled = false;
          }
        });
        $$renderer3.push(`<!----> `);
        if (errors.zip_code) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-xs text-destructive">${escape_html(errors.zip_code)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></div>`);
      }
      $$renderer3.push(`<!--]--></div>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { address_line, city, province, zip_code });
  });
}
export {
  PhilippineAddressSelector as P
};
