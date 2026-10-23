import { g as sanitize_props, i as spread_props, j as slot, o as onDestroy, c as attr_class, b as attr, f as attr_style, e as escape_html, d as derived, a as ensure_array_like, l as bind_props, r as run } from "../app.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { Z as Zoom_out, a as Zoom_in } from "./zoom-out-DsJCISKt.js";
import { M as Maximize_2, I as Image } from "./maximize-2-u72Ahym6.js";
import { L as Loader_circle } from "./loader-circle-_UEl10Lx.js";
import { C as Circle_x } from "./circle-x-BQrhluGu.js";
import { R as Refresh_cw } from "./refresh-cw-CYdk8aE0.js";
import { E as Eye_off } from "./eye-off-Bx_raPQb.js";
import { S as Save } from "./save-DAlMQk-Q.js";
import { S as Scan_line } from "./scan-line-CiwhUtYK.js";
import { L as Layers } from "./layers-_Ie6y7G_.js";
import { C as Chevron_down } from "./chevron-down-CkQQZIhu.js";
import { C as Chevron_up } from "./chevron-up-lhASJLRD.js";
import { X } from "./x-CnxrMbMn.js";
import { C as Check } from "./check-DK45Y9SJ.js";
import { C as Circle_alert } from "./circle-alert-jWXJ7oC0.js";
function Circle_dot_dashed($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M10.1 2.18a9.93 9.93 0 0 1 3.8 0" }],
    ["path", { "d": "M17.6 3.71a9.95 9.95 0 0 1 2.69 2.7" }],
    ["path", { "d": "M21.82 10.1a9.93 9.93 0 0 1 0 3.8" }],
    ["path", { "d": "M20.29 17.6a9.95 9.95 0 0 1-2.7 2.69" }],
    ["path", { "d": "M13.9 21.82a9.94 9.94 0 0 1-3.8 0" }],
    ["path", { "d": "M6.4 20.29a9.95 9.95 0 0 1-2.69-2.7" }],
    ["path", { "d": "M2.18 13.9a9.93 9.93 0 0 1 0-3.8" }],
    ["path", { "d": "M3.71 6.4a9.95 9.95 0 0 1 2.7-2.69" }],
    ["circle", { "cx": "12", "cy": "12", "r": "1" }]
  ];
  Icon($$renderer, spread_props([
    { name: "circle-dot-dashed" },
    $$sanitized_props,
    {
      /**
       * @component @name CircleDotDashed
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTAuMSAyLjE4YTkuOTMgOS45MyAwIDAgMSAzLjggMCIgLz4KICA8cGF0aCBkPSJNMTcuNiAzLjcxYTkuOTUgOS45NSAwIDAgMSAyLjY5IDIuNyIgLz4KICA8cGF0aCBkPSJNMjEuODIgMTAuMWE5LjkzIDkuOTMgMCAwIDEgMCAzLjgiIC8+CiAgPHBhdGggZD0iTTIwLjI5IDE3LjZhOS45NSA5Ljk1IDAgMCAxLTIuNyAyLjY5IiAvPgogIDxwYXRoIGQ9Ik0xMy45IDIxLjgyYTkuOTQgOS45NCAwIDAgMS0zLjggMCIgLz4KICA8cGF0aCBkPSJNNi40IDIwLjI5YTkuOTUgOS45NSAwIDAgMS0yLjY5LTIuNyIgLz4KICA8cGF0aCBkPSJNMi4xOCAxMy45YTkuOTMgOS45MyAwIDAgMSAwLTMuOCIgLz4KICA8cGF0aCBkPSJNMy43MSA2LjRhOS45NSA5Ljk1IDAgMCAxIDIuNy0yLjY5IiAvPgogIDxjaXJjbGUgY3g9IjEyIiBjeT0iMTIiIHI9IjEiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/circle-dot-dashed
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function Lock_keyhole($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["circle", { "cx": "12", "cy": "16", "r": "1" }],
    [
      "rect",
      {
        "x": "3",
        "y": "10",
        "width": "18",
        "height": "12",
        "rx": "2"
      }
    ],
    ["path", { "d": "M7 10V7a5 5 0 0 1 10 0v3" }]
  ];
  Icon($$renderer, spread_props([
    { name: "lock-keyhole" },
    $$sanitized_props,
    {
      /**
       * @component @name LockKeyhole
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8Y2lyY2xlIGN4PSIxMiIgY3k9IjE2IiByPSIxIiAvPgogIDxyZWN0IHg9IjMiIHk9IjEwIiB3aWR0aD0iMTgiIGhlaWdodD0iMTIiIHJ4PSIyIiAvPgogIDxwYXRoIGQ9Ik03IDEwVjdhNSA1IDAgMCAxIDEwIDB2MyIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/lock-keyhole
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
function Move($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["path", { "d": "M12 2v20" }],
    ["path", { "d": "m15 19-3 3-3-3" }],
    ["path", { "d": "m19 9 3 3-3 3" }],
    ["path", { "d": "M2 12h20" }],
    ["path", { "d": "m5 9-3 3 3 3" }],
    ["path", { "d": "m9 5 3-3 3 3" }]
  ];
  Icon($$renderer, spread_props([
    { name: "move" },
    $$sanitized_props,
    {
      /**
       * @component @name Move
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8cGF0aCBkPSJNMTIgMnYyMCIgLz4KICA8cGF0aCBkPSJtMTUgMTktMyAzLTMtMyIgLz4KICA8cGF0aCBkPSJtMTkgOSAzIDMtMyAzIiAvPgogIDxwYXRoIGQ9Ik0yIDEyaDIwIiAvPgogIDxwYXRoIGQ9Im01IDktMyAzIDMgMyIgLz4KICA8cGF0aCBkPSJtOSA1IDMtMyAzIDMiIC8+Cjwvc3ZnPgo=) - https://lucide.dev/icons/move
       * @see https://lucide.dev/guide/packages/lucide-svelte - Documentation
       *
       * @param {Object} props - Lucide icons props and any valid SVG attribute
       * @returns {FunctionalComponent} Svelte component
       *
       */
      iconNode,
      children: ($$renderer2) => {
        $$renderer2.push(`<!--[-->`);
        slot($$renderer2, $$props, "default", {});
        $$renderer2.push(`<!--]-->`);
      },
      $$slots: { default: true }
    }
  ]));
}
const MACHINE_STATES = [
  "marked",
  "blank",
  "ambiguous",
  "unreadable"
];
function stringOr$1(value, fallback = null) {
  return typeof value === "string" && value.trim() !== "" ? value : fallback;
}
function numberOr$1(value, fallback = null) {
  if (typeof value === "number" && Number.isFinite(value)) return value;
  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    if (Number.isFinite(parsed)) return parsed;
  }
  return fallback;
}
function positiveNumberOr$1(value) {
  const number = numberOr$1(value, null);
  return number !== null && number > 0 ? number : null;
}
function normalizeHash$1(value) {
  const hash = stringOr$1(value, null)?.toLowerCase() ?? null;
  return hash !== null && /^[a-f0-9]{64}$/.test(hash) ? hash : null;
}
function normalizeState(value) {
  const state = stringOr$1(value, null)?.toLowerCase() ?? null;
  if (state === "match" || state === "selected" || state === "exact") return "marked";
  if (state === "multiple" || state === "multi_mark") return "ambiguous";
  return state && MACHINE_STATES.includes(state) ? state : null;
}
function normalizeCode(value) {
  const code = stringOr$1(value, null);
  return code === null ? null : code;
}
function uniqueCodes(values) {
  if (!Array.isArray(values)) return [];
  const seen = /* @__PURE__ */ new Set();
  const codes = [];
  for (const value of values) {
    const code = normalizeCode(value);
    if (code === null || seen.has(code)) continue;
    seen.add(code);
    codes.push(code);
  }
  return codes;
}
function normalizeObservation(value) {
  if (!isRecord(value)) return null;
  const code = normalizeCode(value.code ?? value.option);
  if (code === null) return null;
  return {
    code,
    mean: numberOr$1(value.mean ?? value.bubble_mean, null),
    classification: stringOr$1(value.classification ?? value.threshold_status, null)
  };
}
function normalizeAsset$1(value) {
  if (!isRecord(value)) return null;
  const dimensions = isRecord(value.dimensions) ? value.dimensions : null;
  const dimensionsArray = Array.isArray(value.dimensions) ? value.dimensions : null;
  return {
    url: stringOr$1(value.url ?? value.href, null),
    width: positiveNumberOr$1(value.width ?? dimensions?.width ?? dimensionsArray?.[0]),
    height: positiveNumberOr$1(value.height ?? dimensions?.height ?? dimensionsArray?.[1]),
    sha256: normalizeHash$1(value.sha256 ?? value.hash ?? value.asset_hash),
    page_no: numberOr$1(value.page_no ?? value.page, null),
    kind: stringOr$1(value.kind ?? value.type, null)
  };
}
function normalizeTransform(value) {
  if (!isRecord(value)) return null;
  let valid = true;
  let matrix = null;
  if ("matrix" in value && value.matrix !== null && value.matrix !== void 0) {
    if (!Array.isArray(value.matrix) || value.matrix.length !== 6) {
      valid = false;
    } else {
      const parsed = value.matrix.map((entry) => numberOr$1(entry, Number.NaN));
      if (!parsed.every((entry) => Number.isFinite(entry))) {
        valid = false;
      } else {
        const det = parsed[0] * parsed[3] - parsed[1] * parsed[2];
        if (!Number.isFinite(det) || Math.abs(det) < 1e-12) {
          valid = false;
        } else {
          matrix = parsed;
        }
      }
    }
  }
  const rawScaleX = value.scale_x ?? value.scaleX;
  const rawScaleY = value.scale_y ?? value.scaleY;
  const rawTransX = value.translate_x ?? value.translateX;
  const rawTransY = value.translate_y ?? value.translateY;
  const scaleX = rawScaleX !== void 0 && rawScaleX !== null ? numberOr$1(rawScaleX, null) : null;
  const scaleY = rawScaleY !== void 0 && rawScaleY !== null ? numberOr$1(rawScaleY, null) : null;
  const transX = rawTransX !== void 0 && rawTransX !== null ? numberOr$1(rawTransX, null) : null;
  const transY = rawTransY !== void 0 && rawTransY !== null ? numberOr$1(rawTransY, null) : null;
  if (rawScaleX !== void 0 && rawScaleX !== null && (scaleX === null || scaleX <= 0 || !Number.isFinite(scaleX))) {
    valid = false;
  }
  if (rawScaleY !== void 0 && rawScaleY !== null && (scaleY === null || scaleY <= 0 || !Number.isFinite(scaleY))) {
    valid = false;
  }
  if (rawTransX !== void 0 && rawTransX !== null && (transX === null || !Number.isFinite(transX))) {
    valid = false;
  }
  if (rawTransY !== void 0 && rawTransY !== null && (transY === null || !Number.isFinite(transY))) {
    valid = false;
  }
  return {
    source_rect: toRegion(value.source_rect),
    rect: toRegion(value.rect),
    scale_x: scaleX,
    scale_y: scaleY,
    translate_x: transX,
    translate_y: transY,
    matrix,
    valid
  };
}
function normalizeCropSource(value) {
  if (!isRecord(value)) return null;
  const rawTransform = value.transform;
  const transform = rawTransform !== void 0 && rawTransform !== null ? normalizeTransform(rawTransform) : null;
  const valid = rawTransform !== void 0 && rawTransform !== null ? isRecord(rawTransform) && transform?.valid !== false : true;
  return {
    kind: stringOr$1(value.kind, null),
    type: stringOr$1(value.type, null),
    source_rect: toRegion(value.source_rect),
    rect: toRegion(value.rect),
    aligned_rect: toRegion(value.aligned_rect),
    transform,
    valid
  };
}
function normalizeOptions(value) {
  if (!Array.isArray(value)) return [];
  const seen = /* @__PURE__ */ new Set();
  const options = [];
  for (const raw of value) {
    if (!isRecord(raw)) continue;
    const code = normalizeCode(raw.code ?? raw.option ?? raw.value);
    const region = toRegion(raw.region ?? raw.rect ?? raw.option_rect ?? raw.geometry);
    if (code === null || region === null) continue;
    if (!seen.has(code)) seen.add(code);
    options.push({ code, region });
  }
  return options;
}
function normalizeMachine(value, fallback) {
  const raw = isRecord(value) ? value : null;
  const state = normalizeState(raw?.state ?? fallback.state);
  const selected = normalizeCode(
    raw?.selected_option ?? raw?.selectedOption ?? raw?.detected_option ?? fallback.detectedOption
  );
  const observed = uniqueCodes(
    raw?.observed_options ?? raw?.observedOptions ?? raw?.candidates
  );
  const observedOptions = observed.length > 0 ? observed : state === "marked" && selected !== null ? [selected] : [];
  const rawObservations = Array.isArray(
    raw?.option_observations ?? raw?.optionObservations
  ) ? raw?.option_observations ?? raw?.optionObservations : [];
  const observations = rawObservations.map(normalizeObservation).filter((entry) => entry !== null);
  if (!raw && state === null && selected === null && observedOptions.length === 0) {
    return null;
  }
  return {
    state,
    selected_option: state === "unreadable" ? null : selected,
    observed_options: observedOptions,
    option_observations: observations,
    confidence: numberOr$1(raw?.confidence, null),
    issue_codes: uniqueCodes(raw?.issue_codes ?? raw?.issueCodes),
    evidence_hash: normalizeHash$1(raw?.evidence_hash ?? raw?.evidenceHash ?? fallback.evidence_hash)
  };
}
function normalizeExpected(value, fallback) {
  if (isRecord(value)) {
    return {
      option: normalizeCode(value.option ?? value.selected_option ?? value.code)
    };
  }
  return {
    option: normalizeCode(value ?? fallback.expectedOption)
  };
}
function normalizeReview$1(value) {
  if (!isRecord(value)) return null;
  const decision = stringOr$1(value.decision, null);
  if (decision === null) return null;
  const reviewer = isRecord(value.reviewer) ? {
    id: value.reviewer.id,
    display_name: stringOr$1(value.reviewer.display_name ?? value.reviewer.name, null)
  } : null;
  return {
    revision: numberOr$1(value.revision, null),
    decision,
    selected_option: normalizeCode(value.selected_option ?? value.selectedOption),
    reason_code: stringOr$1(value.reason_code ?? value.reasonCode, null),
    note: stringOr$1(value.note, null),
    reviewer,
    reviewed_at: stringOr$1(value.reviewed_at ?? value.reviewedAt, null),
    evidence_hash: normalizeHash$1(value.evidence_hash ?? value.evidenceHash)
  };
}
function normalizeGeometry(value, fallback) {
  const raw = isRecord(value) ? value : {};
  const itemRect = toRegion(
    raw.item_rect ?? raw.itemRect ?? raw.crop_rect ?? raw.cropRect ?? raw.region ?? fallback.crop_rect ?? fallback.region
  );
  const rawOptions = raw.options ?? raw.option_rects ?? raw.optionRects ?? fallback.options;
  const cropSource = normalizeCropSource(raw.crop_source ?? raw.cropSource ?? fallback.crop_source);
  const alignedAsset = normalizeAsset$1(
    raw.aligned_asset ?? raw.alignedAsset ?? raw.aligned ?? fallback.page_asset ?? fallback.asset
  );
  const cropAsset = normalizeAsset$1(raw.crop_asset ?? raw.cropAsset);
  const sourceRect = toRegion(
    cropSource?.source_rect ?? cropSource?.rect ?? cropSource?.aligned_rect ?? cropSource?.transform?.source_rect ?? cropSource?.transform?.rect
  );
  const normalizedCropSource = cropSource ? { ...cropSource, source_rect: sourceRect } : null;
  return {
    item_rect: itemRect,
    crop_rect: toRegion(raw.crop_rect ?? raw.cropRect ?? itemRect),
    options: normalizeOptions(rawOptions),
    aligned_asset: alignedAsset,
    crop_asset: cropAsset,
    crop_source: normalizedCropSource,
    geometry_manifest_hash: normalizeHash$1(
      raw.geometry_manifest_hash ?? raw.geometryManifestHash ?? raw.geometry_hash ?? raw.geometryHash ?? raw.manifest_hash
    ),
    evidence_version: stringOr$1(raw.evidence_version ?? raw.evidenceVersion ?? fallback.evidence_version, null),
    page_no: numberOr$1(raw.page_no ?? raw.page ?? fallback.page_no, null)
  };
}
function normalizeOverlayInput(value) {
  const root = isRecord(value) ? value : {};
  const item = isRecord(root.item) ? root.item : root;
  const machineValue = root.machine ?? item.machine;
  const expectedValue = root.expected ?? item.expected;
  const geometryValue = root.geometry ?? item.geometry;
  const machine = normalizeMachine(machineValue, {
    ...root,
    state: root.state ?? item.state,
    detectedOption: root.detectedOption ?? item.detected_option,
    evidence_hash: root.evidence_hash ?? item.evidence_hash
  });
  const expected = normalizeExpected(expectedValue, {
    ...root,
    expectedOption: root.expectedOption ?? item.expected_option
  });
  const geometry = normalizeGeometry(geometryValue, {
    ...root,
    region: root.region ?? item.region,
    crop_rect: root.crop_rect ?? item.crop_rect,
    options: root.options ?? item.options,
    asset: root.asset ?? item.asset,
    page_asset: root.page_asset ?? item.page_asset,
    crop_source: root.crop_source ?? item.crop_source,
    evidence_version: root.evidence_version ?? item.evidence_version,
    page_no: root.page_no ?? item.page_no
  });
  const review = normalizeReview$1(root.review ?? item.review);
  const cropUrl = stringOr$1(root.cropUrl ?? root.crop_url ?? item.cropUrl ?? item.crop_url, null);
  const imageUrl = stringOr$1(root.imageUrl ?? root.image_url ?? item.imageUrl ?? item.image_url, null);
  const isDedicated = isDedicatedCropSource(geometry.crop_source, geometry.crop_asset, cropUrl);
  const sourceUrl = isDedicated ? cropUrl ?? geometry.crop_asset?.url ?? imageUrl ?? geometry.aligned_asset?.url ?? null : imageUrl ?? geometry.aligned_asset?.url ?? cropUrl ?? geometry.crop_asset?.url ?? null;
  return {
    machine,
    expected,
    geometry,
    review,
    source_url: sourceUrl,
    image_url: imageUrl,
    crop_url: cropUrl,
    legacy: machineValue == null && geometryValue == null
  };
}
function transformRegionToViewport(region, viewport) {
  if (!isRegion(region) || !isRegion(viewport)) return null;
  return {
    x: (region.x - viewport.x) / viewport.w,
    y: (region.y - viewport.y) / viewport.h,
    w: region.w / viewport.w,
    h: region.h / viewport.h
  };
}
function transformPointWithMatrix(x, y, matrix) {
  return {
    x: matrix[0] * x + matrix[2] * y + matrix[4],
    y: matrix[1] * x + matrix[3] * y + matrix[5]
  };
}
function applySourceTransform(region, transform) {
  if (!transform) return region;
  if (transform.valid === false) return null;
  if (transform.matrix) {
    const corners = [
      transformPointWithMatrix(region.x, region.y, transform.matrix),
      transformPointWithMatrix(region.x + region.w, region.y, transform.matrix),
      transformPointWithMatrix(region.x, region.y + region.h, transform.matrix),
      transformPointWithMatrix(region.x + region.w, region.y + region.h, transform.matrix)
    ];
    const xs = corners.map((corner) => corner.x);
    const ys = corners.map((corner) => corner.y);
    const minX = Math.min(...xs);
    const minY = Math.min(...ys);
    const maxX = Math.max(...xs);
    const maxY = Math.max(...ys);
    const w2 = maxX - minX;
    const h2 = maxY - minY;
    if (!Number.isFinite(minX) || !Number.isFinite(minY) || !Number.isFinite(w2) || !Number.isFinite(h2) || w2 <= 0 || h2 <= 0) {
      return null;
    }
    return {
      x: minX,
      y: minY,
      w: w2,
      h: h2
    };
  }
  const scaleX = numberOr$1(transform.scale_x, 1) ?? 1;
  const scaleY = numberOr$1(transform.scale_y, 1) ?? 1;
  const translateX = numberOr$1(transform.translate_x, 0) ?? 0;
  const translateY = numberOr$1(transform.translate_y, 0) ?? 0;
  if (!Number.isFinite(scaleX) || !Number.isFinite(scaleY) || !Number.isFinite(translateX) || !Number.isFinite(translateY) || scaleX <= 0 || scaleY <= 0) {
    return null;
  }
  const w = region.w * scaleX;
  const h = region.h * scaleY;
  if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) {
    return null;
  }
  return {
    x: region.x * scaleX + translateX,
    y: region.y * scaleY + translateY,
    w,
    h
  };
}
function transformOptionRegion(region, sourceRect, transform = null) {
  const local = transformRegionToViewport(region, sourceRect);
  return local ? applySourceTransform(local, transform) : null;
}
function containsRegion(container, region) {
  const epsilon = 1e-7;
  return region.x >= container.x - epsilon && region.y >= container.y - epsilon && region.x + region.w <= container.x + container.w + epsilon && region.y + region.h <= container.y + container.h + epsilon;
}
function inUnitRegion(region) {
  if (!isRegion(region)) return false;
  const epsilon = 1e-7;
  return region.x >= -epsilon && region.y >= -epsilon && region.x + region.w <= 1 + epsilon && region.y + region.h <= 1 + epsilon;
}
function isDedicatedCropSource(cropSource, cropAsset, cropUrl) {
  const kind = stringOr$1(cropSource?.kind ?? cropSource?.type, null)?.toLowerCase();
  return kind === "dedicated_crop" || cropAsset?.kind === "dedicated_crop" || cropUrl !== null && cropUrl !== "";
}
function reviewSummary(review) {
  if (!review?.decision) {
    return { reviewed: false, decision: null, selected_option: null, text: null };
  }
  const selected = review.selected_option ? ` ${review.selected_option}` : "";
  return {
    reviewed: true,
    decision: review.decision,
    selected_option: review.selected_option ?? null,
    text: `Reviewed: ${review.decision}${selected}`
  };
}
function stateLabel$1(state, expected) {
  const label = state === "marked" ? "Marked" : state === "blank" ? "Blank" : state === "ambiguous" ? "Ambiguous" : state === "unreadable" ? "Unreadable" : "Machine state unavailable";
  return expected === null ? `${label}; no expected answer` : label;
}
function iconFor(tone, kind) {
  if (kind === "expected-ring") return "○";
  if (tone === "match") return "✓";
  if (tone === "mismatch") return "×";
  if (tone === "ambiguous") return "?";
  if (tone === "unreadable") return "!";
  return "•";
}
function toneLabel(tone, expectedRing) {
  const labels = {
    match: "machine match",
    mismatch: "machine mismatch",
    ambiguous: "ambiguous machine candidate",
    unreadable: "unreadable machine candidate",
    neutral: "machine observation without expected answer"
  };
  const parts = [];
  if (tone) parts.push(labels[tone]);
  if (expectedRing) parts.push("expected answer");
  return parts.length > 0 ? parts.join("; ") : "declared option";
}
function ellipseFor(region, scale) {
  return {
    cx: region.x + region.w / 2,
    cy: region.y + region.h / 2,
    rx: Math.max(1e-4, region.w * scale),
    ry: Math.max(1e-4, region.h * scale)
  };
}
function makeLayer(kind, code, tone, geometry, ariaLabel) {
  const isRing = kind === "expected-ring";
  return {
    kind,
    layer: kind,
    code,
    tone,
    geometry,
    ellipse: ellipseFor(geometry, isRing ? 0.58 : 0.4),
    fill: isRing ? "none" : "currentColor",
    stroke: isRing ? "currentColor" : "none",
    pattern: kind === "candidate-hatch" ? "unreadable-hatch" : null,
    icon: iconFor(tone, kind),
    aria_label: ariaLabel
  };
}
function unavailableRecipe(input, reasons) {
  const machineState = input.machine?.state ?? null;
  const expectedOption = input.expected.option;
  const review = reviewSummary(input.review);
  return {
    status: "unavailable",
    unavailable_reason: reasons[0] ?? "missing-machine-evidence",
    unavailable_reasons: reasons,
    machine_state: machineState,
    expected_option: expectedOption,
    observed_options: input.machine?.observed_options ?? [],
    viewport: null,
    source_rect: null,
    asset: null,
    page_no: input.geometry.page_no,
    geometry_manifest_hash: input.geometry.geometry_manifest_hash,
    evidence_version: input.geometry.evidence_version,
    visuals: [],
    layers: [],
    state_label: stateLabel$1(machineState, expectedOption),
    description: `Evidence unavailable: ${reasons.join(", ")}. Machine evidence remains unchanged.`,
    review
  };
}
function validateInput(input) {
  const reasons = [];
  const machine = input.machine;
  const geometry = input.geometry;
  const itemRect = geometry.item_rect ?? geometry.crop_rect;
  const isDedicated = isDedicatedCropSource(geometry.crop_source, geometry.crop_asset, input.crop_url);
  if (!machine || machine.state === null) {
    reasons.push("missing-machine-evidence");
  } else {
    if (machine.state === "marked" && (machine.selected_option === null || machine.observed_options.length === 0)) {
      reasons.push("missing-machine-evidence");
    }
    if (machine.state === "ambiguous" && machine.observed_options.length < 2) {
      reasons.push("missing-machine-evidence");
    }
  }
  if (!itemRect || !isRegion(itemRect)) {
    reasons.push("missing-item-geometry");
  } else if (!inUnitRegion(itemRect)) {
    reasons.push("geometry-out-of-bounds");
  }
  const asset = isDedicated ? geometry.crop_asset ?? (input.crop_url ? geometry.aligned_asset : null) : geometry.aligned_asset ?? geometry.crop_asset;
  if (!asset) {
    reasons.push("missing-asset");
  } else {
    if (positiveNumberOr$1(asset.width) === null || positiveNumberOr$1(asset.height) === null) {
      reasons.push("missing-asset-dimensions");
    }
    if (!normalizeHash$1(asset.sha256)) {
      reasons.push("missing-asset-hash");
    }
  }
  if (!machine?.evidence_hash) reasons.push("missing-evidence-hash");
  if (!geometry.geometry_manifest_hash) reasons.push("missing-geometry-hash");
  if (!geometry.evidence_version) reasons.push("missing-evidence-version");
  const pageNo = geometry.page_no ?? asset?.page_no ?? null;
  if (pageNo === null || !Number.isInteger(pageNo) || pageNo < 1) {
    reasons.push("missing-page");
  } else if (geometry.page_no !== null && asset?.page_no !== null && asset?.page_no !== void 0 && geometry.page_no !== asset.page_no) {
    reasons.push("missing-page");
  }
  const options = geometry.options;
  const optionCodes = /* @__PURE__ */ new Set();
  for (const option of options) {
    if (optionCodes.has(option.code)) reasons.push("duplicate-option-geometry");
    optionCodes.add(option.code);
    if (!inUnitRegion(option.region)) reasons.push("geometry-out-of-bounds");
  }
  if (options.length === 0 && (machine?.observed_options.length || input.expected.option)) {
    reasons.push("missing-option-geometry");
  }
  const expected = input.expected.option;
  if (expected !== null && !optionCodes.has(expected)) reasons.push("missing-expected-geometry");
  for (const code of machine?.observed_options ?? []) {
    if (!optionCodes.has(code)) reasons.push("missing-observed-geometry");
  }
  const cropSource = geometry.crop_source;
  let sourceRect = { x: 0, y: 0, w: 1, h: 1 };
  if (cropSource && cropSource.valid === false) {
    reasons.push("invalid-crop-transform");
  }
  if (cropSource?.transform && cropSource.transform.valid === false) {
    reasons.push("invalid-crop-transform");
  }
  if (isDedicated) {
    sourceRect = toRegion(
      cropSource?.source_rect ?? cropSource?.rect ?? cropSource?.aligned_rect ?? cropSource?.transform?.source_rect ?? cropSource?.transform?.rect
    );
    if (!sourceRect || !isRegion(sourceRect)) {
      reasons.push("missing-crop-transform");
    } else {
      if (!inUnitRegion(sourceRect)) {
        reasons.push("geometry-out-of-bounds");
      }
      if (itemRect && isRegion(itemRect) && !containsRegion(sourceRect, itemRect)) {
        reasons.push("geometry-out-of-bounds");
      }
    }
  }
  const viewport = itemRect && isRegion(itemRect) ? isDedicated ? { x: 0, y: 0, w: 1, h: 1 } : cropBox(itemRect) : null;
  if (!viewport || !isRegion(viewport)) reasons.push("missing-item-geometry");
  return {
    reasons: [...new Set(reasons)],
    viewport,
    sourceRect,
    asset: asset ?? null
  };
}
function buildBubbleLayerRecipe(value) {
  const input = normalizeOverlayInput(value);
  const validation = validateInput(input);
  if (validation.reasons.length > 0) return unavailableRecipe(input, validation.reasons);
  const machine = input.machine;
  const expectedOption = input.expected.option;
  const viewport = validation.viewport;
  const sourceRect = validation.sourceRect;
  const isDedicated = isDedicatedCropSource(
    input.geometry.crop_source,
    input.geometry.crop_asset,
    input.crop_url
  );
  const transform = input.geometry.crop_source?.transform ?? null;
  const visuals = [];
  const layers = [];
  const observed = new Set(machine.observed_options);
  const optionRegions = /* @__PURE__ */ new Map();
  for (const option of input.geometry.options) {
    const local = isDedicated ? transformOptionRegion(option.region, sourceRect, transform) : transformRegionToViewport(option.region, viewport);
    if (!local || !inUnitRegion(local)) {
      return unavailableRecipe(input, ["geometry-out-of-bounds"]);
    }
    optionRegions.set(option.code, local);
  }
  for (const [code, region] of optionRegions) {
    const isObserved = observed.has(code);
    let fillTone = null;
    let pattern = null;
    if (machine.state === "marked" && isObserved && machine.selected_option === code) {
      fillTone = expectedOption === null ? "neutral" : code === expectedOption ? "match" : "mismatch";
    } else if (machine.state === "ambiguous" && isObserved) {
      fillTone = expectedOption === null ? "neutral" : "ambiguous";
    } else if (machine.state === "unreadable" && isObserved) {
      pattern = "unreadable-hatch";
      fillTone = expectedOption === null ? "neutral" : "unreadable";
    }
    const expectedRing = expectedOption === code;
    const ellipse = ellipseFor(region, 0.4);
    const ariaLabel = `Option ${code}: ${toneLabel(fillTone, expectedRing)}`;
    visuals.push({
      code,
      geometry: region,
      ellipse,
      fill_tone: fillTone,
      expected_ring: expectedRing,
      pattern,
      icon: pattern ? "!" : fillTone ? iconFor(fillTone, "machine-fill") : expectedRing ? "○" : "·",
      aria_label: ariaLabel
    });
    if (fillTone && pattern === null) {
      layers.push(makeLayer("machine-fill", code, fillTone, region, ariaLabel));
    }
    if (pattern) {
      layers.push(makeLayer("candidate-hatch", code, fillTone ?? "unreadable", region, ariaLabel));
    }
  }
  for (const visual of visuals) {
    if (!visual.expected_ring) continue;
    layers.push(makeLayer("expected-ring", visual.code, "expected", visual.geometry, visual.aria_label));
  }
  const state = stateLabel$1(machine.state, expectedOption);
  const observedLabel = machine.observed_options.length > 0 ? machine.observed_options.join(", ") : "none";
  const expectedLabel = expectedOption ?? "none";
  const issuesLabel = machine.issue_codes.length > 0 ? ` Issues: ${machine.issue_codes.join(", ")}.` : "";
  return {
    status: "ready",
    unavailable_reason: null,
    unavailable_reasons: [],
    machine_state: machine.state,
    expected_option: expectedOption,
    observed_options: machine.observed_options,
    viewport,
    source_rect: sourceRect,
    asset: validation.asset,
    page_no: input.geometry.page_no ?? validation.asset?.page_no ?? null,
    geometry_manifest_hash: input.geometry.geometry_manifest_hash,
    evidence_version: input.geometry.evidence_version,
    visuals,
    layers,
    state_label: state,
    description: `Machine state: ${state}. Observed options: ${observedLabel}. Expected option: ${expectedLabel}.${issuesLabel}`,
    review: reviewSummary(input.review)
  };
}
function cropAspectRatio(viewport, asset) {
  if (!isRegion(viewport) || !asset) return null;
  const width = positiveNumberOr$1(asset.width);
  const height = positiveNumberOr$1(asset.height);
  if (width === null || height === null) return null;
  return viewport.w * width / (viewport.h * height);
}
function overlayAccessibleText(recipe) {
  const review = recipe.review.text ? ` ${recipe.review.text}.` : "";
  return `${recipe.description}${review}`;
}
function isRegion(value) {
  if (!value || typeof value !== "object" || Array.isArray(value)) return false;
  const region = value;
  return typeof region.x === "number" && typeof region.y === "number" && typeof region.w === "number" && typeof region.h === "number" && Number.isFinite(region.x) && Number.isFinite(region.y) && Number.isFinite(region.w) && Number.isFinite(region.h) && region.w > 0 && region.h > 0;
}
function isRecord(value) {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}
function toRegion(value) {
  return isRegion(value) ? value : null;
}
function cropBox(region) {
  if (!isRegion(region)) return null;
  const padLeft = region.w * 0.22;
  const padRight = region.w * 0.05;
  const padTop = region.h * 0.25;
  const padBottom = region.h * 0.2;
  const x = Math.max(0, region.x - padLeft);
  const y = Math.max(0, region.y - padTop);
  const w = Math.min(1 - x, region.w + padLeft + padRight);
  const h = Math.min(1 - y, region.h + padTop + padBottom);
  if (!Number.isFinite(w) || !Number.isFinite(h) || w <= 0 || h <= 0) return null;
  return { x, y, w, h };
}
function OmrRegionCrop($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      /** Canonical item projection: machine, expected, geometry, review. */
      item = null,
      machine = null,
      expected = null,
      geometry = null,
      review = null,
      /** Pinned aligned-page or dedicated-crop provenance. */
      asset = null,
      page_asset = null,
      crop_source = null,
      /** Existing hosts use these URL props until their cutover lands. */
      cropUrl = null,
      imageUrl = null,
      region = null,
      options = [],
      detectedOption = null,
      expectedOption = null,
      state: itemState = null,
      mode = "markings",
      alt = "",
      title = "",
      width = 170,
      id = "omr-region-crop"
    } = $$props;
    let failed = false;
    let visible = false;
    let observer = null;
    const input = derived(() => ({
      item,
      machine,
      expected,
      geometry,
      review,
      asset,
      page_asset,
      crop_source,
      cropUrl,
      imageUrl,
      region,
      options,
      detectedOption,
      expectedOption,
      state: itemState
    }));
    const normalized = derived(() => normalizeOverlayInput(input()));
    const recipe = derived(() => buildBubbleLayerRecipe(input()));
    const displayMode = derived(() => mode === "raw" || mode === "stencil" || mode === "markings" ? mode : "markings");
    const stencilAvailable = derived(() => displayMode() === "stencil" && recipe().status === "ready" && recipe().expected_option !== null && recipe().visuals.some((visual) => visual.expected_ring));
    const usesModeImage = derived(() => displayMode() === "raw" && normalized().image_url !== null);
    const sourceUrl = derived(() => usesModeImage() ? normalized().image_url : normalized().source_url);
    const isDedicatedCrop = derived(() => !usesModeImage() && (normalized().crop_url !== null || normalized().geometry.crop_source?.kind === "dedicated_crop" || normalized().geometry.crop_source?.type === "dedicated_crop"));
    const usesAlignedPage = derived(() => !isDedicatedCrop() && sourceUrl() !== null);
    const ratio = derived(() => recipe().status === "ready" ? cropAspectRatio(recipe().viewport, recipe().asset) : null);
    const viewportStyle = derived(() => ratio() !== null && Number.isFinite(ratio()) ? `aspect-ratio:${ratio()};` : "");
    const accessibleText = derived(() => overlayAccessibleText(recipe()));
    const modeAccessibleText = derived(() => displayMode() === "raw" ? "Raw captured evidence. No interpretive overlays are shown." : displayMode() === "stencil" ? stencilAvailable() ? "Expected-answer stencil. Non-expected bubbles are masked." : "Expected-answer stencil unavailable. Expected truth is not disclosed." : accessibleText());
    const titleText = derived(() => title || alt || recipe().state_label || "Evidence crop");
    const unavailableText = derived(() => recipe().status === "unavailable" ? `Evidence unavailable: ${recipe().unavailable_reasons.join(", ")}` : "Crop unavailable");
    onDestroy(() => observer?.disconnect());
    $$renderer2.push(`<div${attr_class("region-crop svelte-1my5nex", void 0, {
      "aligned-page": usesAlignedPage(),
      "ready": recipe().status === "ready",
      "unavailable": recipe().status === "unavailable",
      "failed": failed
    })} role="group"${attr("aria-label", titleText())}${attr("aria-describedby", `${id}-description`)}${attr("data-omr-crop-status", recipe().status)}${attr("data-omr-crop-mode", displayMode())}${attr("data-omr-evidence-state", recipe().machine_state ?? "unavailable")}${attr_style(viewportStyle())}>`);
    if (sourceUrl() && visible) ;
    else if (!sourceUrl()) {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<span class="crop-note svelte-1my5nex" role="status">${escape_html(unavailableText())}</span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<span class="crop-note svelte-1my5nex" role="status">Loading crop…</span>`);
    }
    $$renderer2.push(`<!--]--> <span${attr("id", `${id}-description`)} class="sr-only svelte-1my5nex">${escape_html(modeAccessibleText())}</span> `);
    if (recipe().review.reviewed) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<span class="review-summary svelte-1my5nex" data-review-summary=""><span class="review-badge svelte-1my5nex">Reviewed</span> <span>${escape_html(recipe().review.text)}</span></span>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div>`);
  });
}
function positiveNumberOr(value) {
  const parsed = numberOr(value, null);
  return parsed !== null && parsed > 0 ? parsed : null;
}
function normalizeHash(value) {
  const hash = stringOr(value, null)?.toLowerCase() ?? null;
  return hash !== null && /^[a-f0-9]{64}$/.test(hash) ? hash : null;
}
const STATE_LABELS = {
  marked: "Marked",
  blank: "Blank",
  ambiguous: "Ambiguous",
  invalid: "Invalid",
  unresolved: "Unresolved"
};
function normalizeEvidence(raw, opts = {}) {
  if (!raw || typeof raw !== "object") return null;
  const source = raw;
  const rawAssets = isRecord(source.assets) ? source.assets : {};
  const alignedAsset = normalizeAsset(
    isRecord(rawAssets.aligned) ? rawAssets.aligned : null
  );
  const overlayAsset = normalizeAsset(
    isRecord(rawAssets.overlay) ? rawAssets.overlay : null
  );
  const capturedAsset = normalizeAsset(
    isRecord(rawAssets.captured) ? rawAssets.captured : null
  );
  const alignedImage = isRecord(source.aligned_image) ? source.aligned_image : null;
  const overlayImage = isRecord(source.overlay_image) ? source.overlay_image : null;
  const cropImageUrl = opts.cropImageUrl ?? (isRecord(alignedAsset) ? alignedAsset.url ?? null : null);
  const alignedUrl = cropImageUrl ?? (isRecord(alignedAsset) ? alignedAsset.url ?? null : null);
  const hashes = isRecord(source.hashes) ? source.hashes : {};
  const rawItems = Array.isArray(source.items) ? source.items : Array.isArray(source.item_evidence) ? source.item_evidence : [];
  const items = rawItems.map(normalizeItem);
  return {
    outcome: typeof source.outcome === "string" ? source.outcome : null,
    code: typeof source.code === "string" ? source.code : null,
    detail: typeof source.detail === "string" ? source.detail : null,
    page_no: numberOr(source.page_no, 1) ?? 1,
    target: isRecord(source.target) ? source.target : null,
    assets: {
      captured: capturedAsset ?? (typeof source.captured_url === "string" ? { url: source.captured_url } : null),
      aligned: alignedAsset ?? (alignedUrl ? {
        url: alignedUrl,
        mime: alignedImage?.mime ?? null,
        width: numberOr(alignedImage?.width, null),
        height: numberOr(alignedImage?.height, null),
        sha256: typeof alignedImage?.hash === "string" ? alignedImage.hash : null
      } : null),
      overlay: overlayAsset ?? (typeof source.overlay_url === "string" ? {
        url: source.overlay_url,
        mime: overlayImage?.mime ?? null,
        width: numberOr(overlayImage?.width, null),
        height: numberOr(overlayImage?.height, null),
        sha256: typeof overlayImage?.hash === "string" ? overlayImage.hash : null
      } : null)
    },
    hashes: {
      input: stringOr(hashes?.input ?? source.input_hash, null),
      aligned: stringOr(hashes?.aligned ?? alignedImage?.hash, null),
      overlay: stringOr(hashes?.overlay ?? overlayImage?.hash, null),
      plan: stringOr(hashes?.plan ?? source.plan_hash, null),
      evidence: stringOr(source.evidence_hash, null)
    },
    gates: isRecord(source.gates) ? source.gates : null,
    marker_matches: isRecord(source.marker_matches) ? Object.fromEntries(
      Object.entries(source.marker_matches).map(
        ([key, value]) => [
          key,
          typeof value === "number" ? value : Number(value)
        ]
      )
    ) : null,
    field_block_shifts: normalizeShifts(source.field_block_shifts),
    items,
    identity: Array.isArray(source.identity) ? source.identity.map(normalizeIdentity) : [],
    summary: computeSummary(items),
    timings: normalizeTimings(source.timings),
    issues: Array.isArray(source.issues) ? source.issues : [],
    calibration_status: stringOr(source.calibration_status, null),
    rectification: isRecord(source.rectification) ? source.rectification : null,
    plan_hash: stringOr(source.plan_hash, null),
    plan_purpose: stringOr(source.plan_purpose, null),
    crop_image_url: cropImageUrl
  };
}
function normalizeEvidencePage(raw) {
  if (!raw || typeof raw !== "object") return null;
  const source = raw;
  const method = stringOr(source.method, "") ?? "";
  const captureId = numberOr(source.capture_id ?? source.capture, 0) ?? 0;
  if (!method || captureId < 1) return null;
  const rawGroups = Array.isArray(source.groups) ? source.groups : Array.isArray(source.group_navigation) ? source.group_navigation : [];
  const groups = rawGroups.filter((group) => isRecord(group)).map((group) => ({
    key: stringOr(group.key ?? group.area_key ?? group.group_type, "") ?? "",
    label: stringOr(
      group.label ?? group.key ?? group.area_key ?? group.group_type,
      "Evidence"
    ) ?? "Evidence",
    group_type: stringOr(group.group_type, null),
    area_key: stringOr(group.area_key, null),
    area_position: numberOr(group.area_position, null),
    total: numberOr(group.total, 0) ?? 0,
    mismatch_total: numberOr(group.mismatch_total, 0) ?? 0,
    page_count: numberOr(group.page_count, 1) ?? 1,
    url: stringOr(group.url, null)
  })).filter((group) => group.key !== "");
  const items = (Array.isArray(source.items) ? source.items : Array.isArray(source.rows) ? source.rows : []).filter((item) => isRecord(item));
  const rawSheetMap = isRecord(source.sheet_map) ? source.sheet_map : {};
  const sheetItems = Array.isArray(rawSheetMap.items) ? rawSheetMap.items.filter(
    (item) => isRecord(item)
  ) : [];
  const documentFields = Array.isArray(rawSheetMap.document_fields) ? rawSheetMap.document_fields.filter(
    (field) => isRecord(field)
  ) : [];
  const lazy = isRecord(source.lazy_asset_urls) ? source.lazy_asset_urls : isRecord(source.asset_urls) ? source.asset_urls : {};
  const rawFilter = stringOr(source.filter, "all") ?? "all";
  const filter = normalizeEvidenceFilter(rawFilter);
  const rawReasons = Array.isArray(
    source.review_reasons ?? source.reasons
  ) ? source.review_reasons ?? source.reasons : [];
  const reviewReasons = rawReasons.filter((reason) => isRecord(reason)).map((reason) => ({
    code: stringOr(reason.code, "") ?? "",
    label: stringOr(reason.label ?? reason.name ?? reason.code, "") ?? ""
  })).filter((reason) => reason.code !== "" && reason.label !== "");
  return {
    method,
    capture_id: captureId,
    area: stringOr(source.area, "all") ?? "all",
    area_position: numberOr(source.area_position, null),
    total: numberOr(source.total, items.length) ?? items.length,
    mismatch_total: numberOr(source.mismatch_total, 0) ?? 0,
    needs_review_total: numberOr(source.needs_review_total, null),
    reviewed_total: numberOr(source.reviewed_total, null),
    page: Math.max(1, numberOr(source.page, 1) ?? 1),
    per_page: Math.min(
      50,
      Math.max(1, numberOr(source.per_page, 25) ?? 25)
    ),
    page_count: Math.max(1, numberOr(source.page_count, 1) ?? 1),
    filter,
    evidence_version: stringOr(source.evidence_version, null),
    evidence_hash: stringOr(source.evidence_hash, null),
    attempt_no: numberOr(source.attempt_no, null),
    capture_page_no: numberOr(source.capture_page_no, null),
    parent_version: numberOr(
      source.parent_version ?? source.version,
      null
    ),
    reviewable: source.reviewable !== false,
    blind_review: source.blind_review === true || source.presentation === "blind" || source.review_presentation === "blind",
    lifecycle: stringOr(source.lifecycle ?? source.status, null),
    status: stringOr(source.status, null),
    review_note_max: Math.min(
      2e3,
      Math.max(1, numberOr(source.review_note_max, 500) ?? 500)
    ),
    review_reasons: reviewReasons,
    groups,
    items,
    lazy_asset_urls: Object.fromEntries(
      Object.entries(lazy).map(([key, value]) => [
        key,
        typeof value === "string" ? value : null
      ])
    ),
    sheet_map: {
      page_no: Math.max(
        1,
        numberOr(
          rawSheetMap.page_no ?? source.capture_page_no,
          1
        ) ?? 1
      ),
      width: positiveNumberOr(rawSheetMap.width),
      height: positiveNumberOr(rawSheetMap.height),
      sha256: normalizeHash(rawSheetMap.sha256),
      identity_write_in: isRecord(rawSheetMap.identity_write_in) ? rawSheetMap.identity_write_in : null,
      items: sheetItems,
      document_fields: documentFields
    }
  };
}
function normalizeItem(item) {
  const machineSource = isRecord(item.machine) ? item.machine : isRecord(item.machine_evidence) ? item.machine_evidence : null;
  const machine = normalizeDetected(
    machineSource ? { ...item, ...machineSource, detected: machineSource } : item
  );
  const detected = machine ? {
    state: machine.state,
    selected_option: machine.selected_option,
    confidence: machine.confidence
  } : null;
  const expectedSource = isRecord(item.expected) ? item.expected : null;
  const expected = stringOr(
    expectedSource?.option ?? expectedSource?.selected_option ?? item.expected ?? item.correct_option_code,
    null
  );
  const exact = normalizeExact(item, detected, expected);
  const geometry = isRecord(item.geometry) ? item.geometry : null;
  const rawOptions = Array.isArray(item.options) ? item.options : geometry && Array.isArray(geometry.options) ? geometry.options : [];
  const means = isRecord(item.means) ? item.means : null;
  const observations = Array.isArray(machine?.option_observations) ? machine.option_observations : [];
  const options = rawOptions.map((option) => {
    const code = stringOr(option.code ?? option.option, "") ?? "";
    const observation = observations.find((entry) => entry.code === code);
    return {
      code,
      region: toRegion(option.region ?? option.rect),
      mean: floatOr(
        option.mean ?? option.bubble_mean ?? means?.[code] ?? observation?.mean,
        null
      ),
      threshold_delta: floatOr(option.threshold_delta, null),
      threshold_status: stringOr(option.threshold_status, null),
      crop_png_base64: null
    };
  });
  const nativeLabel = stringOr(
    item.native_label ?? item.label ?? item.item_id,
    null
  );
  const areaCode = stringOr(item.area_code ?? item.area_key, null);
  const questionNo = item.question_no == null ? null : Number(item.question_no);
  const label = nativeLabel ?? (areaCode ? `${areaCode} Q${questionNo ?? "?"}` : "Item");
  const review = normalizeReview(item.review ?? item.current_review);
  const history = Array.isArray(item.review_history) ? item.review_history.filter((entry) => isRecord(entry)).map(normalizeReview).filter((entry) => entry !== null) : [];
  const effective = normalizeEffective(item.effective);
  const asset = isRecord(item.asset) ? item.asset : isRecord(item.page_asset) ? item.page_asset : null;
  const pageAsset = isRecord(item.page_asset) ? item.page_asset : null;
  const cropSource = isRecord(item.crop_source) ? item.crop_source : geometry && isRecord(geometry.crop_source) ? geometry.crop_source : null;
  const provenance = {
    asset,
    page_asset: pageAsset,
    crop_source: cropSource,
    geometry_manifest_hash: stringOr(
      item.geometry_manifest_hash ?? geometry?.geometry_manifest_hash,
      null
    ),
    evidence_version: stringOr(item.evidence_version, null),
    page_no: numberOr(item.page_no ?? item.page, 1)
  };
  const itemRevision = numberOr(
    item.item_revision ?? review?.revision,
    null
  );
  return {
    id: typeof item.id === "string" || typeof item.id === "number" ? item.id : null,
    item_id: stringOr(item.item_id ?? item.label, null),
    group_type: stringOr(item.group_type ?? item.group, null),
    position: item.position == null ? null : Number(item.position),
    area_code: areaCode,
    question_no: Number.isFinite(questionNo) ? questionNo : null,
    native_label: nativeLabel,
    page_no: numberOr(item.page_no ?? item.page, 1) ?? 1,
    region: toRegion(
      item.region ?? item.item_rect ?? item.crop_rect ?? geometry?.item_rect
    ),
    crop_rect: toRegion(item.crop_rect ?? geometry?.crop_rect),
    options,
    detected,
    machine,
    expected,
    expected_evidence: { option: expected },
    review,
    review_history: history,
    effective,
    provenance,
    geometry,
    exact,
    confidence: floatOr(machine?.confidence ?? item.confidence, null),
    score: item.score == null ? null : Number(item.score),
    issues: normalizeIssueCodes(
      item.issue_codes ?? item.issues ?? machine?.issue_codes
    ),
    parent_version: numberOr(item.parent_version ?? item.version, null),
    item_revision: itemRevision,
    reviewable: item.reviewable !== false,
    label
  };
}
function normalizeIdentity(entry) {
  return {
    code: stringOr(entry.code, null),
    position: numberOr(entry.position, 1) ?? 1,
    page_no: numberOr(entry.page_no, 1) ?? 1,
    region: toRegion(entry.region),
    confidence: floatOr(entry.confidence, null),
    detected_value: stringOr(entry.detected_value, null),
    expected_digit: stringOr(entry.expected_digit, null),
    exact: entry.exact == null ? null : Boolean(entry.exact)
  };
}
function computeSummary(items) {
  let expected = 0;
  let exact = 0;
  let ambiguous = 0;
  for (const item of items) {
    if (item.expected === null) continue;
    expected += 1;
    if (item.exact === true) {
      exact += 1;
      continue;
    }
    if (item.detected?.state === "ambiguous") ambiguous += 1;
  }
  return {
    total: items.length,
    expected,
    exact,
    ambiguous,
    missing: Math.max(0, expected - exact - ambiguous),
    vector_match: expected > 0 ? exact === expected : null
  };
}
function itemProblem(item) {
  if (item.expected === null) return null;
  if (item.detected?.state === "ambiguous" || item.detected?.state === "unreadable")
    return "ambiguous";
  if (item.detected == null) return "missing";
  if (item.detected.selected_option == null && item.detected.state !== "marked" && item.detected.state !== "match")
    return "missing";
  if (item.exact === false) return "incorrect";
  return null;
}
function isProblemItem(item) {
  return itemProblem(item) !== null;
}
function filterItems(items, filter) {
  return filter === "problems" ? items.filter(isProblemItem) : items;
}
function normalizeEvidenceFilter(value) {
  return value === "mismatches" || value === "mismatch" ? "mismatch" : value === "needs_review" || value === "reviewed" ? value : "all";
}
function reviewIsStale(item) {
  const reviewHash = item.review?.evidence_hash;
  const machineHash = item.machine?.evidence_hash;
  return Boolean(
    item.review?.stale || reviewHash && machineHash && reviewHash !== machineHash
  );
}
function stateLabel(state) {
  return state ? STATE_LABELS[state] ?? state : "Unavailable";
}
function detectedText(item) {
  if (item.detected?.selected_option) return item.detected.selected_option;
  return stateLabel(item.detected?.state ?? null);
}
function returnLabel(view, filter) {
  const visible = filterItems(view.items, filter);
  const firstProblem = visible.find(isProblemItem);
  return firstProblem?.label ?? visible[0]?.label ?? null;
}
function mappingUrl(returnUrl, label) {
  if (!returnUrl || !label) return null;
  const separator = returnUrl.includes("?") ? "&" : "?";
  return `${returnUrl}${separator}row=${encodeURIComponent(label)}`;
}
function normalizeDetected(item) {
  const nested = isRecord(item.detected) ? item.detected : null;
  const scalar = !nested ? stringOr(item.detected, null) : null;
  const state = stringOr(nested?.state ?? item.state, null);
  const observedSource = nested?.observed_options ?? item.observed_options;
  const observed = Array.isArray(observedSource) ? observedSource : [];
  const observationsSource = nested?.option_observations ?? item.option_observations;
  const observations = Array.isArray(observationsSource) ? observationsSource : [];
  if (nested || state || scalar || observed.length || observations.length) {
    return {
      state,
      selected_option: stringOr(
        nested?.selected_option ?? item.detected_answer ?? scalar,
        null
      ),
      confidence: floatOr(nested?.confidence ?? item.confidence, null),
      observed_options: [...new Set(
        observed.map((code) => String(code)).filter((code) => code !== "")
      )],
      option_observations: observations.filter(
        (entry) => isRecord(entry)
      ).map((entry) => ({
        code: stringOr(entry.code ?? entry.option, "") ?? "",
        mean: floatOr(entry.mean ?? entry.bubble_mean, null),
        classification: stringOr(entry.classification, null)
      })).filter((entry) => entry.code !== ""),
      issue_codes: normalizeIssueCodes(
        nested?.issue_codes ?? item.issue_codes
      ),
      evidence_hash: stringOr(
        nested?.evidence_hash ?? item.evidence_hash,
        null
      )
    };
  }
  return null;
}
function normalizeReview(value) {
  if (!isRecord(value)) return null;
  const reviewer = isRecord(value.reviewer) ? {
    id: typeof value.reviewer.id === "string" || typeof value.reviewer.id === "number" ? value.reviewer.id : null,
    display_name: stringOr(
      value.reviewer.display_name ?? value.reviewer.name,
      null
    )
  } : null;
  return {
    revision: numberOr(value.revision, null),
    decision: stringOr(value.decision, null),
    selected_option: stringOr(value.selected_option, null),
    reason_code: stringOr(value.reason_code, null),
    note: stringOr(value.note, null),
    reviewer,
    reviewed_at: stringOr(value.reviewed_at ?? value.created_at, null),
    evidence_hash: stringOr(
      value.evidence_hash ?? value.reviewed_machine_evidence_hash,
      null
    ),
    stale: value.stale === true
  };
}
function normalizeEffective(value) {
  if (!isRecord(value)) return null;
  return {
    state: stringOr(value.state, null),
    selected_option: stringOr(value.selected_option, null),
    source: stringOr(value.source, null)
  };
}
function normalizeIssueCodes(value) {
  if (!Array.isArray(value)) return [];
  return [...new Set(value.map(String).filter((code) => code !== ""))];
}
function normalizeExact(item, detected, expected) {
  if (item.exact != null) return Boolean(item.exact);
  if (detected === null || expected === null) return null;
  if (detected.state === "ambiguous" || detected.state === "unreadable" || detected.state === "blank")
    return false;
  if (detected.state === "match" || detected.state === "marked")
    return detected.selected_option === expected;
  if (detected.state === "mismatch") return false;
  return detected.selected_option === expected;
}
function normalizeShifts(value) {
  if (!Array.isArray(value)) return [];
  return value.map((entry) => {
    if (!isRecord(entry)) return null;
    return {
      block: stringOr(entry.block, ""),
      x: floatOr(entry.x, null),
      y: floatOr(entry.y, null)
    };
  }).filter(
    (entry) => entry !== null && entry.block !== ""
  );
}
function normalizeTimings(value) {
  const timings = isRecord(value) ? value : {};
  return {
    preprocess_ms: floatOr(timings.preprocess_ms, null),
    layout_verification_ms: floatOr(
      timings.layout_verification_ms ?? timings.verification_ms,
      null
    ),
    detection_ms: floatOr(timings.detection_ms, null)
  };
}
function normalizeAsset(asset) {
  if (!isRecord(asset)) return null;
  const url = stringOr(asset.url, null);
  const sha256 = stringOr(asset.sha256, null);
  return {
    url,
    mime: stringOr(asset.mime, null),
    width: numberOr(asset.width, null),
    height: numberOr(asset.height, null),
    sha256
  };
}
function stringOr(value, fallback) {
  return typeof value === "string" && value !== "" ? value : fallback;
}
function numberOr(value, fallback) {
  const parsed = typeof value === "number" ? value : value == null || value === "" ? NaN : Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}
function floatOr(value, fallback) {
  if (value == null) return fallback;
  const parsed = typeof value === "number" ? value : Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}
function OmrSheetEvidenceCanvas($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      sheetMap = null,
      imageUrl = null,
      selectedId = null,
      blind = false,
      mode = "markings"
    } = $$props;
    let view = { x: 0, y: 0, w: 1, h: 1 };
    let drag = null;
    const width = derived(() => Number(sheetMap?.width ?? 0));
    const height = derived(() => Number(sheetMap?.height ?? 0));
    const ready = derived(() => Boolean(imageUrl && width() > 0 && height() > 0));
    const items = derived(() => (Array.isArray(sheetMap?.items) ? sheetMap.items : []).map((item) => normalizeEvidence({ items: [item] })?.items?.[0] ?? null).filter((item) => item !== null));
    function itemId(item) {
      return String(item.id ?? item.item_id ?? item.label);
    }
    function observedCodes(item) {
      const observed = item.machine?.observed_options ?? [];
      return observed.length > 0 ? observed : item.machine?.selected_option ? [item.machine.selected_option] : [];
    }
    function machineTone(item, code) {
      if (!observedCodes(item).includes(code)) return "none";
      if (item.machine?.state === "ambiguous") return "ambiguous";
      if (blind || item.expected === null) return "neutral";
      return item.expected === code ? "match" : "mismatch";
    }
    $$renderer2.push(`<section class="border-border bg-muted/20 overflow-hidden rounded-b-xl border" aria-label="Full-sheet evidence map"><div class="border-border bg-background flex items-center justify-between gap-2 border-b px-3 py-2"><div class="flex min-w-0 items-center gap-2 text-xs font-semibold">`);
    Move($$renderer2, {
      class: "text-muted-foreground size-3.5",
      "aria-hidden": "true"
    });
    $$renderer2.push(`<!----> <span class="truncate">${escape_html(mode === "raw" ? "Raw capture" : mode === "stencil" ? "Answer stencil" : "Marked sheet")}</span> <span class="text-muted-foreground hidden font-normal sm:inline">${escape_html(items().length)} mapped items</span></div> <div class="flex items-center gap-1.5"><button type="button" class="icon-button svelte-1u030zp" aria-label="Zoom out">`);
    Zoom_out($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----></button> <button type="button" class="icon-button svelte-1u030zp" aria-label="Zoom in">`);
    Zoom_in($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----></button> <button type="button" class="icon-button svelte-1u030zp" aria-label="Fit full sheet">`);
    Maximize_2($$renderer2, { class: "size-3.5" });
    $$renderer2.push(`<!----></button></div></div> `);
    if (ready()) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="relative max-h-[68vh] min-h-80 overflow-hidden bg-slate-900 p-2 sm:min-h-[34rem]"><svg${attr_class("h-full max-h-[66vh] min-h-80 w-full cursor-grab touch-none select-none sm:min-h-[33rem]", void 0, { "cursor-grabbing": drag !== null })}${attr("viewBox", `${view.x} ${view.y} ${view.w} ${view.h}`)} preserveAspectRatio="xMidYMid meet" role="img"${attr("aria-label", `${mode === "raw" ? "Raw captured" : mode === "stencil" ? "Expected-answer stencil" : "Machine-marked"} answer sheet`)}><image${attr("href", imageUrl)} x="0" y="0"${attr("width", width())}${attr("height", height())}></image><!--[-->`);
      const each_array = ensure_array_like(items());
      for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
        let item = each_array[$$index_1];
        const selected = itemId(item) === String(selectedId ?? "");
        const itemRegion = item.crop_rect ?? item.region;
        if (itemRegion) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<g${attr("data-sheet-item", itemId(item))} class="sheet-item svelte-1u030zp" role="button" tabindex="0"${attr("aria-label", `Inspect ${item.label}`)}>`);
          if (selected) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<rect class="selected-item svelte-1u030zp"${attr("x", itemRegion.x * width())}${attr("y", itemRegion.y * height())}${attr("width", itemRegion.w * width())}${attr("height", itemRegion.h * height())} rx="5"></rect>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--><!--[-->`);
          const each_array_1 = ensure_array_like(item.options);
          for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
            let option = each_array_1[$$index];
            if (option.region) {
              $$renderer2.push("<!--[0-->");
              const centerX = (option.region.x + option.region.w / 2) * width();
              const centerY = (option.region.y + option.region.h / 2) * height();
              const radiusX = option.region.w * width() / 2;
              const radiusY = option.region.h * height() / 2;
              const tone = machineTone(item, option.code);
              if (mode === "stencil" && !blind && item.expected !== null && item.expected !== option.code) {
                $$renderer2.push("<!--[0-->");
                $$renderer2.push(`<ellipse class="stencil-mask svelte-1u030zp"${attr("cx", centerX)}${attr("cy", centerY)}${attr("rx", radiusX * 1.18)}${attr("ry", radiusY * 1.18)}></ellipse>`);
              } else {
                $$renderer2.push("<!--[-1-->");
              }
              $$renderer2.push(`<!--]-->`);
              if (mode === "markings" && tone !== "none") {
                $$renderer2.push("<!--[0-->");
                $$renderer2.push(`<ellipse${attr_class(`machine-fill ${tone}`, "svelte-1u030zp")}${attr("cx", centerX)}${attr("cy", centerY)}${attr("rx", radiusX * 0.78)}${attr("ry", radiusY * 0.78)}></ellipse>`);
              } else {
                $$renderer2.push("<!--[-1-->");
              }
              $$renderer2.push(`<!--]-->`);
              if (mode === "markings") {
                $$renderer2.push("<!--[0-->");
                $$renderer2.push(`<ellipse${attr_class("geometry-ring svelte-1u030zp", void 0, { "selected-ring": selected })}${attr("cx", centerX)}${attr("cy", centerY)}${attr("rx", radiusX)}${attr("ry", radiusY)}></ellipse>`);
              } else {
                $$renderer2.push("<!--[-1-->");
              }
              $$renderer2.push(`<!--]-->`);
              if (mode === "markings" && !blind && item.expected === option.code) {
                $$renderer2.push("<!--[0-->");
                $$renderer2.push(`<ellipse class="expected-ring svelte-1u030zp"${attr("cx", centerX)}${attr("cy", centerY)}${attr("rx", radiusX * 1.14)}${attr("ry", radiusY * 1.14)}></ellipse>`);
              } else {
                $$renderer2.push("<!--[-1-->");
              }
              $$renderer2.push(`<!--]-->`);
            } else {
              $$renderer2.push("<!--[-1-->");
            }
            $$renderer2.push(`<!--]-->`);
          }
          $$renderer2.push(`<!--]--><rect class="item-hit-area svelte-1u030zp"${attr("x", itemRegion.x * width())}${attr("y", itemRegion.y * height())}${attr("width", itemRegion.w * width())}${attr("height", itemRegion.h * height())}></rect></g>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]-->`);
      }
      $$renderer2.push(`<!--]--></svg> <p class="pointer-events-none absolute bottom-3 left-3 rounded-md bg-slate-950/85 px-2 py-1 text-[10px] text-slate-200">Scroll to zoom · drag to pan</p></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="text-muted-foreground flex min-h-80 items-center justify-center p-6 text-center text-xs" role="status">Full-sheet mapping is unavailable because aligned-image dimensions
            or geometry are missing.</div>`);
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
function OmrDiagnosticEvidencePanel($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      /** Bounded OmrEvidencePage response. */
      page = null,
      labels = {},
      returnUrl = null,
      loading = false,
      error = null,
      onRetry = null,
      onPageChange = null,
      onGroupChange = null,
      onFilterChange = null,
      onRowOpen = null,
      onTabChange = null,
      onViewChange = null,
      initialTab = "aligned",
      mobileRailExpanded = false,
      contextSummary = null,
      idPrefix = "omr-evidence",
      reviewable = false,
      blind = false,
      reviewReasons = [],
      onDirtyChange = null,
      onDirtyNavigation = null,
      onSaveDecision = null,
      onSaveAndNext = null
    } = $$props;
    const normalizedPage = derived(() => normalizeEvidencePage(page));
    const identityWriteIn = derived(() => normalizedPage()?.sheet_map?.identity_write_in ?? null);
    const visibleItems = derived(() => normalizedPage()?.items ?? []);
    const sheetItems = derived(() => (normalizedPage()?.sheet_map?.items ?? []).map((item) => itemView(item)).filter(Boolean));
    const identityItems = derived(() => visibleItems().filter((item) => item.group_type === "identity").map((item) => itemView(item)).filter(Boolean).toSorted((left, right) => Number(left.question_no ?? left.position ?? 0) - Number(right.question_no ?? right.position ?? 0)));
    const identitySummaryItems = derived(() => {
      const mapped = sheetItems().filter((item) => item.group_type === "identity").toSorted((left, right) => Number(left.question_no ?? left.position ?? 0) - Number(right.question_no ?? right.position ?? 0));
      return mapped.length > 0 ? mapped : identityItems();
    });
    const identityDetailsComplete = derived(() => normalizedPage()?.area === "identity" && identitySummaryItems().length > 0 && identityItems().length === identitySummaryItems().length && identitySummaryItems().every((summaryItem) => identityItems().some((item) => rowId(item) === rowId(summaryItem))));
    const sourceView = derived(() => {
      if (!normalizedPage()) return null;
      const raw = page && typeof page === "object" ? page : {};
      const lazy = normalizedPage().lazy_asset_urls;
      return normalizeEvidence(
        {
          ...raw && typeof raw === "object" ? raw : {},
          outcome: "succeeded",
          page_no: normalizedPage().capture_page_no ?? 1,
          assets: {
            captured: lazy.input ? { url: lazy.input } : null,
            aligned: lazy.aligned ? { url: lazy.aligned } : null,
            overlay: lazy.overlay ? { url: lazy.overlay } : null
          },
          items: visibleItems().filter((item) => item.group_type !== "identity" && item.group_type !== "calibration")
        },
        { cropImageUrl: lazy.aligned ?? null }
      );
    });
    const blindReview = derived(() => Boolean(blind || normalizedPage()?.blind_review));
    const documentFields = derived(() => (normalizedPage()?.sheet_map?.document_fields ?? []).map((field) => ({
      ...field,
      crop_rect: field.region,
      label: field.label ?? field.type ?? "Recorded field"
    })));
    const candidateNameField = derived(() => documentFields().find((field) => field.type === "candidate_name") ?? null);
    const isReviewable = derived(() => Boolean(reviewable && normalizedPage()?.reviewable !== false && (onSaveDecision || onSaveAndNext)));
    const reasons = derived(() => (reviewReasons?.length ? reviewReasons : normalizedPage()?.review_reasons ?? []) ?? []);
    const noteMax = derived(() => normalizedPage()?.review_note_max ?? 500);
    const identityConfidence = derived(() => {
      const values = identitySummaryItems().map((item) => itemMachine(item)?.confidence).filter((value) => typeof value === "number" && Number.isFinite(value));
      return values.length > 0 ? values.reduce((sum, value) => sum + value, 0) / values.length : null;
    });
    const detectedIdentity = derived(() => identitySummaryItems().map((item) => itemMachine(item)?.selected_option ?? item.detected?.selected_option ?? "").join(""));
    const identityDraftCount = derived(() => identityItems().filter((item) => hasDraft(item)).length);
    const selectedEditorItem = derived(() => visibleItems().find((item) => rowId(item) === String("")) ?? null);
    const selectedEditorView = derived(() => selectedEditorItem() ? itemView(selectedEditorItem()) : null);
    const sheetAsset = derived(() => normalizedPage() ? {
      url: normalizedPage().lazy_asset_urls.aligned,
      width: normalizedPage().sheet_map.width,
      height: normalizedPage().sheet_map.height,
      sha256: normalizedPage().sheet_map.sha256,
      page_no: normalizedPage().sheet_map.page_no,
      kind: "aligned_page"
    } : null);
    const activeImageUrl = derived(() => activeMode === "raw" ? normalizedPage()?.lazy_asset_urls?.input ?? null : normalizedPage()?.lazy_asset_urls?.aligned ?? null);
    const stencilTruthAvailable = derived(() => !blindReview() && sheetItems().some((item) => item.expected !== null && item.options.some((option) => option.region !== null)));
    const needsReviewCount = derived(() => normalizedPage()?.needs_review_total ?? normalizedPage()?.mismatch_total ?? 0);
    const activeFilter = derived(() => normalizeEvidenceFilter(normalizedPage()?.filter));
    const queueFilter = derived(() => activeFilter() === "all" ? "all" : "needs_review");
    let drafts = {};
    let saveErrors = {};
    let savingItemId = null;
    let savedReviews = {};
    let revealedItems = /* @__PURE__ */ new Set();
    let identityCorrection = "";
    let pendingNavigation = null;
    let selectedSheetItemId = null;
    let activeMode = run(() => normalizeMode(initialTab));
    let moreOpen = false;
    const dirty = derived(() => Object.keys(drafts).length > 0);
    function normalizeMode(value) {
      if (value === "captured" || value === "raw") return "raw";
      if (value === "recognition" || value === "markings") return "markings";
      return "stencil";
    }
    function itemView(item) {
      return normalizeEvidence({ items: [item] })?.items?.[0] ?? null;
    }
    function rowId(item) {
      return String(item?.item_id ?? item?.id ?? item?.label ?? "item");
    }
    function currentReview(item, view = itemView(item)) {
      const id = rowId(item);
      return savedReviews[id] ?? view?.review ?? null;
    }
    function itemMachine(item, view = itemView(item)) {
      return view?.machine ?? view?.detected ?? null;
    }
    function itemIsStale(item, view = itemView(item)) {
      return view ? reviewIsStale({ ...view, review: currentReview(item, view) }) : false;
    }
    function itemUnavailableReason(item, view = itemView(item)) {
      if (!view) return "missing-view";
      if (item.unavailable_reason) return String(item.unavailable_reason);
      if (item.unavailable === true) return "evidence-unavailable";
      if (item.geometry_stale === true) return "stale-geometry";
      return null;
    }
    function hasDraft(item) {
      return Boolean(drafts[rowId(item)]);
    }
    function setDraft(item, draft) {
      const id = rowId(item);
      drafts = { ...drafts, [id]: draft };
      saveErrors = { ...saveErrors, [id]: null };
    }
    function discardDrafts() {
      drafts = {};
      saveErrors = {};
      pendingNavigation = null;
    }
    function guardedNavigation(intent, action) {
      if (!dirty()) {
        action();
        return;
      }
      const callbackResult = onDirtyNavigation?.(intent);
      if (callbackResult === true) {
        discardDrafts();
        action();
        return;
      }
      pendingNavigation = { label: intent.label ?? "this change", action };
    }
    function reviewForDisplay(item, view) {
      const review = currentReview(item, view);
      return review && !itemIsStale(item, view) ? review : null;
    }
    function reviewLabel(decision) {
      return {
        confirm_machine: "Use detected answer",
        set_option: "Choose answer",
        blank: "Mark blank",
        unresolved: "Leave unresolved"
      }[decision] ?? decision ?? "Not reviewed";
    }
    function selectedMachineOption(item, view) {
      return itemMachine(item, view)?.selected_option ?? null;
    }
    function draftNeedsReason(item, draft, view = itemView(item)) {
      if (!draft) return false;
      const machineOption = selectedMachineOption(item, view);
      if (draft.decision === "unresolved") return true;
      if (draft.decision === "set_option") return draft.selected_option !== machineOption;
      return draft.decision === "blank" && machineOption !== null;
    }
    function draftNeedsNote(item, draft, view = itemView(item)) {
      return draft?.decision === "unresolved" || draftNeedsReason(item, draft, view);
    }
    function reviewHistory(item, view) {
      const current = currentReview(item, view);
      const history = Array.isArray(view?.review_history) ? view.review_history : [];
      const entries = current ? [current, ...history] : history;
      return entries.filter((entry, index, list) => index === list.findIndex((candidate) => candidate.revision != null && candidate.revision === entry.revision));
    }
    function selectArea(areaKey) {
      if (areaKey === (normalizedPage()?.area ?? "all")) return;
      guardedNavigation({ kind: "area", label: `the ${areaKey} area`, target: areaKey }, () => {
        if (onViewChange) {
          onViewChange({ area: areaKey, filter: queueFilter(), page: 1 });
        } else {
          onGroupChange?.(areaKey);
          onPageChange?.(1);
        }
      });
    }
    function rowUrl(item) {
      return typeof item.detail_url === "string" && item.detail_url ? item.detail_url : item.crop_asset?.url ?? null;
    }
    function rowCropRect(item) {
      return item.crop_rect ?? item.region ?? item.geometry?.crop_rect ?? null;
    }
    function fieldAspect(field) {
      const region = field?.region;
      const width = Number(normalizedPage()?.sheet_map?.width) || 1;
      const height = Number(normalizedPage()?.sheet_map?.height) || 1;
      return region ? Math.max(0.2, Number(region.w) * width / (Number(region.h) * height)) : 1;
    }
    function fieldImageStyle(field) {
      const region = field?.region;
      if (!region) return "";
      return `width:${100 / Number(region.w)}%;max-width:none;left:${-Number(region.x) / Number(region.w) * 100}%;top:${-Number(region.y) / Number(region.h) * 100}%;`;
    }
    function shortHash(value) {
      const hash = String(value ?? "");
      return hash.length > 12 ? `${hash.slice(0, 12)}…` : hash || "Unavailable";
    }
    function rowExpected(item) {
      if (blindReview() && !revealedItems.has(rowId(item))) return "Hidden until saved";
      return rowView(item)?.expected ?? "No expected answer";
    }
    function rowDetected(item) {
      const view = rowView(item);
      if (item.expected == null && (view?.machine == null || view.machine.selected_option == null)) return "—";
      return view ? detectedText(view) : stateLabel(item.state ?? null);
    }
    function rowView(item) {
      return itemView(item);
    }
    function problemLabel(item) {
      return {
        incorrect: "Incorrect",
        ambiguous: "Ambiguous",
        missing: "No answer detected"
      }[itemProblem(item)] ?? null;
    }
    function rowActionLabel(item, view = rowView(item)) {
      const displayedReview = reviewForDisplay(item, view);
      if (displayedReview?.decision && displayedReview.decision !== "unresolved") {
        return "Edit";
      }
      if (displayedReview?.decision === "unresolved") return "Mark";
      const machine = itemMachine(item, view);
      const issueCodes = [
        ...Array.isArray(machine?.issue_codes) ? machine.issue_codes : [],
        ...Array.isArray(view?.issues) ? view.issues.map((issue) => typeof issue === "string" ? issue : issue?.code ?? issue?.issue_code ?? "") : []
      ].map((code) => String(code).toLowerCase());
      const confidence = Number(machine?.confidence);
      const lowConfidence = issueCodes.includes("low_confidence") || Number.isFinite(confidence) && confidence < 0.8;
      const definite = machine?.state === "marked" && machine.selected_option !== null && machine.selected_option !== void 0 && machine.observed_options?.length === 1 && !lowConfidence;
      return definite ? "Edit" : "Mark";
    }
    function rowDisplayLabel(item, view = rowView(item)) {
      const area = item?.area_key ?? view?.area_key ?? null;
      if (item?.question_no !== null && item?.question_no !== void 0) {
        return area ? `${area} · Q${item.question_no}` : `Q${item.question_no}`;
      }
      return view?.label ?? item?.item_id ?? area ?? "Item";
    }
    function metadataValue(value) {
      if (value === null || value === void 0) return "—";
      const text = String(value).trim();
      return text || "—";
    }
    function scoreDisplay() {
      const score = contextSummary?.score;
      if (!score) return "—";
      if (score.display !== null && score.display !== void 0) {
        return metadataValue(score.display);
      }
      const earned = metadataValue(score.earned);
      const possible = metadataValue(score.possible);
      return earned === "—" && possible === "—" ? "—" : `${earned} / ${possible}`;
    }
    function pageLabel() {
      return "Evidence pages";
    }
    function paginationPages() {
      if (!normalizedPage()) return [];
      const count = normalizedPage().page_count;
      if (count <= 7) return Array.from({ length: count }, (_, index) => index + 1);
      const first = Math.max(1, Math.min(normalizedPage().page - 2, count - 4));
      return Array.from({ length: 5 }, (_, index) => first + index);
    }
    function returnHref() {
      if (!returnUrl || !sourceView()) return null;
      return mappingUrl(returnUrl, returnLabel(sourceView(), normalizeEvidenceFilter(normalizedPage()?.filter) === "mismatch" ? "problems" : "all"));
    }
    $$renderer2.push(`<div class="omr-evidence-panel text-foreground flex min-w-0 flex-col gap-4 text-sm svelte-12dtilv"${attr("data-omr-evidence-panel", normalizedPage()?.method ?? "empty")}>`);
    if (loading) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<div class="border-border bg-muted/20 text-muted-foreground flex items-center gap-2 rounded-lg border border-dashed p-4 text-xs svelte-12dtilv" role="status" aria-live="polite">`);
      Loader_circle($$renderer2, { class: "size-4 animate-spin", "aria-hidden": "true" });
      $$renderer2.push(`<!----> <span class="svelte-12dtilv">${escape_html(labels.loading ?? "Evidence is loading…")}</span></div>`);
    } else if (error) {
      $$renderer2.push("<!--[1-->");
      $$renderer2.push(`<div class="border-destructive/30 bg-destructive/5 text-destructive flex items-start gap-3 rounded-lg border p-4 text-xs svelte-12dtilv" role="alert">`);
      Circle_x($$renderer2, { class: "mt-0.5 size-4 shrink-0", "aria-hidden": "true" });
      $$renderer2.push(`<!----> <div class="min-w-0 flex-1 svelte-12dtilv"><p class="font-semibold svelte-12dtilv">${escape_html(labels.errorTitle ?? "Evidence could not be loaded")}</p> <p class="text-destructive/80 mt-1 svelte-12dtilv">${escape_html(typeof error === "string" ? error : error.detail ?? error.message ?? "The private evidence read failed.")}</p> `);
      if (onRetry) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="border-destructive/30 hover:bg-destructive/10 focus-visible:ring-ring mt-3 inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 font-semibold focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv">`);
        Refresh_cw($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer2.push(`<!----> ${escape_html(labels.retryLabel ?? "Retry")}</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></div>`);
    } else if (!normalizedPage()) {
      $$renderer2.push("<!--[2-->");
      $$renderer2.push(`<div class="border-border bg-muted/20 text-muted-foreground rounded-lg border border-dashed p-4 text-xs svelte-12dtilv" role="status" aria-live="polite">${escape_html(labels.empty ?? "No evidence is available for this capture yet.")} `);
      if (onRetry) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<button type="button" class="text-foreground focus-visible:ring-ring ml-2 inline-flex items-center gap-1 font-semibold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv">${escape_html(labels.retryLabel ?? "Retry")}</button>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div>`);
    } else {
      $$renderer2.push("<!--[-1-->");
      $$renderer2.push(`<div class="evidence-heading flex min-w-0 flex-wrap items-center justify-between gap-2 svelte-12dtilv"><div class="flex min-w-0 flex-wrap items-center gap-2 svelte-12dtilv"><h3 class="truncate text-sm font-bold svelte-12dtilv">${escape_html(labels.title ?? "Evidence")}</h3> `);
      if (blindReview()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:bg-amber-900/40 dark:text-amber-100 svelte-12dtilv">`);
        Eye_off($$renderer2, { class: "size-3", "aria-hidden": "true" });
        $$renderer2.push(`<!----> Blind</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (contextSummary?.simulated) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="inline-flex items-center rounded-full border border-amber-300/70 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:bg-amber-950/30 dark:text-amber-100 svelte-12dtilv">Simulated</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> `);
      if (returnHref()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<a class="text-primary focus-visible:ring-ring shrink-0 text-xs font-semibold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv"${attr("href", returnHref())}>${escape_html(labels.returnAction ?? "Return to mapping")}</a>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> `);
      if (blindReview()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="border-amber-300/50 bg-amber-50/70 px-2.5 py-1.5 text-[10px] text-amber-950 dark:bg-amber-950/20 dark:text-amber-100 svelte-12dtilv" role="status">`);
        Lock_keyhole($$renderer2, { class: "mr-1 inline size-3", "aria-hidden": "true" });
        $$renderer2.push(`<!----> Correctness stays hidden until a decision is saved.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (pendingNavigation) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-400/80 bg-amber-50 p-3 text-xs text-amber-950 dark:bg-amber-950/40 dark:text-amber-100 svelte-12dtilv" role="alertdialog"${attr("aria-labelledby", `${idPrefix}-discard-heading`)}${attr("aria-describedby", `${idPrefix}-discard-prompt`)}><div class="min-w-0 flex-1 svelte-12dtilv"><p${attr("id", `${idPrefix}-discard-heading`)} class="font-bold svelte-12dtilv">Unsaved review changes</p> <p${attr("id", `${idPrefix}-discard-prompt`)} class="mt-0.5 text-amber-900/90 dark:text-amber-200/90 svelte-12dtilv">You have unsaved changes in progress. Discard drafts
                        before navigating to ${escape_html(pendingNavigation.label)}?</p></div> <div class="flex items-center gap-2 svelte-12dtilv"><button type="button" class="border-border bg-background hover:bg-muted focus-visible:ring-ring rounded-md border px-3 py-1.5 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv">Continue editing</button> <button type="button" class="bg-destructive text-destructive-foreground hover:bg-destructive/90 focus-visible:ring-ring rounded-md px-3 py-1.5 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv">Discard changes</button></div></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <section${attr_class("ticket-summary border-border bg-card/80 rounded-xl border p-3 svelte-12dtilv", void 0, { "ticket-collapsed": mobileRailExpanded })}${attr("aria-labelledby", `${idPrefix}-ticket-heading`)}><h4${attr("id", `${idPrefix}-ticket-heading`)} class="sr-only svelte-12dtilv">Evidence ticket</h4> <div class="ticket-grid svelte-12dtilv"><div class="ticket-field ticket-identity min-w-0 svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Examinee ID</span> `);
      if (identityWriteIn()?.region && identityWriteIn()?.aligned_url) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="identity-write-in-preview mb-1.5 overflow-hidden rounded border border-border bg-background max-w-[14rem] svelte-12dtilv"><div class="relative overflow-hidden svelte-12dtilv"${attr_style(`aspect-ratio: ${fieldAspect({ region: identityWriteIn().region })}`)}><img${attr("src", identityWriteIn().aligned_url)} alt="Handwritten Applicant Number crop" class="absolute max-w-none select-none svelte-12dtilv"${attr_style(fieldImageStyle({ region: identityWriteIn().region }))} loading="lazy" decoding="async"/></div></div> `);
        if (identityWriteIn().page_no && normalizedPage()?.capture_page_no && identityWriteIn().page_no !== normalizedPage().capture_page_no) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="text-[9px] text-muted-foreground svelte-12dtilv">Captured on page ${escape_html(identityWriteIn().page_no)}</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (identitySummaryItems().length > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="identity-write-in svelte-12dtilv" role="group" aria-label="Examinee ID digit boxes"><!--[-->`);
        const each_array = ensure_array_like(identitySummaryItems());
        for (let index = 0, $$length = each_array.length; index < $$length; index++) {
          each_array[index];
          const digit = detectedIdentity()[index] ?? "·";
          $$renderer2.push(`<button type="button" class="identity-digit-box focus-visible:ring-ring svelte-12dtilv"${attr("aria-label", `Inspect examinee ID digit ${index + 1}, detected ${digit}`)}><span class="svelte-12dtilv">${escape_html(digit)}</span></button>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        if (Boolean(identityWriteIn()?.region)) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span class="svelte-12dtilv">Manual write-in ID</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<span class="ticket-value font-mono svelte-12dtilv">—</span>`);
        }
        $$renderer2.push(`<!--]-->`);
      }
      $$renderer2.push(`<!--]--></div> <div class="ticket-field svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Answer Sheet No.</span> <span class="ticket-value truncate svelte-12dtilv">${escape_html(metadataValue(contextSummary?.answer_sheet_number))}</span></div> <div class="ticket-field min-w-0 svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Applicant</span> `);
      if (candidateNameField()?.region && (candidateNameField().aligned_url || normalizedPage()?.lazy_asset_urls?.aligned)) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="candidate-name-preview mb-1 overflow-hidden rounded border border-border bg-background max-w-[14rem] svelte-12dtilv"><div class="relative overflow-hidden svelte-12dtilv"${attr_style(`aspect-ratio: ${fieldAspect(candidateNameField())}`)}><img${attr("src", candidateNameField().aligned_url || normalizedPage().lazy_asset_urls.aligned)} alt="Handwritten Applicant Name crop" class="absolute max-w-none select-none svelte-12dtilv"${attr_style(fieldImageStyle(candidateNameField()))} loading="lazy" decoding="async"/></div></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <span class="ticket-value truncate svelte-12dtilv">${escape_html(metadataValue(contextSummary?.applicant_name))}</span></div> <div class="ticket-field ticket-session svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Exam session</span> <span class="ticket-value truncate svelte-12dtilv">${escape_html(metadataValue(contextSummary?.exam_session))}</span></div> <div class="ticket-field ticket-schedule svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Schedule</span> <span class="ticket-value truncate svelte-12dtilv">${escape_html(metadataValue(contextSummary?.schedule))}</span></div> <div class="ticket-field ticket-room svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Room / seat</span> <span class="ticket-value truncate svelte-12dtilv">${escape_html(metadataValue(contextSummary?.room_seat))}</span></div> <div class="ticket-field ticket-score svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Score</span> <span class="ticket-value font-mono tabular-nums svelte-12dtilv">${escape_html(scoreDisplay())}</span></div></div> <div class="ticket-mobile-line svelte-12dtilv"${attr("aria-hidden", !mobileRailExpanded)}><span class="ticket-mobile-id svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Examinee ID</span> <span class="ticket-value font-mono svelte-12dtilv">${escape_html(detectedIdentity() || "—")}</span></span> <span class="ticket-mobile-applicant svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Applicant</span> <span class="ticket-value truncate svelte-12dtilv">${escape_html(metadataValue(contextSummary?.applicant_name))}</span></span> <span class="ticket-mobile-score svelte-12dtilv"><span class="ticket-label svelte-12dtilv">Score</span> <span class="ticket-value font-mono tabular-nums svelte-12dtilv">${escape_html(scoreDisplay())}</span></span></div> <details class="ticket-more border-border border-t svelte-12dtilv"${attr("open", moreOpen, true)}><summary class="text-primary focus-visible:ring-ring cursor-pointer px-1 pt-2 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv">View more</summary> <div class="ticket-more-content mt-2 grid gap-3 lg:grid-cols-2 svelte-12dtilv"><section class="identity-details border-border bg-background rounded-lg border p-3 svelte-12dtilv"${attr("aria-labelledby", `${idPrefix}-identity-details-heading`)}><div class="flex items-center justify-between gap-2 svelte-12dtilv"><div class="svelte-12dtilv"><h5${attr("id", `${idPrefix}-identity-details-heading`)} class="text-[10px] font-bold tracking-wide uppercase svelte-12dtilv">Examinee ID evidence</h5> <p class="text-muted-foreground mt-0.5 text-[10px] svelte-12dtilv">${escape_html(identityConfidence() === null ? "Confidence unavailable" : `${Math.round(identityConfidence() * 100)}% average confidence`)}</p></div> `);
      if (identitySummaryItems().length > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="text-muted-foreground text-[10px] svelte-12dtilv">${escape_html(identitySummaryItems().length)} digits</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> `);
      if (identitySummaryItems().length > 0) {
        $$renderer2.push("<!--[0-->");
        if (identityDetailsComplete() && isReviewable()) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end svelte-12dtilv"><label class="min-w-0 flex-1 text-[10px] font-semibold svelte-12dtilv"${attr("for", `${idPrefix}-identity-value`)}>Corrected value <input${attr("id", `${idPrefix}-identity-value`)} class="border-border bg-background focus-visible:ring-ring mt-1 block min-h-9 w-full rounded-md border px-3 font-mono text-sm tracking-[0.2em] focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv" inputmode="numeric" autocomplete="off"${attr("maxlength", identityItems().length)}${attr("value", identityCorrection)}/></label> <button type="button" class="bg-primary text-primary-foreground focus-visible:ring-ring inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md px-3 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 svelte-12dtilv"${attr("disabled", identityItems().length === 0, true)}>`);
          Save($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
          $$renderer2.push(`<!----> ${escape_html("Save corrected ID")}</button></div> `);
          {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> `);
          if (identityDraftCount() > 0) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<p class="text-muted-foreground mt-2 text-[10px] svelte-12dtilv">${escape_html(identityDraftCount())} changed ${escape_html(identityDraftCount() === 1 ? "digit" : "digits")} staged for review.</p>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]-->`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<button type="button" class="border-primary/30 text-primary hover:bg-primary/5 focus-visible:ring-ring mt-3 inline-flex min-h-8 items-center justify-center rounded-md border px-2.5 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv">${escape_html(normalizedPage().area === "identity" ? "Load Identity details" : "Open Identity focus")}</button>`);
        }
        $$renderer2.push(`<!--]--> `);
        if (normalizedPage().area === "identity" && identityItems().length > 0) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<details class="border-border mt-3 rounded-md border p-2 svelte-12dtilv"><summary class="cursor-pointer text-[10px] font-semibold svelte-12dtilv">Inspect per-digit bubble evidence <span class="text-muted-foreground ml-1 font-normal svelte-12dtilv">(${escape_html(identityItems().length)})</span></summary> `);
          if (identityDetailsComplete()) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 svelte-12dtilv"><!--[-->`);
            const each_array_1 = ensure_array_like(identityItems());
            for (let index = 0, $$length = each_array_1.length; index < $$length; index++) {
              let item = each_array_1[index];
              const view = itemView(item);
              $$renderer2.push(`<details class="border-border bg-muted/15 rounded-md border p-1.5 svelte-12dtilv"><summary class="flex cursor-pointer items-center justify-between gap-1 text-[9px] font-semibold svelte-12dtilv"><span class="svelte-12dtilv">Digit ${escape_html(index + 1)}</span> <span class="font-mono svelte-12dtilv">${escape_html(itemMachine(item, view)?.selected_option ?? "—")}</span></summary> <div class="mt-1 max-h-56 overflow-y-auto overscroll-contain svelte-12dtilv">`);
              OmrRegionCrop($$renderer2, {
                item: view,
                machine: view?.machine,
                expected: blindReview() ? null : view?.expected_evidence,
                geometry: view?.geometry,
                asset: view?.provenance?.asset,
                page_asset: view?.provenance?.page_asset ?? sheetAsset(),
                crop_source: view?.provenance?.crop_source,
                cropUrl: rowUrl(item),
                imageUrl: activeImageUrl(),
                region: rowCropRect(item),
                options: view?.options ?? [],
                detectedOption: view?.machine?.selected_option ?? null,
                expectedOption: blindReview() ? null : view?.expected ?? null,
                state: view?.machine?.state ?? null,
                mode: activeMode,
                alt: `Bubble evidence for examinee ID digit ${index + 1}`,
                title: `Examinee ID digit ${index + 1}`,
                width: 120,
                id: `${idPrefix}-identity-crop-${rowId(item)}`
              });
              $$renderer2.push(`<!----></div></details>`);
            }
            $$renderer2.push(`<!--]--></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<p class="text-muted-foreground mt-2 text-[10px] svelte-12dtilv">Choose Identity focus to load all
                                            digit evidence before correcting the
                                            identifier.</p>`);
          }
          $$renderer2.push(`<!--]--></details>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]-->`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<p class="text-muted-foreground mt-3 text-[10px] svelte-12dtilv">Examinee ID evidence is not present in this
                                capture projection.</p>`);
      }
      $$renderer2.push(`<!--]--></section> <section class="document-summary border-border bg-background rounded-lg border p-3 svelte-12dtilv"${attr("aria-labelledby", `${idPrefix}-document-fields-heading`)}><div class="flex items-center justify-between gap-2 svelte-12dtilv"><div class="svelte-12dtilv"><h5${attr("id", `${idPrefix}-document-fields-heading`)} class="text-[10px] font-bold tracking-wide uppercase svelte-12dtilv">Document fields</h5> <p class="text-muted-foreground mt-0.5 text-[10px] svelte-12dtilv">Image-only crops</p></div> <span class="text-muted-foreground text-[10px] svelte-12dtilv">${escape_html(documentFields().length)}</span></div> `);
      if (documentFields().length > 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="mt-2 grid grid-cols-2 gap-2 svelte-12dtilv"><!--[-->`);
        const each_array_2 = ensure_array_like(documentFields());
        for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
          let field = each_array_2[$$index_2];
          $$renderer2.push(`<figure class="border-border bg-muted/20 overflow-hidden rounded-md border svelte-12dtilv"><figcaption class="border-border bg-background border-b px-2 py-1 text-[9px] font-semibold svelte-12dtilv">${escape_html(field.label)}</figcaption> `);
          if (normalizedPage().lazy_asset_urls.aligned && field.region) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="relative overflow-hidden svelte-12dtilv"${attr_style(`aspect-ratio:${fieldAspect(field)}`)}><img${attr("src", normalizedPage().lazy_asset_urls.aligned)}${attr("alt", `Image crop for ${field.label}; value not transcribed`)} class="absolute max-w-none select-none svelte-12dtilv"${attr_style(fieldImageStyle(field))} loading="lazy" decoding="async"/></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
            $$renderer2.push(`<div class="text-muted-foreground flex min-h-12 items-center justify-center p-2 text-center text-[9px] svelte-12dtilv">Image unavailable</div>`);
          }
          $$renderer2.push(`<!--]--></figure>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<p class="text-muted-foreground mt-3 text-[10px] svelte-12dtilv">No document field crops were supplied.</p>`);
      }
      $$renderer2.push(`<!--]--></section></div></details></section> <div class="workbench-grid svelte-12dtilv"><section class="canvas-column min-w-0 svelte-12dtilv"${attr("aria-labelledby", `${idPrefix}-canvas-heading`)}><div class="canvas-toolbar border-border bg-background flex min-h-10 items-center justify-between gap-2 rounded-t-xl border border-b-0 px-3 py-2 svelte-12dtilv"><h4${attr("id", `${idPrefix}-canvas-heading`)} class="truncate text-xs font-bold sm:text-sm svelte-12dtilv">Sheet canvas</h4> `);
      if (blindReview()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<span class="text-muted-foreground inline-flex items-center gap-1 text-[10px] svelte-12dtilv">`);
        Lock_keyhole($$renderer2, { class: "size-3", "aria-hidden": "true" });
        $$renderer2.push(`<!----> Blind</span>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div> `);
      if (activeMode === "stencil" && blindReview()) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<p class="border-amber-300/50 bg-amber-50 px-3 py-2 text-[10px] text-amber-950 dark:bg-amber-950/20 dark:text-amber-100 svelte-12dtilv" role="status">`);
        Lock_keyhole($$renderer2, { class: "mr-1 inline size-3.5", "aria-hidden": "true" });
        $$renderer2.push(`<!----> Stencil is unavailable during blind review.</p>`);
      } else if (activeMode === "stencil" && !stencilTruthAvailable()) {
        $$renderer2.push("<!--[1-->");
        $$renderer2.push(`<p class="border-amber-300/50 bg-amber-50 px-3 py-2 text-[10px] text-amber-950 dark:bg-amber-950/20 dark:text-amber-100 svelte-12dtilv" role="status">`);
        Circle_dot_dashed($$renderer2, { class: "mr-1 inline size-3.5", "aria-hidden": "true" });
        $$renderer2.push(`<!----> Stencil is unavailable without expected bubble geometry.</p>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      OmrSheetEvidenceCanvas($$renderer2, {
        sheetMap: normalizedPage().sheet_map,
        imageUrl: activeImageUrl(),
        mode: activeMode,
        selectedId: selectedSheetItemId,
        blind: blindReview()
      });
      $$renderer2.push(`<!----></section> <aside${attr_class("review-rail border-border bg-card min-w-0 rounded-xl border svelte-12dtilv", void 0, { "mobile-rail-expanded": mobileRailExpanded })} aria-label="Evidence review queue" data-review-rail=""><div${attr("id", `${idPrefix}-rail-body`)} class="review-rail-body svelte-12dtilv"><div class="rail-controls border-border bg-card flex min-h-11 shrink-0 items-center gap-1.5 overflow-x-auto border-b px-2 py-1.5 sm:px-3 svelte-12dtilv" role="toolbar" aria-label="Evidence queue controls">`);
      if (normalizedPage()?.groups?.length > 1) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="queue-area-select flex items-center gap-1 svelte-12dtilv"><label${attr("for", `${idPrefix}-area-filter`)} class="sr-only svelte-12dtilv">Filter by area</label> `);
        $$renderer2.select(
          {
            id: `${idPrefix}-area-filter`,
            class: "border-border bg-background text-foreground h-7 rounded border px-1.5 py-0.5 text-[10px] font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none",
            value: normalizedPage().area ?? "all",
            onchange: (e) => selectArea(e.currentTarget.value)
          },
          ($$renderer3) => {
            $$renderer3.push(`<!--[-->`);
            const each_array_3 = ensure_array_like(normalizedPage().groups);
            for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
              let group = each_array_3[$$index_3];
              $$renderer3.option(
                { value: group.key, class: "" },
                ($$renderer4) => {
                  $$renderer4.push(`${escape_html(group.label)} (${escape_html(group.total)})`);
                },
                "svelte-12dtilv"
              );
            }
            $$renderer3.push(`<!--]-->`);
          },
          "svelte-12dtilv"
        );
        $$renderer2.push(`</div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> <div class="queue-filter flex shrink-0 items-center gap-0.5 svelte-12dtilv" role="group" aria-label="Review queue filter"><button type="button"${attr_class(
        `focus-visible:ring-ring inline-flex min-h-7 items-center gap-0.5 rounded px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none ${queueFilter() === "all" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:bg-background"}`,
        "svelte-12dtilv"
      )}${attr("aria-pressed", queueFilter() === "all")}>All <span class="text-[9px] opacity-70 svelte-12dtilv">${escape_html(normalizedPage().total)}</span></button> <button type="button"${attr_class(
        `focus-visible:ring-ring inline-flex min-h-7 items-center gap-0.5 rounded px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none ${queueFilter() === "needs_review" ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:bg-background"}`,
        "svelte-12dtilv"
      )}${attr("aria-pressed", queueFilter() === "needs_review")}${attr("disabled", needsReviewCount() === 0, true)}>Needs review <span class="text-[9px] opacity-70 svelte-12dtilv">${escape_html(needsReviewCount())}</span></button></div> <div class="mode-segmented border-border bg-muted/35 flex shrink-0 items-center gap-0.5 rounded-md border p-0.5 svelte-12dtilv" role="tablist" aria-label="Evidence display mode"><!--[-->`);
      const each_array_4 = ensure_array_like([
        { key: "raw", label: "Raw", icon: Image },
        { key: "stencil", label: "Stencil", icon: Scan_line },
        { key: "markings", label: "Markings", icon: Layers }
      ]);
      for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
        let mode = each_array_4[$$index_4];
        $$renderer2.push(`<button type="button" role="tab"${attr("aria-selected", activeMode === mode.key)}${attr_class(
          `focus-visible:ring-ring inline-flex min-h-7 items-center gap-1 rounded px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none ${activeMode === mode.key ? "bg-background text-foreground shadow-sm" : "text-muted-foreground hover:text-foreground"}`,
          "svelte-12dtilv"
        )}${attr("aria-label", `${mode.label} evidence`)}>`);
        if (mode.icon) {
          $$renderer2.push("<!--[-->");
          mode.icon($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push("<!--]-->");
        } else {
          $$renderer2.push("<!--[!-->");
          $$renderer2.push("<!--]-->");
        }
        $$renderer2.push(` <span class="svelte-12dtilv">${escape_html(mode.label)}</span></button>`);
      }
      $$renderer2.push(`<!--]--></div> <button type="button" class="rail-expand-button border-border bg-background hover:bg-muted focus-visible:ring-ring ml-auto inline-flex size-8 shrink-0 items-center justify-center rounded-md border focus-visible:ring-2 focus-visible:outline-none lg:hidden svelte-12dtilv"${attr("aria-expanded", mobileRailExpanded)}${attr("aria-controls", `${idPrefix}-rail-body`)}${attr("aria-label", mobileRailExpanded ? "Collapse evidence review queue" : "Expand evidence review queue")}>`);
      if (mobileRailExpanded) {
        $$renderer2.push("<!--[0-->");
        Chevron_down($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
      } else {
        $$renderer2.push("<!--[-1-->");
        Chevron_up($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
      }
      $$renderer2.push(`<!--]--></button></div> <div class="queue-scroll px-2 py-2 svelte-12dtilv">`);
      if (visibleItems().length === 0) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<div class="border-border text-muted-foreground rounded-md border border-dashed p-3 text-center text-[10px] svelte-12dtilv" role="status">${escape_html(labels.noRows ?? "No evidence rows match this view.")}</div>`);
      } else {
        $$renderer2.push("<!--[-1-->");
        $$renderer2.push(`<div${attr_class("queue-table svelte-12dtilv", void 0, { "queue-read-only": !isReviewable() })} role="table" aria-label="Evidence review queue"><div class="queue-header text-muted-foreground svelte-12dtilv" role="row"><span role="columnheader" class="svelte-12dtilv">Item</span> <span role="columnheader" class="svelte-12dtilv">Evidence</span> <span role="columnheader" class="svelte-12dtilv">Result</span> `);
        if (isReviewable()) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<span role="columnheader" class="sr-only svelte-12dtilv">Action</span>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--></div> <!--[-->`);
        const each_array_5 = ensure_array_like(visibleItems());
        for (let index = 0, $$length = each_array_5.length; index < $$length; index++) {
          let item = each_array_5[index];
          const itemId = rowId(item);
          const normalizedItem = itemView(item);
          const displayedReview = normalizedItem ? reviewForDisplay(item, normalizedItem) : null;
          const problem = normalizedItem ? problemLabel(normalizedItem) : null;
          const selected = selectedSheetItemId === itemId;
          const prevItem = index > 0 ? visibleItems()[index - 1] : null;
          const isNewArea = index === 0 || item.area_key !== prevItem?.area_key;
          if (isNewArea && item.area_key && item.group_type !== "identity") {
            $$renderer2.push("<!--[0-->");
            const areaGroup = normalizedPage()?.groups?.find((g) => g.key === item.area_key || g.area_key === item.area_key);
            $$renderer2.push(`<div class="queue-section-header border-y border-border/80 bg-muted/40 px-2.5 py-1 text-[10px] font-bold text-foreground flex items-center justify-between col-span-full svelte-12dtilv" role="row"><span role="columnheader" class="svelte-12dtilv">${escape_html(item.area_key)}</span> `);
            if (areaGroup?.total) {
              $$renderer2.push("<!--[0-->");
              $$renderer2.push(`<span class="text-[9px] font-normal text-muted-foreground svelte-12dtilv">${escape_html(areaGroup.total)} items</span>`);
            } else {
              $$renderer2.push("<!--[-1-->");
            }
            $$renderer2.push(`<!--]--></div>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> <div${attr("id", `${idPrefix}-row-${itemId}`)}${attr_class("queue-row border-border/70 svelte-12dtilv", void 0, { "queue-selected": selected })} role="row"${attr("aria-selected", selected)}><button type="button" class="queue-item focus-visible:ring-ring min-w-0 text-left focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv" role="cell"><span class="block truncate text-[10px] font-semibold svelte-12dtilv">${escape_html(rowDisplayLabel(item, normalizedItem))}</span> `);
          if (problem) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span class="text-destructive block text-[9px] font-semibold svelte-12dtilv">${escape_html(problem)}</span>`);
          } else if (displayedReview) {
            $$renderer2.push("<!--[1-->");
            $$renderer2.push(`<span class="block text-[9px] font-semibold text-emerald-700 dark:text-emerald-300 svelte-12dtilv">Reviewed</span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></button> <div class="queue-bubbles svelte-12dtilv" role="cell">`);
          OmrRegionCrop($$renderer2, {
            item: normalizedItem,
            machine: normalizedItem?.machine,
            expected: blindReview() && !revealedItems.has(itemId) ? null : normalizedItem?.expected_evidence,
            geometry: normalizedItem?.geometry,
            review: reviewForDisplay(item, normalizedItem),
            asset: normalizedItem?.provenance?.asset,
            page_asset: normalizedItem?.provenance?.page_asset ?? sheetAsset(),
            crop_source: normalizedItem?.provenance?.crop_source,
            cropUrl: rowUrl(item),
            imageUrl: activeImageUrl(),
            region: rowCropRect(item),
            options: normalizedItem?.options ?? [],
            detectedOption: normalizedItem?.machine?.selected_option ?? null,
            expectedOption: blindReview() && !revealedItems.has(itemId) ? null : normalizedItem?.expected ?? null,
            state: normalizedItem?.machine?.state ?? item.state ?? null,
            mode: activeMode,
            alt: `Scanned bubbles for ${normalizedItem?.label ?? item.item_id ?? "item"}`,
            title: normalizedItem?.label ?? item.item_id ?? "Scanned bubbles",
            width: 112,
            id: `${idPrefix}-crop-${itemId}`
          });
          $$renderer2.push(`<!----></div> <div class="queue-result min-w-0 svelte-12dtilv" role="cell"${attr("aria-label", `Detected ${rowDetected(item)}; expected ${rowExpected(item)}`)}><span class="block truncate font-mono text-[10px] svelte-12dtilv">${escape_html(rowDetected(item))}</span> <span class="text-muted-foreground block truncate text-[9px] svelte-12dtilv">Expected: ${escape_html(rowExpected(item))}</span> `);
          if (normalizedItem?.score != null) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<span class="text-muted-foreground block text-[9px] svelte-12dtilv">${escape_html(normalizedItem.score)} pts</span>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div> `);
          if (isReviewable() && item.reviewable !== false) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<button type="button" class="queue-edit text-primary focus-visible:ring-ring rounded px-1.5 py-1 text-[10px] font-bold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv"${attr("aria-label", `${rowActionLabel(item, normalizedItem)} ${rowDisplayLabel(item, normalizedItem)} ${item.item_id ?? ""}`.trim())}>${escape_html(rowActionLabel(item, normalizedItem))}</button>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div>`);
        }
        $$renderer2.push(`<!--]--></div>`);
      }
      $$renderer2.push(`<!--]--></div> `);
      if (selectedEditorItem()) {
        $$renderer2.push("<!--[0-->");
        const editorId = rowId(selectedEditorItem());
        const editorView = selectedEditorView();
        const draft = drafts[editorId] ?? null;
        const saveError = saveErrors[editorId] ?? null;
        const displayedReview = editorView ? reviewForDisplay(selectedEditorItem(), editorView) : null;
        const unavailableReason = editorView ? itemUnavailableReason(selectedEditorItem(), editorView) : "missing-view";
        $$renderer2.push(`<section class="inline-editor border-border bg-background border-t px-3 py-3 svelte-12dtilv"${attr("aria-label", `Edit ${rowDisplayLabel(selectedEditorItem(), editorView)}`)}><div class="flex items-start justify-between gap-2 svelte-12dtilv"><div class="min-w-0 svelte-12dtilv"><p class="truncate text-xs font-bold svelte-12dtilv">${escape_html(rowDisplayLabel(selectedEditorItem(), editorView))}</p> <p class="text-muted-foreground mt-0.5 text-[10px] svelte-12dtilv">Detected ${escape_html(rowDetected(selectedEditorItem()))} · Expected ${escape_html(rowExpected(selectedEditorItem()))}</p></div> <button type="button" class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex size-7 shrink-0 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none svelte-12dtilv" aria-label="Close inline editor">`);
        X($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
        $$renderer2.push(`<!----></button></div> `);
        if (unavailableReason) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="border-destructive/30 bg-destructive/5 text-destructive mt-2 rounded-md border p-2 text-[10px] svelte-12dtilv" role="alert"><p class="font-semibold svelte-12dtilv">Evidence unavailable: ${escape_html(unavailableReason)}</p> <p class="mt-0.5 svelte-12dtilv">Review save is blocked until provenance
                                        and geometry are available.</p></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (displayedReview) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="mt-2 rounded-md border border-emerald-300/50 bg-emerald-50/50 p-2 text-[10px] dark:bg-emerald-950/20 svelte-12dtilv" aria-label="Current review"><p class="font-semibold svelte-12dtilv">Current review · ${escape_html(reviewLabel(displayedReview.decision))}</p> <p class="text-muted-foreground mt-1 svelte-12dtilv">${escape_html(displayedReview.selected_option ?? "Blank/unresolved")} · ${escape_html(displayedReview.reviewer?.display_name ?? "Reviewer unavailable")} · ${escape_html(displayedReview.reviewed_at ?? "Timestamp unavailable")}</p> <p class="text-muted-foreground mt-1 svelte-12dtilv">Reason ${escape_html(displayedReview.reason_code ?? "Not required")} · evidence ${escape_html(shortHash(displayedReview.evidence_hash))}</p></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (reviewHistory(selectedEditorItem(), editorView).length > 0) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<details class="border-border mt-2 rounded-md border p-2 text-[10px] svelte-12dtilv"><summary class="cursor-pointer font-semibold svelte-12dtilv">Review history (${escape_html(reviewHistory(selectedEditorItem(), editorView).length)})</summary> <ol class="mt-2 space-y-1.5 svelte-12dtilv" aria-label="Immutable review history"><!--[-->`);
          const each_array_6 = ensure_array_like(reviewHistory(selectedEditorItem(), editorView));
          for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
            let historyEntry = each_array_6[$$index_6];
            $$renderer2.push(`<li class="border-border/70 border-l-2 pl-2 svelte-12dtilv"><strong class="svelte-12dtilv">Revision ${escape_html(historyEntry.revision ?? "—")} · ${escape_html(reviewLabel(historyEntry.decision))}</strong> <span class="text-muted-foreground block svelte-12dtilv">${escape_html(historyEntry.reviewer?.display_name ?? "Reviewer unavailable")} ·
                                                    ${escape_html(historyEntry.reviewed_at ?? "Timestamp unavailable")} ·
                                                    ${escape_html(shortHash(historyEntry.evidence_hash))}</span> `);
            if (historyEntry.stale) {
              $$renderer2.push("<!--[0-->");
              $$renderer2.push(`<span class="text-amber-700 svelte-12dtilv">Stale history</span>`);
            } else {
              $$renderer2.push("<!--[-1-->");
            }
            $$renderer2.push(`<!--]--></li>`);
          }
          $$renderer2.push(`<!--]--></ol></details>`);
        } else {
          $$renderer2.push("<!--[-1-->");
        }
        $$renderer2.push(`<!--]--> `);
        if (isReviewable() && editorView?.reviewable !== false) {
          $$renderer2.push("<!--[0-->");
          $$renderer2.push(`<div class="review-editor-controls border-primary/20 bg-primary/5 mt-2 rounded-md border p-2.5 svelte-12dtilv" aria-label="Review decision controls"><p class="text-muted-foreground mb-2 text-[10px] svelte-12dtilv">Choose a decision, then save explicitly.
                                        Machine evidence remains unchanged.</p> <div class="grid grid-cols-2 gap-1.5 svelte-12dtilv"><button type="button" class="border-border bg-background focus-visible:ring-ring inline-flex min-h-8 items-center justify-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 svelte-12dtilv"${attr("disabled", editorView?.machine?.state !== "marked" || savingItemId === editorId, true)}${attr("aria-pressed", draft?.decision === "confirm_machine")}>`);
          Check($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push(`<!----> Use detected</button> <button type="button" class="border-border bg-background focus-visible:ring-ring inline-flex min-h-8 items-center justify-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 svelte-12dtilv"${attr("disabled", savingItemId === editorId, true)}${attr("aria-pressed", draft?.decision === "set_option")}>Choose answer</button> <button type="button" class="border-border bg-background focus-visible:ring-ring inline-flex min-h-8 items-center justify-center rounded-md border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 svelte-12dtilv"${attr("disabled", savingItemId === editorId, true)}${attr("aria-pressed", draft?.decision === "blank")}>Mark blank</button> <button type="button" class="border-border bg-background focus-visible:ring-ring inline-flex min-h-8 items-center justify-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 svelte-12dtilv"${attr("disabled", savingItemId === editorId, true)}${attr("aria-pressed", draft?.decision === "unresolved")}>`);
          Circle_alert($$renderer2, { class: "size-3", "aria-hidden": "true" });
          $$renderer2.push(`<!----> Unresolved</button></div> `);
          if (draft?.decision === "set_option") {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<fieldset class="mt-2 svelte-12dtilv"><legend class="text-muted-foreground text-[10px] font-semibold svelte-12dtilv">Pinned options</legend> <div class="mt-1 flex flex-wrap gap-1.5 svelte-12dtilv" role="radiogroup" aria-label="Pinned options"><!--[-->`);
            const each_array_7 = ensure_array_like(editorView?.options ?? []);
            for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
              let option = each_array_7[$$index_7];
              $$renderer2.push(`<label class="border-border bg-background inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-1 text-[10px] svelte-12dtilv"><input type="radio"${attr("name", `${idPrefix}-option-${editorId}`)}${attr("value", option.code)}${attr("checked", draft.selected_option === option.code, true)} class="svelte-12dtilv"/> <span class="font-mono svelte-12dtilv">${escape_html(option.code)}</span></label>`);
            }
            $$renderer2.push(`<!--]--></div></fieldset>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--> `);
          if (draft) {
            $$renderer2.push("<!--[0-->");
            $$renderer2.push(`<div class="mt-2 grid gap-2 svelte-12dtilv"><label class="text-[10px] font-semibold svelte-12dtilv"${attr("for", `${idPrefix}-reason-${editorId}`)}>Reason ${escape_html(draftNeedsReason(selectedEditorItem(), draft, editorView) ? "(required)" : "(optional)")}</label> `);
            $$renderer2.select(
              {
                id: `${idPrefix}-reason-${editorId}`,
                class: "border-border bg-background rounded-md border px-2 py-1.5 text-[10px]",
                value: draft.reason_code ?? "",
                onchange: (event) => setDraft(selectedEditorItem(), { ...draft, reason_code: event.currentTarget.value || null })
              },
              ($$renderer3) => {
                $$renderer3.option(
                  { value: "", class: "" },
                  ($$renderer4) => {
                    $$renderer4.push(`Select a reason`);
                  },
                  "svelte-12dtilv"
                );
                $$renderer3.push(`<!--[-->`);
                const each_array_8 = ensure_array_like(reasons());
                for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
                  let reason = each_array_8[$$index_8];
                  $$renderer3.option(
                    { value: reason.code, class: "" },
                    ($$renderer4) => {
                      $$renderer4.push(`${escape_html(reason.label)}`);
                    },
                    "svelte-12dtilv"
                  );
                }
                $$renderer3.push(`<!--]-->`);
              },
              "svelte-12dtilv"
            );
            $$renderer2.push(` <label class="text-[10px] font-semibold svelte-12dtilv"${attr("for", `${idPrefix}-note-${editorId}`)}>Note ${escape_html(draftNeedsNote(selectedEditorItem(), draft, editorView) ? "(required)" : "(optional)")}</label> <textarea${attr("id", `${idPrefix}-note-${editorId}`)} class="border-border bg-background min-h-16 rounded-md border px-2 py-1.5 text-[10px] svelte-12dtilv"${attr("maxlength", noteMax())} placeholder="Keep the explanation concise">`);
            const $$body = escape_html(draft.note ?? "");
            if ($$body) {
              $$renderer2.push(`${$$body}`);
            }
            $$renderer2.push(`</textarea> <span class="text-muted-foreground svelte-12dtilv">${escape_html(String(draft.note ?? "").length)}/${escape_html(noteMax())}</span></div> `);
            if (saveError) {
              $$renderer2.push("<!--[0-->");
              $$renderer2.push(`<p class="text-destructive mt-2 text-[10px] svelte-12dtilv" role="alert">${escape_html(saveError.kind)}: ${escape_html(saveError.detail)}</p>`);
            } else {
              $$renderer2.push("<!--[-1-->");
            }
            $$renderer2.push(`<!--]--> <button type="button" class="bg-primary text-primary-foreground focus-visible:ring-ring mt-2 inline-flex min-h-9 w-full items-center justify-center gap-1 rounded-md px-3 py-1.5 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 svelte-12dtilv"${attr("disabled", savingItemId === editorId, true)}>`);
            Save($$renderer2, { class: "size-3.5", "aria-hidden": "true" });
            $$renderer2.push(`<!----> ${escape_html(savingItemId === editorId ? "Saving…" : "Save")}</button> <p class="text-muted-foreground text-[10px] svelte-12dtilv" aria-live="polite">Reviewed appears only after
                                            authoritative server state returns.</p>`);
          } else {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></div>`);
        } else {
          $$renderer2.push("<!--[-1-->");
          $$renderer2.push(`<p class="text-muted-foreground border-border mt-2 rounded-md border border-dashed p-2 text-[10px] svelte-12dtilv">${escape_html(normalizedPage().lifecycle && normalizedPage().lifecycle !== "open" ? "This evidence context is closed; history is read-only." : "Read-only evidence. Review controls appear only when an authorized save callback is supplied.")}</p>`);
        }
        $$renderer2.push(`<!--]--></section>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--> `);
      if (normalizedPage().page_count > 1) {
        $$renderer2.push("<!--[0-->");
        $$renderer2.push(`<nav class="border-border flex items-center justify-between gap-2 border-t px-3 py-2 svelte-12dtilv"${attr("aria-label", labels.paginationLabel ?? "Evidence pagination")}><button type="button" class="border-border focus-visible:ring-ring inline-flex min-h-8 items-center rounded-md border px-2.5 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 svelte-12dtilv"${attr("disabled", loading || normalizedPage().page <= 1 || !onPageChange, true)} aria-label="Previous evidence page">Previous</button> <div class="flex items-center gap-1 svelte-12dtilv"${attr("aria-label", pageLabel())}><!--[-->`);
        const each_array_9 = ensure_array_like(paginationPages());
        for (let $$index_9 = 0, $$length = each_array_9.length; $$index_9 < $$length; $$index_9++) {
          let pageNumber = each_array_9[$$index_9];
          $$renderer2.push(`<button type="button"${attr_class(
            `focus-visible:ring-ring inline-flex size-7 items-center justify-center rounded-md text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none ${pageNumber === normalizedPage().page ? "bg-primary text-primary-foreground" : "border-border bg-background hover:bg-muted border"}`,
            "svelte-12dtilv"
          )}${attr("aria-label", `Evidence page ${pageNumber}`)}${attr("aria-current", pageNumber === normalizedPage().page ? "page" : void 0)}>${escape_html(pageNumber)}</button>`);
        }
        $$renderer2.push(`<!--]--></div> <button type="button" class="border-border focus-visible:ring-ring inline-flex min-h-8 items-center rounded-md border px-2.5 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 svelte-12dtilv"${attr("disabled", loading || normalizedPage().page >= normalizedPage().page_count || !onPageChange, true)} aria-label="Next evidence page">Next</button></nav>`);
      } else {
        $$renderer2.push("<!--[-1-->");
      }
      $$renderer2.push(`<!--]--></div></aside></div>`);
    }
    $$renderer2.push(`<!--]--></div>`);
    bind_props($$props, { mobileRailExpanded });
  });
}
export {
  OmrRegionCrop as O,
  OmrDiagnosticEvidencePanel as a,
  computeSummary as c,
  normalizeItem as n
};
