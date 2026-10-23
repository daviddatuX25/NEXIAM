import { g as sanitize_props, i as spread_props, j as slot, l as bind_props, b as attr, e as escape_html, B as Button, a as ensure_array_like, d as derived, c as attr_class, n as newIdempotencyKey } from "../app.js";
import { S as StaffLayout } from "./StaffLayout-CR-MZopa.js";
import { P as PageHeaderBar } from "./PageHeaderBar-DJhT5vOR.js";
import { I as Input } from "./input-BUZAZKIK.js";
import { T as Textarea } from "./textarea-Bv3Na1wO.js";
import { T as Tabs, a as Tabs_list, b as Tabs_trigger, c as Tabs_content } from "./tabs-trigger-DxnTaeSp.js";
import { D as Dialog, a as Dialog_content, b as Dialog_header, c as Dialog_title, d as Dialog_description } from "./dialog-description-D12L0QA9.js";
import { D as Dialog_footer } from "./dialog-footer-kNnwKT2h.js";
import { S as Sticky_note } from "./sticky-note-CKw7DId8.js";
import { I as Icon } from "./Icon-Dal7PqKK.js";
import { H as History } from "./history-D15LFKXr.js";
import { E as Ellipsis } from "./ellipsis-CPRDNN0k.js";
import { C as Copy } from "./copy-D9_YDhto.js";
import { U as Undo_2 } from "./undo-2-CzBOZ6l_.js";
import { T as TabFmResultView } from "./TabFmResultView-ykz3bqxf.js";
import { G as GovernanceDiscussionPanel } from "./GovernanceDiscussionPanel-B64Kd0LL.js";
import { f as focusGovernanceTarget, c as beginGovernanceRequest, g as governanceRequest, a as governanceRoute, i as isCurrentGovernanceRequest, b as governanceError, G as GovernanceRequestError, r as reusePendingGovernancePayload, d as invalidatePreview, e as isUncertainCommandOutcome, h as applyGovernanceNoteDetail, j as reconcileGovernanceNoteEditor } from "./governanceRequests-C1KnF59a.js";
import { L as Link } from "./Link-B7ks7rBK.js";
import { router } from "@inertiajs/core";
import "axios";
import "clsx";
import "laravel-precognition";
import "tailwind-merge";
import "tailwind-variants";
import "./ToastManager-C7oO_4Jn.js";
import "./circle-x-BQrhluGu.js";
import "./triangle-alert-CUvuB-HL.js";
import "./info-C3MCzlQW.js";
import "./x-CnxrMbMn.js";
import "./NotificationBell-Cgc59min.js";
import "./PreferencesMenu-CCPeta-5.js";
import "./watch.svelte-BcPoqckX.js";
import "./is-C6cm64pF.js";
import "./create-id-CUJmDlea.js";
import "style-to-object";
import "./scroll-lock-B1ibcKMx.js";
import "./noop-BQS7OFs8.js";
import "tabbable";
import "@floating-ui/dom";
import "./hidden-input-GSZDfM3n.js";
import "./InstitutionalBackdrop-BjxeRqDu.js";
import "./zoom-out-DsJCISKt.js";
import "./rotate-ccw-D6Dm_efx.js";
import "./inbox-AmUVtgA2.js";
import "./mail-DBtkjaCt.js";
import "./AvatarBadge-DQ5Qru9A.js";
import "./badge-D9Vgs7xh.js";
import "./circle-alert-jWXJ7oC0.js";
import "./refresh-cw-CYdk8aE0.js";
import "./camera-C09Eyogi.js";
import "./settings-C5xOKpV3.js";
import "./arrow-right-CzynHiIo.js";
import "./circle-check-C59ZS7Zv.js";
import "./clock-B4fXoAi9.js";
import "./qr-code-C5X8qcH6.js";
import "./chevron-right-BHu-PGOg.js";
import "./chevron-down-CkQQZIhu.js";
import "./printer-Bx0z1CRb.js";
import "./book-open-B6eMguqY.js";
import "./megaphone-C8QUuT5s.js";
import "./file-check-BSLEMs1I.js";
import "./shield-DTtuUihb.js";
import "./clipboard-list-YgthURdB.js";
import "./users-DWlguTa_.js";
import "./user-round-Kft0Cy_Y.js";
import "./XiamNpcDialog-DoqCSHj9.js";
/* empty css                                                        */
import "./circle-question-mark-DY-RU614.js";
import "./sparkles-DbQIaVG6.js";
import "./chevron-left-DCSOZwZl.js";
import "./roving-focus-group-BJDH9kUk.js";
import "./card-content-Brg2wRkV.js";
import "./card-description-BGy_v5-d.js";
import "./card-title-BNY2ZPje.js";
function Share_2($$renderer, $$props) {
  const $$sanitized_props = sanitize_props($$props);
  const iconNode = [
    ["circle", { "cx": "18", "cy": "5", "r": "3" }],
    ["circle", { "cx": "6", "cy": "12", "r": "3" }],
    ["circle", { "cx": "18", "cy": "19", "r": "3" }],
    [
      "line",
      { "x1": "8.59", "x2": "15.42", "y1": "13.51", "y2": "17.49" }
    ],
    [
      "line",
      { "x1": "15.41", "x2": "8.59", "y1": "6.51", "y2": "10.49" }
    ]
  ];
  Icon($$renderer, spread_props([
    { name: "share-2" },
    $$sanitized_props,
    {
      /**
       * @component @name Share2
       * @description Lucide SVG icon component, renders SVG Element with children.
       *
       * @preview ![img](data:image/svg+xml;base64,PHN2ZyAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIgogIHdpZHRoPSIyNCIKICBoZWlnaHQ9IjI0IgogIHZpZXdCb3g9IjAgMCAyNCAyNCIKICBmaWxsPSJub25lIgogIHN0cm9rZT0iIzAwMCIgc3R5bGU9ImJhY2tncm91bmQtY29sb3I6ICNmZmY7IGJvcmRlci1yYWRpdXM6IDJweCIKICBzdHJva2Utd2lkdGg9IjIiCiAgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIgogIHN0cm9rZS1saW5lam9pbj0icm91bmQiCj4KICA8Y2lyY2xlIGN4PSIxOCIgY3k9IjUiIHI9IjMiIC8+CiAgPGNpcmNsZSBjeD0iNiIgY3k9IjEyIiByPSIzIiAvPgogIDxjaXJjbGUgY3g9IjE4IiBjeT0iMTkiIHI9IjMiIC8+CiAgPGxpbmUgeDE9IjguNTkiIHgyPSIxNS40MiIgeTE9IjEzLjUxIiB5Mj0iMTcuNDkiIC8+CiAgPGxpbmUgeDE9IjE1LjQxIiB4Mj0iOC41OSIgeTE9IjYuNTEiIHkyPSIxMC40OSIgLz4KPC9zdmc+Cg==) - https://lucide.dev/icons/share-2
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
const levels = { honors: "1", regular: "2", cutoff: "3" };
const bands = ["honors", "regular", "cutoff"];
function toCents(value) {
  return Math.sign(value) * Math.round(Math.abs(value) * 100 + Number.EPSILON * 1e3);
}
function roundedMidpoint(from, to) {
  const cents = toCents(from) + toCents(to);
  return Math.sign(cents) * Math.floor(Math.abs(cents) / 2 + 0.5) / 100;
}
function normalizeRangeValue(value) {
  if (value === null || value === void 0) return null;
  const numeric = typeof value === "number" ? value : typeof value === "string" && value.trim() !== "" ? Number(value) : NaN;
  return Number.isFinite(numeric) ? numeric : null;
}
function cloneAuthoring(source) {
  const sourceRanges = source.ranges;
  const ranges = Object.fromEntries(bands.map((band) => [
    band,
    Object.fromEntries(Object.entries(sourceRanges?.[band] ?? {}).map(([key, pair]) => [
      key,
      Array.isArray(pair) ? [normalizeRangeValue(pair[0]), normalizeRangeValue(pair[1])] : [null, null]
    ]))
  ]));
  return {
    version: source.version === 2 ? 2 : 1,
    ranges,
    preferred_strands: Array.isArray(source.preferred_strands) ? [...source.preferred_strands] : null,
    interests: Array.isArray(source.interests) ? [...source.interests] : null,
    ...Array.isArray(source.interest_activities) ? { interest_activities: [...source.interest_activities] } : {},
    ...typeof source.activities_reviewed === "boolean" ? { activities_reviewed: source.activities_reviewed } : {},
    ...isPreparationMap(source.preparation) ? { preparation: clonePreparationMap(source.preparation) } : {},
    ...isProfileActivityMapping(source.profile_activity_mapping) ? { profile_activity_mapping: cloneProfileActivityMapping(source.profile_activity_mapping) } : {}
  };
}
function isPreparationMap(value) {
  if (!value || typeof value !== "object" || Array.isArray(value)) return false;
  return Object.values(value).every((entry) => {
    if (!entry || typeof entry !== "object") return false;
    const preparation = entry;
    const validPair = (pair) => Array.isArray(pair) && pair.length === 2 && pair.every((item) => item === null || typeof item === "number" && Number.isFinite(item));
    return preparation.version === 1 && Array.isArray(preparation.common_range) && preparation.common_range.length === 2 && preparation.common_range.every((item) => typeof item === "number" && Number.isFinite(item)) && preparation.reviewed === true && !!preparation.before && bands.every((band) => preparation.before?.[band] === null || validPair(preparation.before?.[band])) && !!preparation.applied_values && bands.every((band) => typeof preparation.applied_values?.[band] === "number" && Number.isFinite(preparation.applied_values[band]));
  });
}
function clonePreparationMap(value) {
  return Object.fromEntries(Object.entries(value).map(([key, preparation]) => [key, {
    version: 1,
    common_range: [...preparation.common_range],
    before: Object.fromEntries(bands.map((band) => [
      band,
      preparation.before[band] ? [...preparation.before[band]] : null
    ])),
    applied_values: { ...preparation.applied_values },
    reviewed: true
  }]));
}
function isProfileActivityMapping(value) {
  if (!value || typeof value !== "object" || Array.isArray(value)) return false;
  return Object.values(value).every((entry) => {
    if (!entry || typeof entry !== "object" || !("reviewed" in entry) || !("activity_ids" in entry)) return false;
    const mapping = entry;
    return mapping.reviewed === true && Array.isArray(mapping.activity_ids) && mapping.activity_ids.every((id) => typeof id === "string") && new Set(mapping.activity_ids).size === mapping.activity_ids.length;
  });
}
function cloneProfileActivityMapping(value) {
  return Object.fromEntries(Object.entries(value).map(([profile, mapping]) => [profile, {
    reviewed: true,
    activity_ids: [...mapping.activity_ids]
  }]));
}
function proposeCommonRange(content, featureKey, pair, constraints = {}) {
  const [from, to] = pair;
  if (from === null || to === null || authoredRangeError(pair)) return null;
  if (constraints.min_value != null && (from < constraints.min_value || to < constraints.min_value)) return null;
  if (constraints.max_value != null && (from > constraints.max_value || to > constraints.max_value)) return null;
  const appliedValues = { honors: to, regular: roundedMidpoint(from, to), cutoff: from };
  const before = Object.fromEntries(bands.map((band) => [
    band,
    content.authoring?.ranges?.[band]?.[featureKey] ? [...content.authoring.ranges[band][featureKey]] : null
  ]));
  return {
    featureKey,
    pair: [from, to],
    constraints: { ...constraints },
    appliedValues,
    before,
    rows: bands.map((band) => ({
      band,
      level: levels[band],
      before: content.tiers[levels[band]]?.values?.[featureKey] ?? null,
      after: appliedValues[band]
    }))
  };
}
function applyCommonRange(content, proposal) {
  let next = cloneTabFmContent(content);
  for (const band of bands) {
    next = setAuthoredRange(next, band, proposal.featureKey, 0, String(proposal.pair[0]));
    next = setAuthoredRange(next, band, proposal.featureKey, 1, String(proposal.pair[1]));
  }
  const authoring = withAuthoring(next);
  authoring.version = 2;
  authoring.preparation = {
    ...authoring.preparation ?? {},
    [proposal.featureKey]: {
      version: 1,
      common_range: [...proposal.pair],
      before: proposal.before,
      applied_values: proposal.appliedValues,
      reviewed: true
    }
  };
  next.authoring = authoring;
  return next;
}
function setProfileActivityMapping(content, profile, activityIds, activities) {
  const parents = ["riasec_r", "riasec_i", "riasec_a", "riasec_s", "riasec_e", "riasec_c"];
  const activityById = new Map(activities.map((activity) => [activity.id, activity]));
  if (new Set(activityIds).size !== activityIds.length || activityIds.some((id) => !activityById.has(id) || !parents.includes(activityById.get(id).parent))) return null;
  if (!["1", "2", "3", "4", "5"].includes(profile)) return null;
  const next = cloneTabFmContent(content);
  const authoring = withAuthoring(next);
  const mappings = { ...authoring.profile_activity_mapping ?? {} };
  mappings[profile] = { reviewed: true, activity_ids: [...activityIds] };
  authoring.version = 2;
  authoring.profile_activity_mapping = mappings;
  authoring.activities_reviewed = true;
  for (const parent of parents) {
    next.tiers[profile].values[parent] = activityIds.some((id) => activityById.get(id)?.parent === parent) ? 1 : 0;
  }
  next.authoring = authoring;
  return next;
}
function cloneTabFmContent(value) {
  if (!value || typeof value !== "object" || !("tiers" in value)) return null;
  const source = value;
  const sourceTiers = source.tiers ?? {};
  const sourceAuthoring = source.authoring;
  const result = {
    tiers: Object.fromEntries(["1", "2", "3", "4", "5"].map((level) => [level, { values: { ...sourceTiers[level]?.values ?? {} } }]))
  };
  if ((sourceAuthoring?.version === 1 || sourceAuthoring?.version === 2) && sourceAuthoring.ranges && typeof sourceAuthoring.ranges === "object") {
    result.authoring = cloneAuthoring(sourceAuthoring);
  }
  return result;
}
function withAuthoring(content) {
  return content.authoring ?? {
    version: 1,
    ranges: { honors: {}, regular: {}, cutoff: {} },
    preferred_strands: null,
    interests: null
  };
}
function hasIncompleteAuthoredRange(content) {
  return bands.some((band) => Object.values(content?.authoring?.ranges?.[band] ?? {}).some(([from, to]) => from === null !== (to === null)));
}
function setAuthoredRange(content, band, key, endpoint, raw) {
  const next = cloneTabFmContent(content);
  const authoring = withAuthoring(next);
  const pair = [...authoring.ranges[band][key] ?? [null, null]];
  const value = raw.trim() === "" ? null : Number(raw);
  pair[endpoint] = value !== null && Number.isFinite(value) ? value : null;
  authoring.ranges[band][key] = pair;
  next.authoring = authoring;
  const [from, to] = pair;
  next.tiers[levels[band]].values[key] = from === null || to === null ? null : band === "honors" ? to : band === "regular" ? roundedMidpoint(from, to) : from;
  return next;
}
function authoredRangeError(pair) {
  if (!pair) return null;
  const [from, to] = pair;
  for (const [label, value] of [["From", from], ["To", to]]) {
    if (value === null) continue;
    if (!Number.isFinite(value) || value < -9999.99 || value > 9999.99) return `${label} must be between -9999.99 and 9999.99.`;
    if (Math.abs(value * 100 - Math.round(value * 100)) > 1e-8) return `${label} can have up to 2 decimal places.`;
  }
  if (from !== null && to !== null && from > to) return "From must not be greater than To.";
  return null;
}
function benchmarkStatusLabel(projection) {
  if (projection.state === "ready") return "Selected benchmark content is complete for review.";
  if (projection.state === "incomplete") {
    const facts = [];
    if (typeof projection.missing_required_count === "number") facts.push(`${projection.missing_required_count} required values missing`);
    if (typeof projection.partial_range_count === "number" && projection.partial_range_count > 0) facts.push(`${projection.partial_range_count} partial ranges`);
    if (projection.negative_anchor_missing === true) facts.push("negative anchor needs review");
    return facts.length ? `${facts.join(" · ")}.` : "Required benchmark values need review.";
  }
  if (projection.state === "recovery_required") return "Historical content is available to review.";
  if (projection.state === "schema_conflict") return "The benchmark schema changed. Review the current programme before editing.";
  if (projection.state === "schema_unavailable") return "The benchmark feature catalogue is unavailable for this programme and academic year.";
  if (projection.state === "integrity_failure") return "Benchmark integrity needs administrator review.";
  if (projection.state === "no_content") return "No benchmark content is saved yet.";
  return "Benchmark status is unavailable.";
}
function TabFmBenchmarkCalibrator($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      program,
      cycle,
      features = [],
      inputSchema = { subjects: [], activities: [] },
      content,
      version = 0,
      canEdit = false,
      busy = false,
      pendingSave = false,
      dirty = false,
      reason = "",
      onContentChange,
      onSave,
      onReset,
      recovery = null,
      isRecoveryProposal = false,
      onRecover,
      selectedBand = "cutoff"
    } = $$props;
    const bands2 = [
      {
        key: "honors",
        level: "1",
        label: "Honors",
        hint: "Upper example"
      },
      {
        key: "regular",
        level: "2",
        label: "Regular",
        hint: "Middle example"
      },
      {
        key: "cutoff",
        level: "3",
        label: "Cutoff",
        hint: "Lower example"
      }
    ];
    const subjects = derived(() => inputSchema?.subjects ?? []);
    const activities = derived(() => inputSchema?.activities ?? []);
    const subjectFeatures = derived(() => features.filter((feature) => subjects().some((subject) => subject.key === feature.key) && feature.logical_type === "numeric"));
    const numericFeatures = derived(() => features.filter((feature) => !subjectFeatures().includes(feature) && feature.logical_type === "numeric"));
    const strandFeature = derived(() => features.find((feature) => feature.key === "shs_strand"));
    const strandsFromExamples = derived(() => ["1", "2", "3", "4", "5"].map((level) => content?.tiers?.[level]?.values?.[strandFeature()?.key]).filter((value) => typeof value === "string" && value.trim() !== ""));
    const strandChoices = derived(() => [
      .../* @__PURE__ */ new Set([
        ...content?.authoring?.preferred_strands ?? [],
        ...strandsFromExamples()
      ])
    ]);
    const groupedActivities = derived(() => [
      "riasec_r",
      "riasec_i",
      "riasec_a",
      "riasec_s",
      "riasec_e",
      "riasec_c"
    ].map((parent) => ({
      parent,
      items: activities().filter((activity) => activity.parent === parent)
    })));
    let reasonOpen = false;
    let commonRanges = {};
    let proposals = {};
    let showAdvanced = false;
    let profileActivities = {};
    let mappingError = "";
    const rangeFeatures = derived(() => [...numericFeatures(), ...subjectFeatures()]);
    const hasRangeErrors = derived(() => bands2.some((band) => rangeFeatures().some((feature) => Object.prototype.hasOwnProperty.call(content?.authoring?.ranges?.[band.key] ?? {}, feature.key) && rangeError(feature, pairFor(band.key, feature.key)) !== null)));
    function pairFor(band, key) {
      return content?.authoring?.ranges?.[band]?.[key];
    }
    function changeRange(band, key, endpoint, raw) {
      onContentChange(setAuthoredRange(content, band, key, endpoint, raw));
    }
    function rangeConstraints(feature) {
      const source = feature.source_definition;
      return source && source.source_kind !== "frozen_legacy" ? {
        min_value: source.min_value ?? null,
        max_value: source.max_value ?? null
      } : {};
    }
    function rangeMinimum(feature) {
      return rangeConstraints(feature).min_value ?? -9999.99;
    }
    function rangeMaximum(feature) {
      return rangeConstraints(feature).max_value ?? 9999.99;
    }
    function rangeError(feature, pair) {
      const storedError = authoredRangeError(pair);
      if (storedError) return storedError;
      if (!pair) return null;
      const constraints = rangeConstraints(feature);
      for (const [label, value] of [["From", pair[0]], ["To", pair[1]]]) {
        if (value === null) continue;
        if (constraints.min_value != null && value < constraints.min_value) return `${label} must be at least ${constraints.min_value}.`;
        if (constraints.max_value != null && value > constraints.max_value) return `${label} must be at most ${constraints.max_value}.`;
      }
      return null;
    }
    function commonRangeError(feature, raw) {
      if (raw[0] === "" && raw[1] === "") return null;
      if (raw[0] === "" || raw[1] === "") return "Both endpoints must be valid before values can be prepared.";
      return rangeError(feature, [Number(raw[0]), Number(raw[1])]);
    }
    function prepareRange(featureKey) {
      const [fromRaw, toRaw] = commonRanges[featureKey] ?? ["", ""];
      const pair = [
        fromRaw === "" ? null : Number(fromRaw),
        toRaw === "" ? null : Number(toRaw)
      ];
      const feature = rangeFeatures().find((item) => item.key === featureKey);
      proposals[featureKey] = proposeCommonRange(content, featureKey, pair, rangeConstraints(feature ?? {}));
    }
    function applyPreparedRange(featureKey) {
      const proposal = proposals[featureKey] ?? null;
      if (!proposal || !proposalIsCurrent(featureKey)) return;
      onContentChange(applyCommonRange(content, proposal));
      proposals[featureKey] = null;
    }
    function proposalIsCurrent(featureKey) {
      const proposal = proposals[featureKey];
      if (!proposal) return false;
      const current = proposeCommonRange(content, featureKey, proposal.pair, proposal.constraints);
      return !!current && JSON.stringify(current.rows) === JSON.stringify(proposal.rows) && JSON.stringify(current.before) === JSON.stringify(proposal.before);
    }
    function applyProfileActivities(profile) {
      const activityIds = profileActivities[profile] ?? content.authoring?.profile_activity_mapping?.[profile]?.activity_ids ?? [];
      const next = setProfileActivityMapping(content, profile, activityIds, activities());
      if (next) {
        onContentChange(next);
        mappingError = "";
      } else {
        mappingError = "An activity is no longer present in the approved schema. Review the current definitions; no profile mapping was applied.";
      }
    }
    function setExampleValue(level, key, value) {
      const next = {
        ...content,
        tiers: Object.fromEntries(["1", "2", "3", "4", "5"].map((tier) => [tier, { values: { ...content.tiers?.[tier]?.values ?? {} } }])),
        ...content.authoring ? { authoring: content.authoring } : {}
      };
      next.tiers[level].values[key] = value;
      onContentChange(next);
    }
    function initiateSave() {
      reasonOpen = true;
    }
    async function confirmSave() {
      if (!reason.trim()) return;
      const saved = await onSave();
      if (saved) reasonOpen = false;
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<section class="min-w-0 space-y-5" aria-label="Programme benchmark editor"${attr("aria-busy", busy)}><div class="flex justify-end"><span role="status" class="rounded-md border px-3 py-1 text-xs">${escape_html(!canEdit ? "View only" : busy ? "Saving…" : pendingSave ? "Save outcome unknown · retry the same change" : dirty ? "Unsaved changes" : version ? `Draft v${version}` : isRecoveryProposal ? "Historical set · start draft to edit" : "No saved draft")}</span></div> <p class="text-sm text-muted-foreground">Saved changes remain a draft until an authorized editor publishes them. Use a version separately to make it live.</p> `);
      if (recovery) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<section class="space-y-3 border-y py-3 text-sm" aria-label="Historical benchmark recovery"><h3 class="font-medium">${escape_html(isRecoveryProposal ? `Historical set #${recovery.source_set_id} is available to start a draft.` : recovery.status === "draft" ? `Draft recovered from historical set #${recovery.source_set_id}.` : `Historical set #${recovery.source_set_id} is available for recovery.`)}</h3> <p class="text-muted-foreground">Historical data stays unchanged. ${escape_html(Number.isFinite(recovery.remaining_program_count) && Number.isFinite(recovery.source_program_count) ? `${recovery.remaining_program_count} of ${recovery.source_program_count} source programs remain to be reviewed.` : "Publication checks the complete source-program review.")} Missing Cross-Strand values need explicit review.</p> <details><summary class="cursor-pointer text-xs">Technical details</summary><dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-2 text-xs"><dt>Historical content digest</dt><dd class="break-all">${escape_html(recovery.source_content_sha256 ?? "Unavailable")}</dd><dt>Frozen source digest</dt><dd class="break-all">${escape_html(recovery.source_snapshot_digest ?? "Unavailable")}</dd></dl></details> `);
        if (canEdit && isRecoveryProposal && recovery.program_in_source === true && recovery.status === "available") {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<label for="recovery-reason" class="block font-medium">Reason for change</label>`);
          Textarea($$renderer3, {
            id: "recovery-reason",
            required: true,
            maxlength: 500,
            rows: 2,
            disabled: busy,
            get value() {
              return reason;
            },
            set value($$value) {
              reason = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!---->`);
          Button($$renderer3, {
            disabled: busy || !reason.trim(),
            onclick: onRecover,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(busy ? "Starting draft…" : "Start draft from historical set")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></section>`);
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--> `);
      if (content && features.length) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="text-xs text-muted-foreground">Enter one common range for each confirmed numeric field. Prepare shows the three resulting model cells; Apply changes only this local working copy. Nothing is saved until the ordinary versioned Save.</p> <div class="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">`);
        const each_array = ensure_array_like([...numericFeatures(), ...subjectFeatures()]);
        if (each_array.length !== 0) {
          $$renderer3.push("<!--[-->");
          for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
            let feature = each_array[$$index_1];
            const raw = commonRanges[feature.key] ?? ["", ""];
            const proposal = proposals[feature.key];
            const preparationError = commonRangeError(feature, raw);
            $$renderer3.push(`<fieldset class="min-w-0 space-y-3 rounded-lg border p-3"><legend class="px-1 text-sm font-medium">${escape_html(feature.label)}</legend> <div class="grid grid-cols-[1fr_auto_1fr] items-end gap-2"><div><label${attr("for", `common-${feature.key}-from`)} class="text-xs">Common range · From</label>`);
            Input($$renderer3, {
              id: `common-${feature.key}-from`,
              type: "number",
              min: rangeMinimum(feature),
              max: rangeMaximum(feature),
              step: "0.01",
              value: raw[0],
              disabled: !canEdit || isRecoveryProposal || busy || pendingSave,
              oninput: (event) => {
                commonRanges[feature.key] = [event.currentTarget.value, raw[1]];
                proposals[feature.key] = null;
              }
            });
            $$renderer3.push(`<!----></div> <span class="pb-3 text-muted-foreground" aria-hidden="true">–</span> <div><label${attr("for", `common-${feature.key}-to`)} class="text-xs">Common range · To</label>`);
            Input($$renderer3, {
              id: `common-${feature.key}-to`,
              type: "number",
              min: rangeMinimum(feature),
              max: rangeMaximum(feature),
              step: "0.01",
              value: raw[1],
              disabled: !canEdit || isRecoveryProposal || busy || pendingSave,
              oninput: (event) => {
                commonRanges[feature.key] = [raw[0], event.currentTarget.value];
                proposals[feature.key] = null;
              }
            });
            $$renderer3.push(`<!----></div></div> <p class="text-xs text-muted-foreground">${escape_html(feature.declared_unit ?? "Unit not confirmed")}</p> `);
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              size: "sm",
              disabled: !canEdit || isRecoveryProposal || busy || pendingSave,
              onclick: () => prepareRange(feature.key),
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Prepare examples`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----> `);
            if (proposal) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div class="space-y-2 border-t pt-2"${attr("aria-label", `Prepared values for ${feature.label}`)}><p class="text-xs font-medium">Review before applying · local only</p> <!--[-->`);
              const each_array_1 = ensure_array_like(proposal.rows);
              for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
                let row = each_array_1[$$index];
                const previousRange = proposal.before[row.band];
                $$renderer3.push(`<div class="grid gap-1 border-t py-2 text-xs sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"><span>${escape_html(bands2.find((item) => item.key === row.band)?.label)}</span><span><span class="block">Range: ${escape_html(previousRange ? `${previousRange[0] ?? "Missing"}–${previousRange[1] ?? "Missing"}` : "No authored range")} → ${escape_html(proposal.pair[0])}–${escape_html(proposal.pair[1])}</span><span class="block">Model cell: ${escape_html(row.before == null ? "Missing" : row.before)} → ${escape_html(row.after)}</span></span></div>`);
              }
              $$renderer3.push(`<!--]--> `);
              if (proposalIsCurrent(feature.key)) {
                $$renderer3.push("<!--[0-->");
                Button($$renderer3, {
                  type: "button",
                  size: "sm",
                  disabled: !canEdit || isRecoveryProposal || busy || pendingSave,
                  onclick: () => applyPreparedRange(feature.key),
                  children: ($$renderer4) => {
                    $$renderer4.push(`<!---->Apply reviewed values to this draft`);
                  },
                  $$slots: { default: true }
                });
              } else {
                $$renderer3.push("<!--[-1-->");
                $$renderer3.push(`<p role="status" class="text-xs text-amber-700 dark:text-amber-300">The draft changed after this review. Prepare again to confirm current before-values.</p>`);
              }
              $$renderer3.push(`<!--]--></div>`);
            } else if (preparationError) {
              $$renderer3.push("<!--[1-->");
              $$renderer3.push(`<p class="text-xs text-muted-foreground">${escape_html(preparationError)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></fieldset>`);
          }
        } else {
          $$renderer3.push("<!--[!-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">No numeric benchmark features are available in the approved schema.</p>`);
        }
        $$renderer3.push(`<!--]--></div> <details class="border-y py-3"${attr("open", showAdvanced, true)}><summary class="cursor-pointer text-sm font-medium">Advanced · profile-specific ranges and examples</summary> <p class="mt-2 text-xs text-muted-foreground">Use only for explicit profile-specific review. Other-strand and Below-cutoff values are never generated from common ranges.</p> <!--[-->`);
        const each_array_2 = ensure_array_like(bands2);
        for (let $$index_3 = 0, $$length = each_array_2.length; $$index_3 < $$length; $$index_3++) {
          let advancedBand = each_array_2[$$index_3];
          $$renderer3.push(`<fieldset class="grid min-w-0 gap-3 border-t py-3 md:grid-cols-2"><legend class="text-sm font-medium">${escape_html(advancedBand.label)} range</legend> <!--[-->`);
          const each_array_3 = ensure_array_like([...numericFeatures(), ...subjectFeatures()]);
          for (let $$index_2 = 0, $$length2 = each_array_3.length; $$index_2 < $$length2; $$index_2++) {
            let feature = each_array_3[$$index_2];
            const pair = pairFor(advancedBand.key, feature.key);
            const errorText = rangeError(feature, pair);
            $$renderer3.push(`<div class="space-y-2"><h4 class="text-sm">${escape_html(feature.label)}</h4><div class="grid grid-cols-[1fr_auto_1fr] items-end gap-2"><div><label${attr("for", `advanced-${advancedBand.key}-${feature.key}-from`)} class="text-xs">From</label>`);
            Input($$renderer3, {
              id: `advanced-${advancedBand.key}-${feature.key}-from`,
              type: "number",
              min: rangeMinimum(feature),
              max: rangeMaximum(feature),
              step: "0.01",
              value: pair?.[0] ?? "",
              disabled: !canEdit || isRecoveryProposal || busy || pendingSave,
              oninput: (event) => changeRange(advancedBand.key, feature.key, 0, event.currentTarget.value)
            });
            $$renderer3.push(`<!----></div><span class="pb-3">–</span><div><label${attr("for", `advanced-${advancedBand.key}-${feature.key}-to`)} class="text-xs">To</label>`);
            Input($$renderer3, {
              id: `advanced-${advancedBand.key}-${feature.key}-to`,
              type: "number",
              min: rangeMinimum(feature),
              max: rangeMaximum(feature),
              step: "0.01",
              value: pair?.[1] ?? "",
              disabled: !canEdit || isRecoveryProposal || busy || pendingSave,
              oninput: (event) => changeRange(advancedBand.key, feature.key, 1, event.currentTarget.value)
            });
            $$renderer3.push(`<!----></div></div>`);
            if (errorText) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p role="alert" class="text-xs text-destructive">${escape_html(errorText)}</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          }
          $$renderer3.push(`<!--]--></fieldset>`);
        }
        $$renderer3.push(`<!--]--> <!--[-->`);
        const each_array_4 = ensure_array_like(["1", "2", "3", "4", "5"]);
        for (let $$index_5 = 0, $$length = each_array_4.length; $$index_5 < $$length; $$index_5++) {
          let level = each_array_4[$$index_5];
          const tierLabel = {
            "1": "Honors",
            "2": "Regular",
            "3": "Cutoff",
            "4": "Other-strand example",
            "5": "Below-cutoff example"
          }[level];
          $$renderer3.push(`<fieldset class="grid min-w-0 gap-3 border-t py-3 sm:grid-cols-2"><legend class="text-sm font-medium">${escape_html(tierLabel)} · exact categorical and binary values</legend><!--[-->`);
          const each_array_5 = ensure_array_like(features.filter((feature) => feature.logical_type !== "numeric" || ["4", "5"].includes(level)));
          for (let $$index_4 = 0, $$length2 = each_array_5.length; $$index_4 < $$length2; $$index_4++) {
            let feature = each_array_5[$$index_4];
            const value = content.tiers?.[level]?.values?.[feature.key];
            $$renderer3.push(`<div class="min-w-0 space-y-1"><label${attr("for", `example-${level}-${feature.key}`)} class="text-xs font-medium">${escape_html(feature.label)}</label>`);
            if (canEdit && !isRecoveryProposal) {
              $$renderer3.push("<!--[0-->");
              Input($$renderer3, {
                id: `example-${level}-${feature.key}`,
                type: feature.logical_type === "categorical" ? "text" : "number",
                value: value ?? "",
                disabled: busy || pendingSave,
                oninput: (event) => setExampleValue(level, feature.key, event.currentTarget.value === "" ? null : feature.logical_type === "categorical" ? event.currentTarget.value : Number(event.currentTarget.value))
              });
            } else {
              $$renderer3.push("<!--[-1-->");
              $$renderer3.push(`<p class="text-sm tabular-nums">${escape_html(value ?? "Missing")}</p>`);
            }
            $$renderer3.push(`<!--]--></div>`);
          }
          $$renderer3.push(`<!--]--></fieldset>`);
        }
        $$renderer3.push(`<!--]--></details> <details class="border-y py-3"><summary class="cursor-pointer text-sm font-medium">Programme preferences and reviewed activity mapping</summary> <div class="mt-3 space-y-4"><fieldset class="space-y-2"><legend class="text-sm font-medium">Preferred school strands · metadata only</legend><div class="flex flex-wrap gap-2"><!--[-->`);
        const each_array_6 = ensure_array_like(strandChoices());
        for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
          let choice = each_array_6[$$index_6];
          $$renderer3.push(`<label class="flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm"><input type="checkbox"${attr("checked", content.authoring?.preferred_strands?.includes(choice) ?? false, true)}${attr("disabled", !canEdit || isRecoveryProposal || busy || pendingSave, true)}/>${escape_html(choice)}</label>`);
        }
        $$renderer3.push(`<!--]--></div>`);
        if (!strandChoices().length) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">No strand choices are present in the reviewed examples.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></fieldset> <fieldset class="space-y-3"><div class="flex items-center justify-between"><legend class="text-sm font-medium">Preferred career activities · programme metadata only</legend> `);
        if ((content.authoring?.interest_activities?.length ?? 0) > 0) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<button type="button" class="text-xs text-muted-foreground hover:text-destructive hover:underline cursor-pointer"${attr("disabled", !canEdit || isRecoveryProposal || busy || pendingSave, true)}>Clear all activities</button>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div> <!--[-->`);
        const each_array_7 = ensure_array_like(groupedActivities());
        for (let $$index_8 = 0, $$length = each_array_7.length; $$index_8 < $$length; $$index_8++) {
          let group = each_array_7[$$index_8];
          const groupIds = group.items.map((a) => a.id);
          const allGroupSelected = groupIds.length > 0 && groupIds.every((id) => (content.authoring?.interest_activities ?? []).includes(id));
          $$renderer3.push(`<div><div class="flex items-center justify-between mb-1"><h4 class="text-sm font-medium">${escape_html(group.parent.replace("riasec_", "").toUpperCase())}</h4> <button type="button" class="text-xs text-primary hover:underline font-medium cursor-pointer"${attr("disabled", !canEdit || isRecoveryProposal || busy || pendingSave, true)}>${escape_html(allGroupSelected ? "Deselect group" : `Select all (${group.items.length})`)}</button></div> <div class="flex flex-wrap gap-2"><!--[-->`);
          const each_array_8 = ensure_array_like(group.items);
          for (let $$index_7 = 0, $$length2 = each_array_8.length; $$index_7 < $$length2; $$index_7++) {
            let activity = each_array_8[$$index_7];
            $$renderer3.push(`<label class="flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm"><input type="checkbox"${attr("checked", content.authoring?.interest_activities?.includes(activity.id) ?? false, true)}${attr("disabled", !canEdit || isRecoveryProposal || busy || pendingSave, true)}/> ${escape_html(activity.label)}</label>`);
          }
          $$renderer3.push(`<!--]--></div></div>`);
        }
        $$renderer3.push(`<!--]--> `);
        if (!activities().length) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">No career activities are declared in the approved definitions catalogue.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></fieldset> <section class="space-y-3 border-t pt-3" aria-label="Profile activity mapping review"><h3 class="text-sm font-medium">Explicit model-profile activity mapping</h3><p class="text-xs text-muted-foreground">Choose activities for one profile, then review that profile mapping. A parent flag is 1 when one or more mapped activities are selected, otherwise 0. Unreviewed profiles keep their current values.</p><!--[-->`);
        const each_array_9 = ensure_array_like(["1", "2", "3", "4", "5"]);
        for (let $$index_11 = 0, $$length = each_array_9.length; $$index_11 < $$length; $$index_11++) {
          let profile = each_array_9[$$index_11];
          $$renderer3.push(`<fieldset class="space-y-2 rounded-md border p-3"><legend class="px-1 text-sm font-medium">${escape_html(profile === "4" ? "Other-strand" : profile === "5" ? "Below-cutoff" : bands2.find((item) => item.level === profile)?.label)} ${escape_html(content.authoring?.profile_activity_mapping?.[profile]?.reviewed ? "· reviewed" : "· not reviewed")}</legend><!--[-->`);
          const each_array_10 = ensure_array_like(groupedActivities());
          for (let $$index_10 = 0, $$length2 = each_array_10.length; $$index_10 < $$length2; $$index_10++) {
            let group = each_array_10[$$index_10];
            if (group.items.length) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<div><h4 class="text-xs font-medium">${escape_html(group.parent.replace("riasec_", "").toUpperCase())}</h4><div class="flex flex-wrap gap-2"><!--[-->`);
              const each_array_11 = ensure_array_like(group.items);
              for (let $$index_9 = 0, $$length3 = each_array_11.length; $$index_9 < $$length3; $$index_9++) {
                let activity = each_array_11[$$index_9];
                const currentIds = profileActivities[profile] ?? content.authoring?.profile_activity_mapping?.[profile]?.activity_ids ?? [];
                $$renderer3.push(`<label class="flex min-h-11 items-center gap-2 rounded-md border px-3 text-xs"><input type="checkbox"${attr("checked", currentIds.includes(activity.id), true)}${attr("disabled", !canEdit || isRecoveryProposal || busy || pendingSave, true)}/>${escape_html(activity.label)}</label>`);
              }
              $$renderer3.push(`<!--]--></div></div>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]-->`);
          }
          $$renderer3.push(`<!--]-->`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            size: "sm",
            disabled: !canEdit || isRecoveryProposal || busy || pendingSave || !activities().length,
            onclick: () => applyProfileActivities(profile),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Review and apply this profile mapping`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></fieldset>`);
        }
        $$renderer3.push(`<!--]--></section></div></details> <details class="border-t py-3"><summary class="cursor-pointer text-xs font-medium">Technical details</summary><ul class="mt-2 space-y-1 text-xs text-muted-foreground"><!--[-->`);
        const each_array_12 = ensure_array_like(features);
        for (let $$index_12 = 0, $$length = each_array_12.length; $$index_12 < $$length; $$index_12++) {
          let feature = each_array_12[$$index_12];
          $$renderer3.push(`<li>${escape_html(feature.label)}: ${escape_html(feature.key)} · ${escape_html(feature.unit_unverified ? "unit unverified" : feature.declared_unit ?? "no confirmed unit")}</li>`);
        }
        $$renderer3.push(`<!--]--><!--[-->`);
        const each_array_13 = ensure_array_like(subjects());
        for (let $$index_13 = 0, $$length = each_array_13.length; $$index_13 < $$length; $$index_13++) {
          let subject = each_array_13[$$index_13];
          $$renderer3.push(`<li>${escape_html(subject.label)}: source ${escape_html(subject.source_code)} v${escape_html(subject.source_version)} · ${escape_html(subject.unit ?? "unit unavailable")}</li>`);
        }
        $$renderer3.push(`<!--]--></ul></details> `);
        if (mappingError) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<p role="alert" class="text-sm text-destructive">${escape_html(mappingError)}</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> `);
        if (reasonOpen) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="space-y-3 border-t pt-4"><label for="benchmark-reason" class="text-sm font-medium">Reason for this save</label>`);
          Textarea($$renderer3, {
            id: "benchmark-reason",
            required: true,
            maxlength: 500,
            rows: 2,
            disabled: busy,
            get value() {
              return reason;
            },
            set value($$value) {
              reason = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----><div class="flex flex-wrap gap-2">`);
          Button($$renderer3, {
            type: "button",
            disabled: busy || !reason.trim() || hasRangeErrors() && !pendingSave,
            onclick: confirmSave,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(busy ? "Saving…" : pendingSave ? "Retry same save" : "Confirm save")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            disabled: busy || pendingSave,
            onclick: () => {
              reasonOpen = false;
              reason = "";
            },
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Cancel`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<div class="flex flex-wrap gap-2 border-t pt-4">`);
          Button($$renderer3, {
            type: "button",
            disabled: busy || !pendingSave && !dirty && version > 0,
            onclick: initiateSave,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->${escape_html(pendingSave ? "Retry same save" : version ? "Save changes" : "Start draft")}`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!---->`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            disabled: busy || pendingSave || !dirty,
            onclick: onReset,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Discard unsaved changes`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        }
        $$renderer3.push(`<!--]-->`);
      } else {
        $$renderer3.push("<!--[-1-->");
        $$renderer3.push(`<p class="py-4 text-sm text-muted-foreground">No saved benchmark values are available for this programme and academic year. No values have been substituted.</p> `);
        if (canEdit && !isRecoveryProposal) {
          $$renderer3.push("<!--[0-->");
          if (reasonOpen || pendingSave) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div class="max-w-xl space-y-3 border-t pt-4"><label for="benchmark-start-reason" class="text-sm font-medium">Reason for starting a draft</label>`);
            Textarea($$renderer3, {
              id: "benchmark-start-reason",
              required: true,
              maxlength: 500,
              rows: 2,
              disabled: busy,
              get value() {
                return reason;
              },
              set value($$value) {
                reason = $$value;
                $$settled = false;
              }
            });
            $$renderer3.push(`<!----><div class="flex gap-2">`);
            Button($$renderer3, {
              type: "button",
              disabled: busy || !features.length || !reason.trim(),
              onclick: confirmSave,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->${escape_html(pendingSave ? "Retry same start" : "Start draft")}`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!---->`);
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              disabled: busy || pendingSave,
              onclick: () => {
                reasonOpen = false;
                reason = "";
              },
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Cancel`);
              },
              $$slots: { default: true }
            });
            $$renderer3.push(`<!----></div>`);
            if (!features.length) {
              $$renderer3.push("<!--[0-->");
              $$renderer3.push(`<p class="text-xs text-muted-foreground">A confirmed benchmark feature catalogue is required. No catalogue is available for this context.</p>`);
            } else {
              $$renderer3.push("<!--[-1-->");
            }
            $$renderer3.push(`<!--]--></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
            Button($$renderer3, {
              type: "button",
              disabled: busy || !features.length,
              onclick: initiateSave,
              children: ($$renderer4) => {
                $$renderer4.push(`<!---->Start draft`);
              },
              $$slots: { default: true }
            });
          }
          $$renderer3.push(`<!--]-->`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      }
      $$renderer3.push(`<!--]--></section>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { reason, selectedBand });
  });
}
function TabFmRevisionCompare($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { comparison, workingCopy = null } = $$props;
    let changedOnly = false;
    let showPairedValues = false;
    let showWorkingCopy = false;
    const baseline = derived(() => comparison?.baseline ?? null);
    const revision = derived(() => comparison?.revision ?? null);
    const sections = derived(() => {
      if (!baseline()?.content || !revision()?.content) return [];
      const featureByKey = new Map([...baseline().features ?? [], ...revision().features ?? []].map((feature) => [feature.key, feature]));
      const profileRows = [];
      for (const level of ["1", "2", "3", "4", "5"]) {
        for (const [key, feature] of featureByKey) {
          const before = baseline().content.tiers?.[level]?.values?.[key] ?? null;
          const after = revision().content.tiers?.[level]?.values?.[key] ?? null;
          profileRows.push({
            id: `${level}:${key}`,
            label: `${profileLabel(level)} · ${feature.label ?? key}`,
            before,
            after,
            category: "model cell"
          });
        }
      }
      const rangeKeys = /* @__PURE__ */ new Set();
      for (const band of ["honors", "regular", "cutoff"]) {
        for (const key of Object.keys(baseline().content.authoring?.ranges?.[band] ?? {})) rangeKeys.add(`${band}:${key}`);
        for (const key of Object.keys(revision().content.authoring?.ranges?.[band] ?? {})) rangeKeys.add(`${band}:${key}`);
      }
      const rangeRows = [...rangeKeys].map((identity) => {
        const [band, key] = identity.split(":");
        return {
          id: identity,
          label: `${bandLabel(band)} · ${featureByKey.get(key)?.label ?? key}`,
          before: baseline().content.authoring?.ranges?.[band]?.[key] ?? null,
          after: revision().content.authoring?.ranges?.[band]?.[key] ?? null,
          category: "authored range"
        };
      });
      const metadataRows = [
        {
          id: "interest_activities",
          label: "Preferred career activities · metadata only",
          before: baseline().content.authoring?.interest_activities ?? null,
          after: revision().content.authoring?.interest_activities ?? null,
          category: "programme metadata"
        },
        {
          id: "preferred_strands",
          label: "Preferred school strands · metadata only",
          before: baseline().content.authoring?.preferred_strands ?? null,
          after: revision().content.authoring?.preferred_strands ?? null,
          category: "programme metadata"
        }
      ];
      const preparationKeys = /* @__PURE__ */ new Set([
        ...Object.keys(baseline().content.authoring?.preparation ?? {}),
        ...Object.keys(revision().content.authoring?.preparation ?? {})
      ]);
      for (const key of preparationKeys) {
        metadataRows.push({
          id: `range-preparation:${key}`,
          label: `${featureByKey.get(key)?.label ?? key} · common-range preparation provenance`,
          before: baseline().content.authoring?.preparation?.[key] ?? null,
          after: revision().content.authoring?.preparation?.[key] ?? null,
          category: "range preparation provenance"
        });
      }
      for (const level of ["1", "2", "3", "4", "5"]) {
        metadataRows.push({
          id: `profile_activity_mapping:${level}`,
          label: `${profileLabel(level)} · reviewed activity mapping`,
          before: baseline().content.authoring?.profile_activity_mapping?.[level] ?? null,
          after: revision().content.authoring?.profile_activity_mapping?.[level] ?? null,
          category: "model input · reviewed mapping"
        });
      }
      const sourceRows = [...featureByKey].map(([key, feature]) => ({
        id: key,
        before: feature.source_definition ?? null,
        after: (revision().features ?? []).find((item) => item.key === key)?.source_definition ?? null,
        category: "subject/source metadata"
      }));
      return [
        {
          id: "model-cells",
          label: "Model example cells",
          rows: profileRows
        },
        { id: "ranges", label: "Authored ranges", rows: rangeRows },
        {
          id: "subject-sources",
          label: "Subject and source definitions",
          rows: sourceRows
        },
        {
          id: "riasec",
          label: "RIASEC and programme metadata",
          rows: metadataRows
        }
      ].map((section) => ({
        ...section,
        rows: section.rows.map((row) => ({
          ...row,
          changed: JSON.stringify(row.before) !== JSON.stringify(row.after)
        })).filter((row) => !changedOnly)
      }));
    });
    const changeCount = (section) => section.rows.filter((row) => row.changed).length;
    function profileLabel(level) {
      return {
        "1": "Honors",
        "2": "Regular",
        "3": "Cutoff",
        "4": "Other-strand",
        "5": "Below-cutoff"
      }[level] ?? level;
    }
    function bandLabel(band) {
      return { honors: "Honors", regular: "Regular", cutoff: "Cutoff" }[band] ?? band;
    }
    $$renderer2.push(`<section class="space-y-4" aria-label="Saved benchmark comparison"><header class="space-y-1"><h2 class="text-base font-semibold">Compare exact benchmark versions</h2> <p class="text-sm text-muted-foreground">${escape_html(baseline()?.in_use ? "In-use baseline" : "Published but not in use")} #${escape_html(baseline()?.id)} v${escape_html(baseline()?.version)} against saved revision #${escape_html(revision()?.id)} v${escape_html(revision()?.version)}. This comparison is read-only.</p></header> <div class="flex flex-wrap items-center gap-4 border-y py-3 text-sm"><label class="flex min-h-11 items-center gap-2"><input type="checkbox"${attr("checked", changedOnly, true)}/>Changed only</label> <label class="flex min-h-11 items-center gap-2"><input type="checkbox"${attr("checked", showPairedValues, true)}/>Show paired values</label> `);
    if (workingCopy) {
      $$renderer2.push("<!--[0-->");
      $$renderer2.push(`<label class="flex min-h-11 items-center gap-2"><input type="checkbox"${attr("checked", showWorkingCopy, true)}/>Include unsaved working copy</label>`);
    } else {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></div> <!--[-->`);
    const each_array = ensure_array_like(sections());
    for (let $$index_1 = 0, $$length = each_array.length; $$index_1 < $$length; $$index_1++) {
      let section = each_array[$$index_1];
      const totalChanges = changeCount(section);
      $$renderer2.push(`<details class="rounded-lg border"><summary class="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-3 text-sm font-medium"><span>${escape_html(section.label)}</span><span class="text-xs text-muted-foreground">${escape_html(totalChanges)} ${escape_html(totalChanges === 1 ? "change" : "changes")}</span></summary> <div class="divide-y px-3">`);
      const each_array_1 = ensure_array_like(section.rows);
      if (each_array_1.length !== 0) {
        $$renderer2.push("<!--[-->");
        for (let $$index = 0, $$length2 = each_array_1.length; $$index < $$length2; $$index++) {
          let row = each_array_1[$$index];
          $$renderer2.push(`<article class="grid gap-2 py-3 md:grid-cols-[minmax(10rem,1fr)_minmax(0,2fr)]"><div class="min-w-0"><h3 class="text-sm font-medium">${escape_html(row.label)}</h3><p class="text-xs text-muted-foreground">${escape_html(row.category)}</p></div> `);
          {
            $$renderer2.push("<!--[-1-->");
          }
          $$renderer2.push(`<!--]--></article>`);
        }
      } else {
        $$renderer2.push("<!--[!-->");
        $$renderer2.push(`<p class="py-3 text-sm text-muted-foreground">No differences in this section.</p>`);
      }
      $$renderer2.push(`<!--]--></div></details>`);
    }
    $$renderer2.push(`<!--]--> `);
    {
      $$renderer2.push("<!--[-1-->");
    }
    $$renderer2.push(`<!--]--></section>`);
  });
}
function TabFmStickyNoteBoard($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let {
      notes = { items: [], pagination: null, counts: null },
      selectedNoteId = null,
      selectedNote = null,
      isShared = false,
      loading = false,
      error = null,
      denied = false,
      canEdit = false,
      canPublish = false,
      busy = false,
      onSelect,
      onNew,
      onShare,
      onHistory,
      onCopy,
      onRevert,
      onPage,
      onSearch,
      search = "",
      children
    } = $$props;
    const noteItems = derived(() => notes?.items ?? []);
    const noNotes = derived(() => !loading && !error && !denied && !selectedNote && !search.trim() && notes?.counts?.total === 0);
    let moreOpen = false;
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      $$renderer3.push(`<section class="min-w-0 space-y-4" aria-label="Advising notes"><header class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold">Advising notes</h2><p class="text-sm text-muted-foreground">Short programme guidance, shared only when reviewed.</p></div> `);
      if (canEdit && !noNotes() && selectedNoteId !== null) {
        $$renderer3.push("<!--[0-->");
        Button($$renderer3, {
          type: "button",
          variant: "outline",
          onclick: onNew,
          disabled: busy,
          children: ($$renderer4) => {
            $$renderer4.push(`<!---->New note`);
          },
          $$slots: { default: true }
        });
      } else {
        $$renderer3.push("<!--[-1-->");
      }
      $$renderer3.push(`<!--]--></header> `);
      if (denied) {
        $$renderer3.push("<!--[0-->");
        $$renderer3.push(`<p class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="status">Advising notes are not available for this programme and academic year.</p>`);
      } else if (loading) {
        $$renderer3.push("<!--[1-->");
        $$renderer3.push(`<p class="py-8 text-sm text-muted-foreground" role="status">Loading notes…</p>`);
      } else if (error) {
        $$renderer3.push("<!--[2-->");
        $$renderer3.push(`<p class="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive" role="alert">${escape_html(error)}</p>`);
      } else if (noNotes()) {
        $$renderer3.push("<!--[3-->");
        $$renderer3.push(`<div class="flex min-h-28 items-center justify-center rounded-lg border border-dashed bg-muted/20 p-5">`);
        if (canEdit) {
          $$renderer3.push("<!--[0-->");
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            class: "min-h-11 gap-2 rounded-lg shadow-sm",
            onclick: onNew,
            disabled: busy,
            children: ($$renderer4) => {
              Sticky_note($$renderer4, { class: "size-4", "aria-hidden": "true" });
              $$renderer4.push(`<!----><span>Add note</span>`);
            },
            $$slots: { default: true }
          });
        } else {
          $$renderer3.push("<!--[-1-->");
          $$renderer3.push(`<p class="text-sm text-muted-foreground">No advising notes are available.</p>`);
        }
        $$renderer3.push(`<!--]--></div>`);
      } else {
        $$renderer3.push("<!--[-1-->");
        if (notes.counts?.total > 0 || search.trim()) {
          $$renderer3.push("<!--[0-->");
          if (typeof notes.counts?.total === "number" && typeof notes.counts?.drafts === "number" && typeof notes.counts?.shared === "number") {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<p class="text-sm text-muted-foreground">${escape_html(notes.counts.total)} notes · ${escape_html(notes.counts.drafts)} drafts · ${escape_html(notes.counts.shared)} shared</p>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> <form class="flex items-center gap-2"><label class="sr-only" for="advising-note-search">Search notes</label> `);
          Input($$renderer3, {
            id: "advising-note-search",
            maxlength: 120,
            placeholder: "Find note text",
            get value() {
              return search;
            },
            set value($$value) {
              search = $$value;
              $$settled = false;
            }
          });
          $$renderer3.push(`<!----> `);
          Button($$renderer3, {
            type: "submit",
            variant: "outline",
            disabled: busy,
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Search`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></form>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="grid min-w-0 gap-5 lg:grid-cols-[minmax(14rem,0.8fr)_minmax(0,1.4fr)]">`);
        if (noteItems().length > 0 || search.trim()) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<nav aria-label="Notes in this programme and academic year" class="max-h-[55vh] min-w-0 space-y-1 overflow-y-auto border-b pb-3 lg:border-b-0 lg:border-r lg:pr-4">`);
          const each_array = ensure_array_like(noteItems());
          if (each_array.length !== 0) {
            $$renderer3.push("<!--[-->");
            for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
              let note = each_array[$$index];
              $$renderer3.push(`<button type="button"${attr("aria-current", String(selectedNoteId ?? "") === String(note.note_id) ? "true" : void 0)}${attr_class(`block min-h-11 w-full rounded-md px-3 py-2 text-left ${String(selectedNoteId ?? "") === String(note.note_id) ? "bg-muted font-medium" : "hover:bg-muted/60"}`)}><span class="block line-clamp-3 whitespace-pre-wrap break-words text-sm">${escape_html(note.content || "Untitled note")}</span> <span class="mt-1 block text-xs text-muted-foreground">${escape_html(note.author_name ?? "Staff")} · ${escape_html(note.published_publication_id ? "Shared" : "Draft")}`);
              if (typeof note.version === "number") {
                $$renderer3.push("<!--[0-->");
                $$renderer3.push(`· v${escape_html(note.version)}`);
              } else {
                $$renderer3.push("<!--[-1-->");
              }
              $$renderer3.push(`<!--]--></span></button>`);
            }
          } else {
            $$renderer3.push("<!--[!-->");
            $$renderer3.push(`<p class="px-3 py-2 text-sm text-muted-foreground" role="status">${escape_html(search.trim() ? "No notes match this search." : "No notes are available for this context.")}</p>`);
          }
          $$renderer3.push(`<!--]--></nav>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--> <div class="min-w-0 space-y-3">`);
        if (selectedNote) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex flex-wrap items-center gap-2" aria-label="Selected note actions">`);
          if (canPublish) {
            $$renderer3.push("<!--[0-->");
            Button($$renderer3, {
              type: "button",
              variant: "outline",
              class: "gap-1.5",
              disabled: busy,
              onclick: onShare,
              children: ($$renderer4) => {
                Share_2($$renderer4, { class: "size-4", "aria-hidden": "true" });
                $$renderer4.push(`<!---->${escape_html(isShared ? "Unshare" : "Share")}`);
              },
              $$slots: { default: true }
            });
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--> `);
          Button($$renderer3, {
            type: "button",
            variant: "ghost",
            size: "icon",
            class: "size-11",
            "aria-label": "Note history",
            title: "Note history",
            disabled: busy,
            onclick: onHistory,
            children: ($$renderer4) => {
              History($$renderer4, { class: "size-4", "aria-hidden": "true" });
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> <div class="relative">`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            class: "min-h-11 gap-2",
            "aria-expanded": moreOpen,
            "aria-controls": "advising-note-more-actions",
            onclick: () => {
              moreOpen = !moreOpen;
            },
            children: ($$renderer4) => {
              Ellipsis($$renderer4, { class: "size-4", "aria-hidden": "true" });
              $$renderer4.push(`<!----><span>More</span>`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> `);
          if (moreOpen) {
            $$renderer3.push("<!--[0-->");
            $$renderer3.push(`<div id="advising-note-more-actions" class="absolute right-0 z-20 mt-1 grid min-w-48 gap-1 rounded-md border bg-background p-2 shadow-md" aria-label="More note actions"><button type="button" class="flex min-h-11 items-center gap-2 rounded px-2 text-left text-sm hover:bg-muted"${attr("disabled", busy, true)}>`);
            Copy($$renderer3, { class: "size-4", "aria-hidden": "true" });
            $$renderer3.push(`<!---->Copy to year</button> <button type="button" class="flex min-h-11 items-center gap-2 rounded px-2 text-left text-sm hover:bg-muted"${attr("disabled", busy, true)}>`);
            Undo_2($$renderer3, { class: "size-4", "aria-hidden": "true" });
            $$renderer3.push(`<!---->Restore a version</button></div>`);
          } else {
            $$renderer3.push("<!--[-1-->");
          }
          $$renderer3.push(`<!--]--></div></div> `);
          children?.($$renderer3);
          $$renderer3.push(`<!---->`);
        } else if (noteItems().length > 0) {
          $$renderer3.push("<!--[1-->");
          $$renderer3.push(`<p class="py-8 text-sm text-muted-foreground">Choose a note to view or edit it.</p>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]--></div></div> `);
        if (notes.pagination?.last_page > 1) {
          $$renderer3.push("<!--[0-->");
          $$renderer3.push(`<div class="flex items-center gap-3 border-t pt-3" aria-label="Advising note pages">`);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            size: "sm",
            disabled: busy || notes.pagination.current_page <= 1,
            onclick: () => onPage(notes.pagination.current_page - 1),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Previous`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----> <span class="text-xs text-muted-foreground">Page ${escape_html(notes.pagination.current_page)} of ${escape_html(notes.pagination.last_page)}</span> `);
          Button($$renderer3, {
            type: "button",
            variant: "outline",
            size: "sm",
            disabled: busy || notes.pagination.current_page >= notes.pagination.last_page,
            onclick: () => onPage(notes.pagination.current_page + 1),
            children: ($$renderer4) => {
              $$renderer4.push(`<!---->Next`);
            },
            $$slots: { default: true }
          });
          $$renderer3.push(`<!----></div>`);
        } else {
          $$renderer3.push("<!--[-1-->");
        }
        $$renderer3.push(`<!--]-->`);
      }
      $$renderer3.push(`<!--]--></section>`);
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
    bind_props($$props, { search });
  });
}
function TabFmStudio($$renderer, $$props) {
  $$renderer.component(($$renderer2) => {
    let { governance = {} } = $$props;
    let programId = "";
    let cycleId = "";
    let showScopeChooser = false;
    let comparisonData = null;
    let comparisonBaselineId = "";
    let comparisonRevisionId = "";
    let comparisonBusy = false;
    let availableBindingIds = [];
    let bindingSourceId = "";
    let bindingFeatureKey = "";
    let bindingUnit = "";
    let bindingModelRequired = false;
    let bindingReason = "";
    let bindingApplyReason = "";
    let subjectActivities = [];
    let activitiesReviewed = false;
    let activeTab = "calibrate";
    let discussionTopic = "benchmark";
    let selectedBand = "cutoff";
    let previewOpen = false;
    let moreOpen = false;
    let content = null;
    let savedContent = null;
    let draft = null;
    let recovery = null;
    let activeSet = null;
    let reason = "";
    let busy = false;
    let error = "";
    let notice = "";
    let denied = false;
    let pendingCommand = null;
    let historySequence = 0;
    let comparisonSequence = 0;
    let noteSequence = 0;
    let benchmarkHistory = { items: [], pagination: null };
    let inspectedRevision = null;
    let publishedReceipt = null;
    let confirmation = null;
    let confirmationOpen = false;
    let confirmed = false;
    let noteBoard = governance.notes ?? { items: [], pagination: null, counts: null };
    let noteSearch = "";
    let selectedNoteId = null;
    let selectedNote = null;
    let noteLoading = false;
    let noteDraft = null;
    let latestNoteDraft = null;
    let publishedNote = null;
    let noteText = "";
    let noteReason = "";
    let noteHistory = { items: [], pagination: null };
    let publicationHistory = { items: [], pagination: null };
    let copyCycle = "";
    let copySource = "";
    let noteInspected = null;
    let importText = "";
    let importReason = "";
    let source = "active";
    let gwa = "";
    let strand = "";
    let aptitudeInputs = {};
    let subjectInputs = {};
    let preview = { status: "idle", current: null, result: null, error: "" };
    const program = derived(() => governance.selected_program);
    const cycle = derived(() => governance.selected_cycle);
    const scope = derived(() => `${program()?.id ?? ""}:${cycle()?.id ?? ""}`);
    const capabilities = derived(() => denied ? {} : governance.capabilities ?? {});
    const features = derived(() => governance.schema_features ?? []);
    const inputSchema = derived(() => governance.input_schema ?? { subjects: [], activities: [] });
    const previewSchema = derived(() => source === "active" ? governance.active_input_schema ?? { subjects: [], activities: [] } : inputSchema());
    const previewSubjects = derived(() => previewSchema().subjects ?? []);
    const previewActivities = derived(() => previewSchema().activities ?? []);
    const previewActivityGroups = derived(() => [
      "riasec_r",
      "riasec_i",
      "riasec_a",
      "riasec_s",
      "riasec_e",
      "riasec_c"
    ].map((parent) => ({
      parent,
      items: previewActivities().filter((activity) => activity.parent === parent)
    })));
    const aptitudeAreas = derived(() => governance.aptitude_areas ?? []);
    const dirty = derived(() => JSON.stringify(content) !== JSON.stringify(savedContent));
    const noteDirty = derived(() => noteText !== (noteDraft?.content ?? ""));
    const noteServerChanged = derived(() => noteDraft?.version !== latestNoteDraft?.version || noteDraft?.current_revision_id !== latestNoteDraft?.current_revision_id || noteDraft?.content !== latestNoteDraft?.content);
    const pendingNoteSaveRetry = derived(() => pendingCommand?.action === "advising-note.save");
    const pendingDraftSave = derived(() => ["benchmarks.draft.save", "benchmarks.draft.start"].includes(pendingCommand?.action));
    const tabBadges = derived(() => governance.tab_badges ?? {});
    const previewHasIncompleteRanges = derived(() => source === "draft" && hasIncompleteAuthoredRange(tierContent(draft?.content)));
    const previewComplete = derived(() => !previewHasIncompleteRanges() && gwa !== "" && gwa != null && Number.isFinite(Number(gwa)) && strand.trim() !== "" && aptitudeAreas().length > 0 && aptitudeAreas().every((area) => aptitudeInputs[area.code] !== "" && aptitudeInputs[area.code] != null && Number.isFinite(Number(aptitudeInputs[area.code]))) && (source === "active" ? !!activeSet : !!draft?.current_revision_id));
    const diffs = derived(() => {
      const rows = [];
      const reviewed = confirmation?.content ?? inspectedRevision?.content ?? draft?.content;
      if (!reviewed) return rows;
      for (const level of ["1", "2", "3", "4", "5"]) {
        for (const feature of features()) {
          const before = governance.active_content?.tiers?.[level]?.values?.[feature.key] ?? null;
          const after = reviewed.tiers?.[level]?.values?.[feature.key] ?? null;
          if (JSON.stringify(before) !== JSON.stringify(after)) rows.push({ level, feature, before, after });
        }
      }
      return rows;
    });
    const breadcrumbs = [{ label: "Setup" }, { label: "TabFM" }];
    function tierContent(value) {
      return cloneTabFmContent(value);
    }
    function beginScopeChange() {
      if (dirty() || noteDirty() || busy || pendingCommand) {
        error = "Save or discard unsaved changes and finish pending actions before changing programme or academic year.";
        return;
      }
      programId = String(program()?.id ?? "");
      cycleId = String(cycle()?.id ?? "");
      showScopeChooser = true;
    }
    function cancelScopeChange() {
      programId = String(program()?.id ?? "");
      cycleId = String(cycle()?.id ?? "");
      showScopeChooser = false;
      error = "";
    }
    async function command(action, payload, cycleOnly = false) {
      if (busy) return null;
      const capturedScope = scope();
      const fingerprint = JSON.stringify({ scope: scope(), action, payload });
      if (pendingCommand && pendingCommand.fingerprint !== fingerprint) {
        error = "A previous command outcome is unknown. Retry that exact action before starting another change.";
        return null;
      }
      if (!pendingCommand) pendingCommand = {
        fingerprint,
        key: newIdempotencyKey(),
        action,
        payload: { ...payload }
      };
      busy = true;
      error = "";
      notice = "";
      try {
        const data = await governanceRequest(
          governanceRoute(`setup.admission-support.${action}`, cycleOnly ? { cycle: cycle().id } : { program: program().id, cycle: cycle().id }),
          { ...payload, idempotency_key: pendingCommand.key }
        );
        if (scope() !== capturedScope) return null;
        pendingCommand = null;
        return data;
      } catch (failure) {
        if (scope() === capturedScope) {
          error = governanceError(failure);
          if (!isUncertainCommandOutcome(failure)) pendingCommand = null;
          if (failure instanceof GovernanceRequestError && failure.status === 403) {
            denied = true;
            preview = invalidatePreview(preview);
          }
        }
        return null;
      } finally {
        if (scope() === capturedScope) busy = false;
      }
    }
    async function saveDraft() {
      const isStarting = !draft?.current_revision_id && !content || pendingCommand?.action === "benchmarks.draft.start";
      const action = isStarting ? "benchmarks.draft.start" : "benchmarks.draft.save";
      const payload = pendingCommand?.action === action ? pendingCommand.payload : isStarting ? { expected_version: 0, reason } : {
        content,
        expected_version: draft?.current_version ?? 0,
        reason
      };
      if (!String(payload.reason ?? "").trim() || String(payload.reason).length > 500 || !isStarting && !payload.content) {
        error = "Enter a reason of up to 500 characters before saving.";
        return false;
      }
      const data = await command(action, payload);
      if (!data) return false;
      draft = {
        id: data.revision.draft_id,
        current_version: data.revision.version,
        current_revision_id: data.revision.id,
        content: data.revision.content,
        base_benchmark_set_id: data.revision.base_benchmark_set_id,
        feature_schema_digest: data.revision.feature_schema_digest
      };
      content = tierContent(data.revision.content);
      savedContent = tierContent(data.revision.content);
      reason = "";
      notice = isStarting ? "Draft started. Nothing has been published or put in use." : `Draft v${data.revision.version} saved. Live benchmarks are unchanged.`;
      await loadBenchmarkHistory(1);
      const recoveryRefreshed = await refreshRecovery();
      await refreshGovernanceProjection();
      return !dirty() && !busy && !pendingCommand && !error && (!recovery || recoveryRefreshed);
    }
    async function refreshRecovery() {
      if (!recovery || !program() || !cycle()) return false;
      try {
        const data = await governanceRequest(governanceRoute("setup.admission-support.governance.show", { program: program().id, cycle: cycle().id }));
        if (!data.recovery) return false;
        recovery = { ...recovery, ...data.recovery };
        return true;
      } catch (failure) {
        error = governanceError(failure);
        if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
        return false;
      }
    }
    async function recoverDraft() {
      if (!capabilities()["admission.benchmark.draft.edit"] || !recovery || recovery.program_in_source !== true || draft?.current_revision_id || recovery.status !== "available") return;
      if (!reason.trim()) {
        error = "Enter a reason before recovering this historical set as a draft.";
        return;
      }
      const data = await command("benchmarks.draft.recover", {
        expected_version: draft?.current_version ?? 0,
        expected_source_digest: recovery.source_snapshot_digest,
        reason
      });
      if (!data) return;
      draft = {
        id: data.revision.draft_id,
        current_version: data.revision.version,
        current_revision_id: data.revision.id,
        content: data.revision.content,
        base_benchmark_set_id: data.revision.base_benchmark_set_id,
        feature_schema_digest: data.revision.feature_schema_digest
      };
      content = tierContent(data.revision.content);
      savedContent = tierContent(data.revision.content);
      recovery = { ...recovery, ...data.recovery ?? {}, status: "draft" };
      reason = "";
      notice = `Historical set #${recovery.source_set_id} recovered as draft v${data.revision.version}. It remains unpublished and inactive.`;
      await loadBenchmarkHistory(1);
      await refreshRecovery();
      await refreshGovernanceProjection();
    }
    async function rebaseDraft() {
      if (!capabilities()["admission.benchmark.draft.edit"] || !draft?.current_revision_id || !activeSet || dirty()) return;
      if (!reason.trim()) {
        error = "Enter a specific reason in the calibration editor before rebasing the saved draft.";
        return;
      }
      const data = await command("benchmarks.draft.rebase", {
        expected_version: draft.current_version,
        benchmark_set_id: activeSet.id,
        expected_content_sha256: activeSet.content_sha256,
        reason
      });
      if (!data) return;
      draft = {
        id: data.revision.draft_id,
        current_version: data.revision.version,
        current_revision_id: data.revision.id,
        content: data.revision.content,
        base_benchmark_set_id: data.revision.base_benchmark_set_id,
        feature_schema_digest: data.revision.feature_schema_digest
      };
      content = tierContent(data.revision.content);
      savedContent = tierContent(data.revision.content);
      reason = "";
      notice = `Saved draft rebased as v${data.revision.version} to active set #${data.revision.base_benchmark_set_id}. Live benchmarks are unchanged.`;
      await loadBenchmarkHistory(1);
      await refreshGovernanceProjection();
    }
    async function loadBenchmarkHistory(page = 1) {
      const capturedScope = scope();
      const sequence = ++historySequence;
      try {
        const data = await governanceRequest(`${governanceRoute("setup.admission-support.benchmarks.history", { program: program().id, cycle: cycle().id })}?page=${page}&per_page=20`);
        if (capturedScope === scope() && sequence === historySequence) benchmarkHistory = data;
      } catch (failure) {
        if (capturedScope === scope() && sequence === historySequence) {
          error = governanceError(failure);
          if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
        }
      }
    }
    async function loadComparison(setId = comparisonBaselineId, revisionId = comparisonRevisionId) {
      if (!capabilities()["admission.benchmark.draft.edit"] && !capabilities()["admission.benchmark.preview"]) return;
      if (!revisionId) {
        error = "Choose an exact saved revision before comparing. The working copy is shown separately.";
        return;
      }
      const capturedScope = scope();
      const sequence = ++comparisonSequence;
      comparisonData = null;
      comparisonBusy = true;
      error = "";
      const query = new URLSearchParams({ revision_id: String(revisionId) });
      if (setId) query.set("set_id", String(setId));
      try {
        const data = await governanceRequest(`${governanceRoute("setup.admission-support.benchmarks.comparison", { program: program().id, cycle: cycle().id })}?${query.toString()}`);
        if (capturedScope === scope() && sequence === comparisonSequence) {
          comparisonData = data.comparison;
          comparisonBaselineId = String(data.comparison.baseline.id);
          comparisonRevisionId = String(data.comparison.revision.id);
        }
      } catch (failure) {
        if (capturedScope === scope() && sequence === comparisonSequence) {
          error = governanceError(failure);
          if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
        }
      } finally {
        if (capturedScope === scope() && sequence === comparisonSequence) comparisonBusy = false;
      }
    }
    async function restoreBenchmark(revision) {
      if (!reason.trim()) {
        error = "Enter a reason in the calibration editor before restoring a revision.";
        return;
      }
      if (dirty()) {
        error = "Save or discard unsaved benchmark changes before restoring.";
        return;
      }
      const data = await command("benchmarks.draft.restore", {
        source_revision_id: revision.id,
        expected_version: draft?.current_version ?? 0,
        reason
      });
      if (!data) return;
      draft = {
        id: data.revision.draft_id,
        current_version: data.revision.version,
        current_revision_id: data.revision.id,
        content: data.revision.content,
        base_benchmark_set_id: data.revision.base_benchmark_set_id,
        feature_schema_digest: data.revision.feature_schema_digest
      };
      content = tierContent(data.revision.content);
      savedContent = tierContent(data.revision.content);
      notice = `Revision #${revision.id} restored as new draft v${data.revision.version}.`;
      reason = "";
      await loadBenchmarkHistory(1);
      await refreshRecovery();
      await refreshGovernanceProjection();
    }
    function applyNoteDetail(data) {
      const refreshed = applyGovernanceNoteDetail(
        {
          selectedNote,
          selectedNoteId,
          draft: noteDraft,
          latestDraft: latestNoteDraft,
          text: noteText
        },
        data
      );
      selectedNote = refreshed.selectedNote;
      selectedNoteId = refreshed.selectedNoteId;
      noteDraft = refreshed.draft;
      latestNoteDraft = refreshed.latestDraft;
      publishedNote = refreshed.published;
      noteHistory = refreshed.history;
      publicationHistory = refreshed.publicationHistory;
      noteText = refreshed.text;
    }
    async function selectNote(noteId) {
      if (noteDirty()) {
        error = "Save or discard this note before opening another.";
        return;
      }
      if (pendingCommand?.action?.startsWith("advising-note.")) {
        error = "Retry the pending note action before opening another note.";
        return;
      }
      const token = beginGovernanceRequest(scope(), noteSequence);
      noteSequence = token.sequence;
      selectedNoteId = Number(noteId);
      selectedNote = null;
      noteDraft = null;
      latestNoteDraft = null;
      publishedNote = null;
      noteText = "";
      noteLoading = true;
      noteHistory = { items: [], pagination: null };
      publicationHistory = { items: [], pagination: null };
      try {
        const data = await governanceRequest(`${governanceRoute("setup.admission-support.governance.show", { program: program().id, cycle: cycle().id })}?note_id=${encodeURIComponent(noteId)}`);
        if (!isCurrentGovernanceRequest(token, scope(), noteSequence)) return;
        noteBoard = data.notes ?? noteBoard;
        applyNoteDetail(data);
      } catch (failure) {
        if (isCurrentGovernanceRequest(token, scope(), noteSequence)) {
          error = governanceError(failure);
          if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
        }
      } finally {
        if (isCurrentGovernanceRequest(token, scope(), noteSequence)) noteLoading = false;
      }
    }
    function startNewNote() {
      if (!capabilities()["admission.advising-note.draft.edit"]) return;
      if (pendingCommand?.action?.startsWith("advising-note.")) {
        error = "Retry the pending note action before starting a new note.";
        return;
      }
      if (noteDirty() && !window.confirm("Discard unsaved changes to this note?")) return;
      noteSequence++;
      selectedNoteId = null;
      selectedNote = { note_id: null, version: 0, content: "" };
      noteLoading = false;
      noteDraft = null;
      latestNoteDraft = null;
      publishedNote = null;
      noteText = "";
      noteHistory = { items: [], pagination: null };
      publicationHistory = { items: [], pagination: null };
      noteInspected = null;
      noteReason = "";
      error = "";
    }
    async function refreshNotes(notePage = 1, publicationPage = 1) {
      const token = beginGovernanceRequest(scope(), noteSequence);
      noteSequence = token.sequence;
      try {
        const params = new URLSearchParams({
          note_page: String(notePage),
          publication_page: String(publicationPage)
        });
        if (noteSearch.trim()) params.set("note_search", noteSearch.trim());
        if (selectedNoteId) params.set("note_id", String(selectedNoteId));
        const data = await governanceRequest(`${governanceRoute("setup.admission-support.governance.show", { program: program().id, cycle: cycle().id })}?${params}`);
        if (!isCurrentGovernanceRequest(token, scope(), noteSequence)) return;
        noteBoard = data.notes ?? noteBoard;
        if (selectedNoteId && data.note) applyNoteDetail(data);
      } catch (failure) {
        if (isCurrentGovernanceRequest(token, scope(), noteSequence)) {
          error = governanceError(failure);
          if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
        }
      }
    }
    async function noteAction(operation, revision = null) {
      const action = `advising-note.${operation}`;
      const retryPayload = pendingCommand?.action === action ? reusePendingGovernancePayload(action, {}, pendingCommand) : null;
      let payload;
      if (retryPayload) {
        payload = retryPayload;
      } else {
        if (!noteReason.trim()) {
          error = "Enter a reason for this note action.";
          return;
        }
        if (operation !== "save" && operation !== "copy" && noteDirty()) {
          error = "Save or discard unsaved note text before this action.";
          return;
        }
        if (operation !== "save" && operation !== "copy" && !selectedNoteId) {
          error = "Choose a saved note before this action.";
          return;
        }
        payload = {
          expected_version: noteDraft?.version ?? 0,
          reason: noteReason,
          ...selectedNoteId ? { note_id: selectedNoteId } : {}
        };
        if (operation === "save") {
          payload.content = noteText;
          if (selectedNoteId) payload.note_id = selectedNoteId;
        }
        if (operation === "publish") {
          payload.note_id = selectedNoteId;
          payload.revision_id = confirmation?.operation === "note-publish" ? confirmation.revisionId : noteDraft.current_revision_id;
        }
        if (operation === "restore") {
          payload.note_id = selectedNoteId;
          payload.source_revision_id = revision.id;
        }
        if (operation === "retract") {
          try {
            const current = await governanceRequest(`${governanceRoute("setup.admission-support.governance.show", { program: program().id, cycle: cycle().id })}?note_id=${selectedNoteId}&publication_page=1`);
            const reviewedId = confirmation?.operation === "note-retract" ? confirmation.revisionId : publishedNote?.revision_id;
            if (current.note?.published?.revision_id !== reviewedId) {
              error = "The shared note changed during review. Refresh and review the current version.";
              return;
            }
            const publication = (current.note.publication_history?.items ?? []).find((item) => item.operation === "publish" && item.revision_id === reviewedId);
            if (!publication) {
              error = "The exact sharing receipt is unavailable; unsharing was not submitted.";
              return;
            }
            payload.note_id = selectedNoteId;
            payload.publication_id = publication.id;
          } catch (failure) {
            error = governanceError(failure);
            return;
          }
        }
        if (operation === "copy") {
          {
            error = "Choose an exact source revision and a different target academic year.";
            return;
          }
        }
      }
      const data = await command(action, payload);
      if (!data) return;
      if (operation === "save" && data.revision) {
        selectedNoteId = data.revision.note_id ?? data.revision.program_advising_note_id ?? selectedNoteId;
        noteDraft = {
          ...noteDraft ?? {},
          note_id: selectedNoteId,
          version: data.revision.version,
          current_revision_id: data.revision.id,
          content: data.revision.content
        };
        latestNoteDraft = noteDraft;
      }
      if (operation === "copy") {
        const targetCycle = governance.cycles?.find((item) => String(item.id) === String(payload.target_cycle_id));
        notice = `Revision #${payload.source_revision_id} copied to ${targetCycle?.staff_label ?? targetCycle?.name ?? `academic year #${payload.target_cycle_id}`} as a draft, not shared.`;
      } else {
        notice = `Note ${operation === "save" ? "draft saved" : operation === "restore" ? "restored as a new version" : operation === "publish" ? "shared" : "unshared"}.`;
      }
      if (noteReason === payload.reason) noteReason = "";
      await refreshNotes(1, 1);
      await refreshGovernanceProjection();
    }
    function refreshGovernanceProjection() {
      router.reload({
        only: ["governance"],
        preserveState: true,
        preserveScroll: true
      });
    }
    async function applyApprovedSubjectBindings() {
      if (!capabilities()["admission.benchmark.draft.edit"]) return;
      if (dirty() || busy || pendingCommand) {
        error = "Save or discard the current draft changes and finish pending actions before applying approved subject bindings.";
        return;
      }
      if (!availableBindingIds.length || !bindingApplyReason.trim()) {
        error = "Select approved subject bindings and enter a reason for this schema change.";
        return;
      }
      const data = await command("benchmarks.subject-bindings.apply", {
        binding_ids: availableBindingIds.map(Number),
        expected_version: draft?.current_version ?? 0,
        reason: bindingApplyReason.trim()
      });
      if (!data) return;
      if (data.revision) {
        draft = {
          id: data.revision.draft_id,
          current_version: data.revision.version,
          current_revision_id: data.revision.id,
          content: data.revision.content,
          base_benchmark_set_id: data.revision.base_benchmark_set_id,
          feature_schema_digest: data.revision.feature_schema_digest
        };
        content = tierContent(data.revision.content);
        savedContent = tierContent(data.revision.content);
      }
      availableBindingIds = [];
      bindingApplyReason = "";
      notice = "Approved subject bindings applied in a new saved draft revision. Review and rebase remain explicit.";
      await loadBenchmarkHistory(1);
      refreshGovernanceProjection();
    }
    async function reviewSet(operation, selectedSet = publishedReceipt) {
      if (busy) return;
      if (operation === "publish" && (dirty() || !draft?.current_revision_id)) {
        error = "Save and review the exact draft revision before publication.";
        return;
      }
      if (operation === "publish") {
        if (recovery?.status === "draft") {
          busy = true;
          const refreshed = await refreshRecovery();
          busy = false;
          if (!refreshed || recovery?.status !== "draft") {
            error ||= "Current recovery publication metadata could not be refreshed. No revision manifest was submitted.";
            return;
          }
          const revisionIds = recovery.revision_ids_by_program ?? null;
          const incomplete = recovery.incomplete_programs ?? [];
          if (revisionIds !== null && (typeof revisionIds !== "object" || Array.isArray(revisionIds)) || !revisionIds && incomplete.length === 0) {
            error = "The current exact recovery revision manifest is unavailable. No revision map was inferred.";
            return;
          }
          const manifest = revisionIds ? Object.entries(revisionIds).sort(([left], [right]) => Number(left) - Number(right)) : [];
          const complete = manifest.length === Number(recovery.source_program_count) && incomplete.length === 0;
          confirmation = {
            operation,
            recovery: true,
            revisionId: draft.current_revision_id,
            version: draft.current_version,
            content: tierContent(draft.content),
            revisionIds,
            manifest,
            incomplete,
            publishBlocked: !complete || String(revisionIds?.[String(program().id)] ?? "") !== String(draft.current_revision_id),
            baseDigest: recovery.expected_content_sha256,
            recoverySourceSetId: recovery.source_set_id,
            sourceSnapshotDigest: recovery.expected_source_snapshot_digest,
            sourceActivationId: recovery.expected_source_activation_id,
            activationId: recovery.source_activation_id
          };
        } else {
          confirmation = {
            operation,
            revisionId: draft.current_revision_id,
            version: draft.current_version,
            content: tierContent(draft.content),
            baseDigest: activeSet?.content_sha256,
            activationId: activeSet?.activation_id ?? null
          };
        }
      } else {
        if (!selectedSet || busy) return;
        busy = true;
        try {
          const data = await governanceRequest(`${governanceRoute("setup.admission-support.governance.show", { program: program().id, cycle: cycle().id })}?benchmark_set_id=${selectedSet.id}`);
          confirmation = {
            operation,
            set: data.benchmark_review.set,
            content: data.benchmark_review.content,
            activationId: activeSet?.activation_id ?? recovery?.source_activation_id ?? null
          };
        } catch (failure) {
          error = governanceError(failure);
          if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
          return;
        } finally {
          busy = false;
        }
      }
      confirmed = false;
      confirmationOpen = true;
    }
    async function confirmSet() {
      const reviewed = confirmation;
      if (!reviewed || !confirmed) return;
      let data;
      if (reviewed.operation === "publish") {
        if (!reason.trim()) {
          error = "Enter a publication reason in the review dialog.";
          return;
        }
        if (reviewed.publishBlocked) {
          error = "The exact complete recovery manifest is unavailable or incomplete. Review the listed source-program gaps; no partial recovery publication was submitted.";
          return;
        }
        const publishPayload = reviewed.recovery ? {
          revision_ids_by_program: reviewed.revisionIds,
          expected_content_sha256: reviewed.baseDigest,
          recovery_source_set_id: reviewed.recoverySourceSetId,
          expected_source_snapshot_digest: reviewed.sourceSnapshotDigest,
          expected_source_activation_id: reviewed.sourceActivationId,
          reason
        } : {
          revision_ids_by_program: { [program().id]: reviewed.revisionId },
          expected_content_sha256: reviewed.baseDigest,
          reason
        };
        data = await command("benchmarks.publish", publishPayload, true);
        if (data) {
          publishedReceipt = {
            ...data.set,
            reviewed_content: reviewed.content,
            reviewed_revision_id: reviewed.revisionId
          };
          notice = `Set v${data.set.version} published. It is not active.`;
        }
      } else {
        data = await command(
          "benchmarks.activate",
          {
            benchmark_set_id: reviewed.set.id,
            expected_current_activation_id: reviewed.activationId,
            expected_content_sha256: reviewed.set.content_sha256
          },
          true
        );
        if (data) {
          activeSet = { ...reviewed.set, activation_id: data.activation.id };
          publishedReceipt = null;
          notice = `Published set #${reviewed.set.id} activated for ${cycle().staff_label ?? cycle().name}.`;
          router.reload({
            only: ["governance"],
            onFinish: () => {
            },
            onSuccess: (page) => {
              const refreshed = page.props.governance;
              activeSet = refreshed.active_set;
              recovery = refreshed.recovery ?? null;
              tierContent(refreshed.recovery_content);
              preview = invalidatePreview(preview);
            }
          });
        }
      }
      if (data) {
        confirmationOpen = false;
        reason = "";
      }
    }
    let $$settled = true;
    let $$inner_renderer;
    function $$render_inner($$renderer3) {
      StaffLayout($$renderer3, {
        breadcrumbs,
        children: ($$renderer4) => {
          $$renderer4.push(`<div class="min-w-0 space-y-5">`);
          PageHeaderBar($$renderer4, {
            title: "TabFM",
            description: "Programme benchmarks and advising context"
          });
          $$renderer4.push(`<!----> <div class="min-w-0 space-y-4">`);
          if (showScopeChooser || !program() || !cycle()) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<form class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto_auto]"><div class="min-w-0 space-y-1"><label for="studio-program" class="text-sm font-medium">Programme</label>`);
            $$renderer4.select(
              {
                id: "studio-program",
                class: "h-11 w-full min-w-0 rounded-md border bg-background px-3 text-sm",
                value: programId,
                disabled: busy || !!pendingCommand
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "" }, ($$renderer6) => {
                  $$renderer6.push(`Select programme`);
                });
                $$renderer5.push(`<!--[-->`);
                const each_array = ensure_array_like(governance.programs ?? []);
                for (let $$index = 0, $$length = each_array.length; $$index < $$length; $$index++) {
                  let item = each_array[$$index];
                  $$renderer5.option({ value: String(item.id) }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(item.code)} · ${escape_html(item.name)}`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(`</div> <div class="min-w-0 space-y-1"><label for="studio-cycle" class="text-sm font-medium">Academic year · Semester</label>`);
            $$renderer4.select(
              {
                id: "studio-cycle",
                class: "h-11 w-full rounded-md border bg-background px-3 text-sm",
                value: cycleId,
                disabled: busy || !!pendingCommand
              },
              ($$renderer5) => {
                $$renderer5.option({ value: "" }, ($$renderer6) => {
                  $$renderer6.push(`Select academic year and semester`);
                });
                $$renderer5.push(`<!--[-->`);
                const each_array_1 = ensure_array_like(governance.cycles ?? []);
                for (let $$index_1 = 0, $$length = each_array_1.length; $$index_1 < $$length; $$index_1++) {
                  let item = each_array_1[$$index_1];
                  $$renderer5.option({ value: String(item.id) }, ($$renderer6) => {
                    $$renderer6.push(`${escape_html(item.staff_label ?? item.name)}`);
                  });
                }
                $$renderer5.push(`<!--]-->`);
              }
            );
            $$renderer4.push(`</div> `);
            Button($$renderer4, {
              type: "submit",
              variant: "outline",
              class: "self-end",
              disabled: busy || !!pendingCommand || !programId || !cycleId,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Open context`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            if (program() && cycle()) {
              $$renderer4.push("<!--[0-->");
              Button($$renderer4, {
                type: "button",
                variant: "ghost",
                class: "self-end",
                disabled: busy || !!pendingCommand,
                onclick: cancelScopeChange,
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Cancel`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></form>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (error) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<div role="alert" class="rounded-lg border border-destructive/40 p-3 text-sm text-destructive">${escape_html(error)}`);
            if (error.startsWith("Conflict:")) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<p class="mt-2 text-foreground">Your input is kept. Inspect Changes, then explicitly reload the current context to reconcile; no overwrite was applied.</p>`);
              Button($$renderer4, {
                variant: "outline",
                size: "sm",
                class: "mt-2",
                onclick: () => router.get(governanceRoute("setup.admission-support.tabfm"), { program_id: program().id, cycle_id: cycle().id }, { preserveState: false }),
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Reload current context (discard unsaved input)`);
                },
                $$slots: { default: true }
              });
              $$renderer4.push(`<!---->`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--></div>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (notice) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p role="status" class="text-sm">${escape_html(notice)}</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
          }
          $$renderer4.push(`<!--]--> `);
          if (!program() || !cycle() || showScopeChooser) {
            $$renderer4.push("<!--[0-->");
            $$renderer4.push(`<p class="rounded-lg border p-5 text-sm text-muted-foreground">Choose an authorized programme and academic year/semester to inspect its benchmark workspace. No context has been substituted.</p>`);
          } else if (denied) {
            $$renderer4.push("<!--[1-->");
            $$renderer4.push(`<p role="alert" class="rounded-lg border p-5 text-sm">Your access to this context is no longer available. Unsaved input has been kept; no further commands are enabled.</p>`);
          } else {
            $$renderer4.push("<!--[-1-->");
            $$renderer4.push(`<div class="flex flex-wrap items-center justify-between gap-3 border-y py-3"><nav aria-label="Selected programme context"><ol class="flex flex-wrap items-center gap-2 text-sm"><li><span class="text-muted-foreground">Programme</span> · ${escape_html(program().code)} · ${escape_html(program().name)}</li><li aria-hidden="true" class="text-muted-foreground">/</li><li aria-current="page"><span class="text-muted-foreground">Academic year · Semester</span> · ${escape_html(cycle().staff_label ?? cycle().name)}</li></ol></nav> `);
            Button($$renderer4, {
              type: "button",
              variant: "outline",
              size: "sm",
              disabled: busy || !!pendingCommand,
              onclick: beginScopeChange,
              children: ($$renderer5) => {
                $$renderer5.push(`<!---->Change context`);
              },
              $$slots: { default: true }
            });
            $$renderer4.push(`<!----> `);
            if (governance.return_to) {
              $$renderer4.push("<!--[0-->");
              Link($$renderer4, {
                href: governance.return_to,
                class: "min-h-11 inline-flex items-center text-sm underline underline-offset-4",
                children: ($$renderer5) => {
                  $$renderer5.push(`<!---->Return to consultation`);
                },
                $$slots: { default: true }
              });
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <div class="flex flex-wrap items-center gap-2 text-sm"><span>${escape_html(activeSet ? `In use · v${activeSet.version}` : "Not in use")}</span><span aria-hidden="true" class="text-muted-foreground">·</span> <span>${escape_html(draft?.current_revision_id ? `Saved draft r${draft.current_revision_id} · v${draft.current_version}` : "No saved draft")}</span> `);
            if (recovery) {
              $$renderer4.push("<!--[0-->");
              $$renderer4.push(`<span class="text-muted-foreground">· ${escape_html(recovery.status === "draft" ? "Historical draft recovered" : "Historical recovery available")}</span>`);
            } else {
              $$renderer4.push("<!--[-1-->");
            }
            $$renderer4.push(`<!--]--> <details class="text-xs"><summary class="min-h-11 cursor-pointer inline-flex items-center">History and technical details</summary><dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1"><dt>Published set / activation</dt><dd>${escape_html(activeSet ? `#${activeSet.id} · activation #${activeSet.activation_id}` : "None")}</dd><dt>Draft revision</dt><dd>${escape_html(draft?.current_revision_id ?? "None")}</dd><dt>Content digest</dt><dd class="break-all">${escape_html(activeSet?.content_sha256 ?? "Unavailable")}</dd><dt>Schema digest</dt><dd class="break-all">${escape_html(activeSet?.schema_sha256 ?? draft?.feature_schema_digest ?? "Unavailable")}</dd></dl></details></div></div> <div class="min-w-0"><main id="studio-editor" tabindex="-1" class="min-w-0">`);
            if (Tabs) {
              $$renderer4.push("<!--[-->");
              Tabs($$renderer4, {
                get value() {
                  return activeTab;
                },
                set value($$value) {
                  activeTab = $$value;
                  $$settled = false;
                },
                children: ($$renderer5) => {
                  $$renderer5.push(`<div class="flex flex-wrap items-center justify-between gap-3">`);
                  if (Tabs_list) {
                    $$renderer5.push("<!--[-->");
                    Tabs_list($$renderer5, {
                      class: "flex h-auto flex-wrap justify-start gap-1",
                      children: ($$renderer6) => {
                        if (Tabs_trigger) {
                          $$renderer6.push("<!--[-->");
                          Tabs_trigger($$renderer6, {
                            value: "calibrate",
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Benchmarks `);
                              if (tabBadges().benchmarks) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`<span class="ml-1 rounded-full border px-1.5 text-xs"${attr("title", benchmarkStatusLabel(tabBadges().benchmarks))}>${escape_html(tabBadges().benchmarks.label)}</span>`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                              }
                              $$renderer7.push(`<!--]-->`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        $$renderer6.push(` `);
                        if (capabilities()["admission.advising-note.view"]) {
                          $$renderer6.push("<!--[0-->");
                          if (Tabs_trigger) {
                            $$renderer6.push("<!--[-->");
                            Tabs_trigger($$renderer6, {
                              value: "notes",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Advising notes `);
                                if (typeof tabBadges().notes?.total === "number" && typeof tabBadges().notes?.drafts === "number" && typeof tabBadges().notes?.shared === "number") {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`<span class="ml-1 rounded-full border px-1.5 text-xs">${escape_html(tabBadges().notes.total)} · ${escape_html(tabBadges().notes.drafts)} drafts · ${escape_html(tabBadges().notes.shared)} shared</span>`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]-->`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (["benchmark", "quota", "advising"].some((topic) => capabilities()[`admission.governance-discussion.${topic}.read`])) {
                          $$renderer6.push("<!--[0-->");
                          if (Tabs_trigger) {
                            $$renderer6.push("<!--[-->");
                            Tabs_trigger($$renderer6, {
                              value: "discussion",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Discussion `);
                                if (typeof tabBadges().discussion?.unread_threads === "number") {
                                  $$renderer7.push("<!--[0-->");
                                  $$renderer7.push(`<span class="ml-1 rounded-full border px-1.5 text-xs">${escape_html(tabBadges().discussion.unread_threads)} unread threads</span>`);
                                } else {
                                  $$renderer7.push("<!--[-1-->");
                                }
                                $$renderer7.push(`<!--]-->`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push("<!--]-->");
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push("<!--]-->");
                          }
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` <div class="flex gap-2">`);
                  if (capabilities()["admission.benchmark.preview"]) {
                    $$renderer5.push("<!--[0-->");
                    Button($$renderer5, {
                      variant: "outline",
                      onclick: () => {
                        source = draft?.current_revision_id ? "draft" : "active";
                        previewOpen = true;
                      },
                      children: ($$renderer6) => {
                        $$renderer6.push(`<!---->Preview`);
                      },
                      $$slots: { default: true }
                    });
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> <details${attr("open", moreOpen, true)} class="relative"><summary class="flex min-h-11 cursor-pointer list-none items-center rounded-md border px-3 text-sm">More</summary><div class="absolute right-0 z-20 grid min-w-48 gap-1 rounded-md border bg-background p-2 shadow-md"><button type="button" class="min-h-11 rounded px-2 text-left text-sm hover:bg-muted">Versions and use</button><button type="button" class="min-h-11 rounded px-2 text-left text-sm hover:bg-muted">Draft history</button>`);
                  if (capabilities()["admission.benchmark.draft.edit"] || capabilities()["admission.benchmark.preview"]) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<button type="button" class="min-h-11 rounded px-2 text-left text-sm hover:bg-muted">Compare saved versions</button>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]-->`);
                  if (governance.comparison) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<button type="button" class="min-h-11 rounded px-2 text-left text-sm hover:bg-muted">Programme comparison</button>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--></div></details></div></div> `);
                  if (tabBadges().benchmarks) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<p class="mb-4 text-sm text-muted-foreground" role="status">${escape_html(benchmarkStatusLabel(tabBadges().benchmarks))}</p>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "calibrate",
                      children: ($$renderer6) => {
                        TabFmBenchmarkCalibrator($$renderer6, {
                          program: program(),
                          cycle: cycle(),
                          features: features(),
                          inputSchema: inputSchema(),
                          content,
                          version: draft?.current_version ?? 0,
                          canEdit: !!capabilities()["admission.benchmark.draft.edit"],
                          busy,
                          pendingSave: pendingDraftSave(),
                          dirty: dirty(),
                          onContentChange: (next) => {
                            content = next;
                          },
                          onSave: saveDraft,
                          onReset: () => {
                            content = tierContent(savedContent);
                          },
                          recovery,
                          isRecoveryProposal: !!recovery && recovery.program_in_source === true && !draft?.current_revision_id,
                          onRecover: recoverDraft,
                          get reason() {
                            return reason;
                          },
                          set reason($$value) {
                            reason = $$value;
                            $$settled = false;
                          },
                          get selectedBand() {
                            return selectedBand;
                          },
                          set selectedBand($$value) {
                            selectedBand = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----> `);
                        if (governance.can_manage_schema) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<details class="mt-5 border-t py-3"><summary class="min-h-11 cursor-pointer text-sm font-medium">Approve a subject source binding · schema guidance</summary> <p class="my-2 text-xs text-muted-foreground">Register only a source field with its exact feature key and confirmed unit. Approval does not add it to this draft; no label aliases or units are inferred.</p> <form class="grid gap-3 md:grid-cols-2"><div class="space-y-1"><label for="binding-source" class="text-sm">Institutional subject field</label>`);
                          $$renderer6.select(
                            {
                              id: "binding-source",
                              class: "h-11 w-full rounded-md border bg-background px-3 text-sm",
                              value: bindingSourceId,
                              required: true
                            },
                            ($$renderer7) => {
                              $$renderer7.option({ value: "" }, ($$renderer8) => {
                                $$renderer8.push(`Choose an approved source field`);
                              });
                              $$renderer7.push(`<!--[-->`);
                              const each_array_2 = ensure_array_like(governance.subject_sources ?? []);
                              for (let $$index_2 = 0, $$length = each_array_2.length; $$index_2 < $$length; $$index_2++) {
                                let item = each_array_2[$$index_2];
                                $$renderer7.option({ value: String(item.id) }, ($$renderer8) => {
                                  $$renderer8.push(`${escape_html(item.code)} · ${escape_html(item.label)}${escape_html(item.is_active === false ? " · unavailable" : "")}`);
                                });
                              }
                              $$renderer7.push(`<!--]-->`);
                            }
                          );
                          $$renderer6.push(`</div> <div class="space-y-1"><label for="binding-feature" class="text-sm">Exact model feature key</label>`);
                          Input($$renderer6, {
                            id: "binding-feature",
                            required: true,
                            maxlength: 100,
                            get value() {
                              return bindingFeatureKey;
                            },
                            set value($$value) {
                              bindingFeatureKey = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----></div> <div class="space-y-1"><label for="binding-unit" class="text-sm">Confirmed unit</label>`);
                          Input($$renderer6, {
                            id: "binding-unit",
                            required: true,
                            maxlength: 100,
                            get value() {
                              return bindingUnit;
                            },
                            set value($$value) {
                              bindingUnit = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----></div> <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox"${attr("checked", bindingModelRequired, true)}/>Required by this model schema</label> <div class="space-y-1 md:col-span-2"><label for="binding-reason" class="text-sm">Reason for approving this binding</label>`);
                          Textarea($$renderer6, {
                            id: "binding-reason",
                            rows: 2,
                            maxlength: 500,
                            required: true,
                            get value() {
                              return bindingReason;
                            },
                            set value($$value) {
                              bindingReason = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----></div> `);
                          Button($$renderer6, {
                            type: "submit",
                            disabled: busy || !!pendingCommand || dirty() || !bindingSourceId || !bindingFeatureKey.trim() || !bindingUnit.trim() || !bindingReason.trim(),
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Approve source binding`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----> `);
                          if (!(governance.subject_sources ?? []).length) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-sm text-muted-foreground">No authorized source fields are available for binding.</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></form></details>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (capabilities()["admission.benchmark.draft.edit"] && (governance.available_subjects ?? []).length) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<details class="border-t py-3"><summary class="min-h-11 cursor-pointer text-sm font-medium">Apply approved subject bindings to this programme schema</summary> <p class="my-2 text-xs text-muted-foreground">Select exact approved bindings. This creates a saved schema successor; incompatible historical drafts require explicit review or rebase.</p> <fieldset class="space-y-2"><div class="flex items-center justify-between"><legend class="text-sm font-medium">Approved definitions</legend> <div class="flex items-center gap-3 text-xs"><button type="button" class="text-primary hover:underline font-medium cursor-pointer"${attr("disabled", busy || !!pendingCommand || dirty(), true)}>${escape_html((governance.available_subjects ?? []).length > 0 && (governance.available_subjects ?? []).every((s) => availableBindingIds.includes(String(s.id))) ? "Deselect all" : `Select all (${(governance.available_subjects ?? []).length})`)}</button> `);
                          if (availableBindingIds.length > 0) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<button type="button" class="text-muted-foreground hover:text-destructive hover:underline cursor-pointer"${attr("disabled", busy || !!pendingCommand || dirty(), true)}>Clear</button>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></div></div> <!--[-->`);
                          const each_array_3 = ensure_array_like(governance.available_subjects);
                          for (let $$index_3 = 0, $$length = each_array_3.length; $$index_3 < $$length; $$index_3++) {
                            let item = each_array_3[$$index_3];
                            $$renderer6.push(`<label class="flex min-h-11 items-start gap-2 rounded-md border p-2 text-sm"><input class="mt-1" type="checkbox"${attr("checked", availableBindingIds.includes(String(item.id)), true)}${attr("disabled", busy || !!pendingCommand || dirty(), true)}/><span>${escape_html(item.label)} · ${escape_html(item.key)}<span class="block text-xs text-muted-foreground">Source ${escape_html(item.source_code)} v${escape_html(item.source_version)} · ${escape_html(item.unit ?? "unit unavailable")} · ${escape_html(item.declared_unit ?? "no confirmed unit")}</span></span></label>`);
                          }
                          $$renderer6.push(`<!--]--></fieldset> <div class="mt-3 max-w-xl space-y-2"><label for="binding-apply-reason" class="text-sm">Reason for schema change</label>`);
                          Textarea($$renderer6, {
                            id: "binding-apply-reason",
                            rows: 2,
                            maxlength: 500,
                            required: true,
                            get value() {
                              return bindingApplyReason;
                            },
                            set value($$value) {
                              bindingApplyReason = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!---->`);
                          Button($$renderer6, {
                            disabled: busy || !!pendingCommand || dirty() || !availableBindingIds.length || !bindingApplyReason.trim(),
                            onclick: applyApprovedSubjectBindings,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Apply reviewed bindings as a saved draft revision`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></div></details>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "notes",
                      class: "min-w-0",
                      children: ($$renderer6) => {
                        TabFmStickyNoteBoard($$renderer6, {
                          notes: noteBoard,
                          selectedNoteId,
                          selectedNote,
                          loading: noteLoading,
                          isShared: !!publishedNote,
                          canEdit: !!capabilities()["admission.advising-note.draft.edit"],
                          canPublish: !!capabilities()["admission.advising-note.publish"],
                          busy,
                          onSearch: () => refreshNotes(1, 1),
                          onSelect: selectNote,
                          onNew: startNewNote,
                          onShare: () => {
                            if (!selectedNoteId) return;
                            if (!noteReason.trim()) {
                              error = "Enter a reason before sharing or unsharing this note.";
                              return;
                            }
                            confirmation = {
                              operation: publishedNote ? "note-retract" : "note-publish",
                              content: publishedNote?.content ?? noteDraft?.content,
                              revisionId: publishedNote?.revision_id ?? noteDraft?.current_revision_id
                            };
                            confirmed = false;
                            confirmationOpen = true;
                          },
                          onHistory: () => refreshNotes(1, 1),
                          onCopy: () => focusGovernanceTarget("copy-source"),
                          onRevert: () => focusGovernanceTarget("note-history"),
                          onPage: (page) => refreshNotes(page, 1),
                          get search() {
                            return noteSearch;
                          },
                          set search($$value) {
                            noteSearch = $$value;
                            $$settled = false;
                          },
                          children: ($$renderer7) => {
                            if (selectedNote) {
                              $$renderer7.push("<!--[0-->");
                              if (publishedNote) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`<section class="space-y-1 border-b pb-3"><h3 class="text-sm font-medium">Currently shared · v${escape_html(publishedNote.version)}</h3><p class="whitespace-pre-wrap break-words text-sm">${escape_html(publishedNote.content)}</p><p class="text-xs text-muted-foreground">${escape_html(publishedNote.author_name)} · Shared by ${escape_html(publishedNote.publisher_name)}</p></section>`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                              }
                              $$renderer7.push(`<!--]--> <div class="space-y-2"><label for="note-text" class="text-sm font-medium">${escape_html(noteDraft ? `Draft v${noteDraft.version}` : "New note")}${escape_html(noteDirty() ? " · unsaved changes" : "")}</label>`);
                              Textarea($$renderer7, {
                                id: "note-text",
                                rows: 5,
                                maxlength: 2e4,
                                disabled: busy || !capabilities()["admission.advising-note.draft.edit"],
                                placeholder: "Write a short programme note",
                                get value() {
                                  return noteText;
                                },
                                set value($$value) {
                                  noteText = $$value;
                                  $$settled = false;
                                }
                              });
                              $$renderer7.push(`<!----><label for="note-reason" class="text-sm font-medium">Reason for this change</label>`);
                              Textarea($$renderer7, {
                                id: "note-reason",
                                rows: 2,
                                maxlength: 1e3,
                                disabled: busy || !(capabilities()["admission.advising-note.draft.edit"] || capabilities()["admission.advising-note.publish"]),
                                get value() {
                                  return noteReason;
                                },
                                set value($$value) {
                                  noteReason = $$value;
                                  $$settled = false;
                                }
                              });
                              $$renderer7.push(`<!----><div class="flex flex-wrap gap-2">`);
                              Button($$renderer7, {
                                disabled: busy || !!pendingCommand && !pendingNoteSaveRetry() || !pendingNoteSaveRetry() && (!capabilities()["admission.advising-note.draft.edit"] || !noteText.trim() || !noteReason.trim() || !noteDirty() && !!selectedNoteId && !!noteDraft),
                                onclick: () => noteAction("save"),
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(pendingNoteSaveRetry() ? "Retry save" : selectedNoteId ? "Save changes" : "Save new note")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push(`<!---->`);
                              Button($$renderer7, {
                                variant: "outline",
                                disabled: busy || !noteDirty() && !noteServerChanged(),
                                onclick: () => {
                                  const reconciled = reconcileGovernanceNoteEditor({ latestDraft: latestNoteDraft });
                                  noteDraft = reconciled.draft;
                                  noteText = reconciled.text;
                                },
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->${escape_html(noteServerChanged() ? "Discard edits and use latest version" : "Cancel edits")}`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push(`<!----></div></div> `);
                              if (capabilities()["admission.advising-note.draft.edit"]) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`<details class="border-t py-3"><summary class="cursor-pointer text-sm font-medium">Copy an exact version to another academic year</summary><div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto]"><div><label for="copy-source" class="text-xs">Source version</label>`);
                                $$renderer7.select(
                                  {
                                    id: "copy-source",
                                    class: "h-11 w-full rounded-md border bg-background px-3 text-sm",
                                    value: copySource
                                  },
                                  ($$renderer8) => {
                                    $$renderer8.option({ value: "" }, ($$renderer9) => {
                                      $$renderer9.push(`Choose version`);
                                    });
                                    $$renderer8.push(`<!--[-->`);
                                    const each_array_4 = ensure_array_like(noteHistory.items);
                                    for (let $$index_4 = 0, $$length = each_array_4.length; $$index_4 < $$length; $$index_4++) {
                                      let revision = each_array_4[$$index_4];
                                      $$renderer8.option({ value: String(revision.id) }, ($$renderer9) => {
                                        $$renderer9.push(`v${escape_html(revision.version)} · #${escape_html(revision.id)}`);
                                      });
                                    }
                                    $$renderer8.push(`<!--]-->`);
                                  }
                                );
                                $$renderer7.push(`</div><div><label for="copy-cycle" class="text-xs">Target year and semester</label>`);
                                $$renderer7.select(
                                  {
                                    id: "copy-cycle",
                                    class: "h-11 w-full rounded-md border bg-background px-3 text-sm",
                                    value: copyCycle
                                  },
                                  ($$renderer8) => {
                                    $$renderer8.option({ value: "" }, ($$renderer9) => {
                                      $$renderer9.push(`Choose target`);
                                    });
                                    $$renderer8.push(`<!--[-->`);
                                    const each_array_5 = ensure_array_like(governance.cycles ?? []);
                                    for (let $$index_5 = 0, $$length = each_array_5.length; $$index_5 < $$length; $$index_5++) {
                                      let item = each_array_5[$$index_5];
                                      if (item.id !== cycle().id) {
                                        $$renderer8.push("<!--[0-->");
                                        $$renderer8.option({ value: String(item.id) }, ($$renderer9) => {
                                          $$renderer9.push(`${escape_html(item.staff_label ?? item.name)}`);
                                        });
                                      } else {
                                        $$renderer8.push("<!--[-1-->");
                                      }
                                      $$renderer8.push(`<!--]-->`);
                                    }
                                    $$renderer8.push(`<!--]-->`);
                                  }
                                );
                                $$renderer7.push(`</div>`);
                                Button($$renderer7, {
                                  variant: "outline",
                                  class: "self-end",
                                  disabled: busy || !copyCycle,
                                  onclick: () => noteAction("copy"),
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Copy as draft`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push(`<!----></div></details>`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                              }
                              $$renderer7.push(`<!--]--> `);
                              if (noteInspected) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`<section class="border-t py-3"><h3 class="text-sm font-medium">Version #${escape_html(noteInspected.id)}</h3><p class="whitespace-pre-wrap break-words text-sm">${escape_html(noteInspected.content)}</p><p class="text-xs text-muted-foreground">${escape_html(noteInspected.author_name)} · ${escape_html(noteInspected.reason)}</p></section>`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                              }
                              $$renderer7.push(`<!--]--> <section id="note-history" tabindex="-1" class="space-y-3 border-t py-3"><h3 class="text-sm font-medium">Version history</h3>`);
                              const each_array_6 = ensure_array_like(noteHistory.items);
                              if (each_array_6.length !== 0) {
                                $$renderer7.push("<!--[-->");
                                for (let $$index_6 = 0, $$length = each_array_6.length; $$index_6 < $$length; $$index_6++) {
                                  let revision = each_array_6[$$index_6];
                                  $$renderer7.push(`<div class="flex flex-wrap items-center justify-between gap-2 border-b py-2"><button type="button" class="text-left text-sm underline underline-offset-4">${escape_html(`v${revision.version} · ${new Date(revision.created_at).toLocaleDateString()}`)}</button>`);
                                  if (capabilities()["admission.advising-note.draft.edit"]) {
                                    $$renderer7.push("<!--[0-->");
                                    Button($$renderer7, {
                                      variant: "ghost",
                                      size: "sm",
                                      disabled: busy || noteDirty() || !noteReason.trim(),
                                      onclick: () => noteAction("restore", revision),
                                      children: ($$renderer8) => {
                                        $$renderer8.push(`<!---->Restore version`);
                                      },
                                      $$slots: { default: true }
                                    });
                                  } else {
                                    $$renderer7.push("<!--[-1-->");
                                  }
                                  $$renderer7.push(`<!--]--></div>`);
                                }
                              } else {
                                $$renderer7.push("<!--[!-->");
                                $$renderer7.push(`<p class="py-2 text-sm text-muted-foreground">No saved versions.</p>`);
                              }
                              $$renderer7.push(`<!--]-->`);
                              if (noteHistory.pagination?.last_page > 1) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`<div class="flex items-center gap-3">`);
                                Button($$renderer7, {
                                  variant: "outline",
                                  size: "sm",
                                  disabled: busy || noteHistory.pagination.current_page <= 1,
                                  onclick: () => refreshNotes(noteHistory.pagination.current_page - 1, publicationHistory.pagination?.current_page ?? 1),
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Previous versions`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push(`<!----><span class="text-xs">Page ${escape_html(noteHistory.pagination.current_page)} of ${escape_html(noteHistory.pagination.last_page)}</span>`);
                                Button($$renderer7, {
                                  variant: "outline",
                                  size: "sm",
                                  disabled: busy || noteHistory.pagination.current_page >= noteHistory.pagination.last_page,
                                  onclick: () => refreshNotes(noteHistory.pagination.current_page + 1, publicationHistory.pagination?.current_page ?? 1),
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->More versions`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push(`<!----></div>`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                              }
                              $$renderer7.push(`<!--]--><!--[-->`);
                              const each_array_7 = ensure_array_like(publicationHistory.items);
                              for (let $$index_7 = 0, $$length = each_array_7.length; $$index_7 < $$length; $$index_7++) {
                                let receipt = each_array_7[$$index_7];
                                $$renderer7.push(`<p class="py-1 text-xs text-muted-foreground">${escape_html(receipt.operation === "publish" ? "Shared" : "Unshared")} by ${escape_html(receipt.actor_name)} · ${escape_html(receipt.reason)}</p>`);
                              }
                              $$renderer7.push(`<!--]-->`);
                              if (publicationHistory.pagination?.last_page > 1) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.push(`<div class="flex items-center gap-3">`);
                                Button($$renderer7, {
                                  variant: "outline",
                                  size: "sm",
                                  disabled: busy || publicationHistory.pagination.current_page <= 1,
                                  onclick: () => refreshNotes(noteHistory.pagination?.current_page ?? 1, publicationHistory.pagination.current_page - 1),
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->Previous sharing changes`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push(`<!----><span class="text-xs">Page ${escape_html(publicationHistory.pagination.current_page)} of ${escape_html(publicationHistory.pagination.last_page)}</span>`);
                                Button($$renderer7, {
                                  variant: "outline",
                                  size: "sm",
                                  disabled: busy || publicationHistory.pagination.current_page >= publicationHistory.pagination.last_page,
                                  onclick: () => refreshNotes(noteHistory.pagination?.current_page ?? 1, publicationHistory.pagination.current_page + 1),
                                  children: ($$renderer8) => {
                                    $$renderer8.push(`<!---->More sharing changes`);
                                  },
                                  $$slots: { default: true }
                                });
                                $$renderer7.push(`<!----></div>`);
                              } else {
                                $$renderer7.push("<!--[-1-->");
                              }
                              $$renderer7.push(`<!--]--></section>`);
                            } else {
                              $$renderer7.push("<!--[-1-->");
                            }
                            $$renderer7.push(`<!--]-->`);
                          },
                          $$slots: { default: true }
                        });
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "discussion",
                      class: "space-y-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div><h2 class="font-semibold">Programme discussion</h2><label for="discussion-topic" class="mt-3 block text-sm font-medium">Topic</label>`);
                        $$renderer6.select(
                          {
                            id: "discussion-topic",
                            class: "mt-1 h-11 w-full max-w-sm rounded-md border bg-background px-3 text-sm",
                            value: discussionTopic
                          },
                          ($$renderer7) => {
                            $$renderer7.push(`<!--[-->`);
                            const each_array_8 = ensure_array_like(["benchmark", "quota", "advising"]);
                            for (let $$index_8 = 0, $$length = each_array_8.length; $$index_8 < $$length; $$index_8++) {
                              let topic = each_array_8[$$index_8];
                              if (capabilities()[`admission.governance-discussion.${topic}.read`]) {
                                $$renderer7.push("<!--[0-->");
                                $$renderer7.option({ value: topic }, ($$renderer8) => {
                                  $$renderer8.push(`${escape_html(topic === "benchmark" ? "Benchmarks" : topic === "quota" ? "Quotas" : "Advising notes")}`);
                                });
                              } else {
                                $$renderer7.push("<!--[-1-->");
                              }
                              $$renderer7.push(`<!--]-->`);
                            }
                            $$renderer7.push(`<!--]-->`);
                          }
                        );
                        $$renderer6.push(`</div> `);
                        if (capabilities()[`admission.governance-discussion.${discussionTopic}.read`]) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<!---->`);
                          {
                            GovernanceDiscussionPanel($$renderer6, {
                              program: program(),
                              cycle: cycle(),
                              topic: discussionTopic,
                              initialList: governance.discussion?.[discussionTopic],
                              onGovernanceActivity: refreshGovernanceProjection
                            });
                          }
                          $$renderer6.push(`<!---->`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                          $$renderer6.push(`<p class="text-sm text-muted-foreground">Discussion access is not available for this topic.</p>`);
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "sets",
                      class: "space-y-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<h2 class="font-semibold">Review, publish, then use</h2> <p class="text-sm text-muted-foreground">Saving a draft never publishes it. Publishing and putting a set in use are separate reviewed actions.</p> <p class="text-sm">Current: ${escape_html(activeSet ? `Set #${activeSet.id} in use` : "No set in use")} · ${escape_html(draft?.current_revision_id ? `Draft revision #${draft.current_revision_id}, v${draft.current_version}` : "No saved draft")}</p> `);
                        if (capabilities()["admission.benchmark.publish"]) {
                          $$renderer6.push("<!--[0-->");
                          Button($$renderer6, {
                            disabled: busy || !!pendingCommand || dirty() || !draft?.current_revision_id || !activeSet?.content_sha256 && recovery?.status !== "draft",
                            onclick: () => reviewSet("publish"),
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(recovery ? "Review recovered set for publication" : "Review exact revision for publication")}`);
                            },
                            $$slots: { default: true }
                          });
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (publishedReceipt) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<section class="space-y-2 border-t py-3"><h3 class="text-sm font-medium">Published set #${escape_html(publishedReceipt.id)} · v${escape_html(publishedReceipt.version)}</h3><p class="text-sm text-muted-foreground">Not in use until separately activated.</p>`);
                          if (capabilities()["admission.benchmark.activate"]) {
                            $$renderer6.push("<!--[0-->");
                            Button($$renderer6, {
                              variant: "outline",
                              disabled: busy || !!pendingCommand || activeSet?.id === publishedReceipt.id,
                              onclick: () => reviewSet("activate"),
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->Review and put this set in use`);
                              },
                              $$slots: { default: true }
                            });
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]--></section>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (capabilities()["admission.benchmark.activate"]) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<section class="border-t py-3"><h3 class="text-sm font-medium">Published versions</h3>`);
                          const each_array_9 = ensure_array_like(governance.sets ?? []);
                          if (each_array_9.length !== 0) {
                            $$renderer6.push("<!--[-->");
                            for (let $$index_9 = 0, $$length = each_array_9.length; $$index_9 < $$length; $$index_9++) {
                              let set = each_array_9[$$index_9];
                              $$renderer6.push(`<div class="flex flex-wrap items-center justify-between gap-3 border-b py-3"><p class="text-sm">v${escape_html(set.version)} · ${escape_html(set.name)}${escape_html(activeSet?.id === set.id ? " · in use" : "")}</p>`);
                              Button($$renderer6, {
                                variant: "outline",
                                size: "sm",
                                disabled: busy || !!pendingCommand || activeSet?.id === set.id,
                                onclick: () => reviewSet("activate", set),
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Review version`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer6.push(`<!----></div>`);
                            }
                          } else {
                            $$renderer6.push("<!--[!-->");
                            $$renderer6.push(`<p class="py-2 text-sm text-muted-foreground">No published versions available.</p>`);
                          }
                          $$renderer6.push(`<!--]--></section>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (capabilities()["admission.benchmark.draft.edit"] && draft?.current_revision_id && activeSet && String(draft.base_benchmark_set_id) !== String(activeSet.id)) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<details class="border-t py-3"><summary class="cursor-pointer text-sm font-medium">Rebase this draft to the current set</summary><p class="my-2 text-sm text-muted-foreground">Rebase preserves this programme's saved draft cells for review against the current set.</p><label for="rebase-reason" class="text-sm">Reason</label>`);
                          Textarea($$renderer6, {
                            id: "rebase-reason",
                            rows: 2,
                            maxlength: 500,
                            get value() {
                              return reason;
                            },
                            set value($$value) {
                              reason = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!---->`);
                          Button($$renderer6, {
                            class: "mt-2",
                            variant: "outline",
                            disabled: busy || !!pendingCommand || dirty() || !reason.trim(),
                            onclick: rebaseDraft,
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Rebase saved draft`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></details>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> <details class="border-t py-3"><summary class="cursor-pointer text-sm font-medium">Technical details</summary><dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-2 text-xs"><dt>Active set digest</dt><dd class="break-all">${escape_html(activeSet?.content_sha256 ?? "Unavailable")}</dd><dt>Schema digest</dt><dd class="break-all">${escape_html(activeSet?.schema_sha256 ?? draft?.feature_schema_digest ?? "Unavailable")}</dd></dl></details> `);
                        if (governance.can_import) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<details class="border-t py-3"><summary class="cursor-pointer text-sm font-medium">Restricted benchmark import</summary><form class="mt-3 space-y-3"><label for="import-json" class="text-sm">Benchmark JSON payload</label>`);
                          Textarea($$renderer6, {
                            id: "import-json",
                            required: true,
                            rows: 8,
                            get value() {
                              return importText;
                            },
                            set value($$value) {
                              importText = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----><label for="import-reason" class="text-sm">Import reason</label>`);
                          Textarea($$renderer6, {
                            id: "import-reason",
                            required: true,
                            maxlength: 500,
                            rows: 2,
                            get value() {
                              return importReason;
                            },
                            set value($$value) {
                              importReason = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!---->`);
                          Button($$renderer6, {
                            variant: "outline",
                            type: "submit",
                            disabled: busy || !importText.trim() || !importReason.trim(),
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Import published set (not activate)`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></form></details>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "history",
                      class: "space-y-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="flex items-center justify-between gap-2"><h2 class="font-semibold">Benchmark revision history</h2>`);
                        Button($$renderer6, {
                          variant: "outline",
                          size: "sm",
                          onclick: () => loadBenchmarkHistory(benchmarkHistory.pagination?.current_page ?? 1),
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Refresh`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!----></div>`);
                        const each_array_10 = ensure_array_like(benchmarkHistory.items);
                        if (each_array_10.length !== 0) {
                          $$renderer6.push("<!--[-->");
                          for (let $$index_10 = 0, $$length = each_array_10.length; $$index_10 < $$length; $$index_10++) {
                            let revision = each_array_10[$$index_10];
                            $$renderer6.push(`<div class="space-y-2 border-b pb-3"><button type="button" class="text-sm font-medium underline underline-offset-4">v${escape_html(revision.version)} · revision #${escape_html(revision.id)}</button><p class="text-xs text-muted-foreground">${escape_html(revision.actor)} · ${escape_html(new Date(revision.created_at).toLocaleString())} · ${escape_html(revision.reason)}</p>`);
                            if (capabilities()["admission.benchmark.draft.edit"]) {
                              $$renderer6.push("<!--[0-->");
                              Button($$renderer6, {
                                variant: "outline",
                                size: "sm",
                                disabled: busy || dirty() || !reason.trim(),
                                onclick: () => restoreBenchmark(revision),
                                children: ($$renderer7) => {
                                  $$renderer7.push(`<!---->Restore as new draft revision`);
                                },
                                $$slots: { default: true }
                              });
                            } else {
                              $$renderer6.push("<!--[-1-->");
                            }
                            $$renderer6.push(`<!--]--></div>`);
                          }
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push(`<p class="text-sm text-muted-foreground">No draft revisions have been saved.</p>`);
                        }
                        $$renderer6.push(`<!--]-->`);
                        if (capabilities()["admission.benchmark.draft.edit"]) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<label for="restore-reason" class="text-sm font-medium">Restore reason</label>`);
                          Textarea($$renderer6, {
                            id: "restore-reason",
                            rows: 2,
                            maxlength: 1e3,
                            get value() {
                              return reason;
                            },
                            set value($$value) {
                              reason = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!---->`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                        if (benchmarkHistory.pagination) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<div class="flex items-center gap-2">`);
                          Button($$renderer6, {
                            variant: "outline",
                            size: "sm",
                            disabled: benchmarkHistory.pagination.current_page <= 1,
                            onclick: () => loadBenchmarkHistory(benchmarkHistory.pagination.current_page - 1),
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Previous`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----><span class="text-xs">Page ${escape_html(benchmarkHistory.pagination.current_page)} / ${escape_html(benchmarkHistory.pagination.last_page)}</span>`);
                          Button($$renderer6, {
                            variant: "outline",
                            size: "sm",
                            disabled: benchmarkHistory.pagination.current_page >= benchmarkHistory.pagination.last_page,
                            onclick: () => loadBenchmarkHistory(benchmarkHistory.pagination.current_page + 1),
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->Next`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push(`<!----></div>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                        {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "version-compare",
                      class: "space-y-4",
                      children: ($$renderer6) => {
                        $$renderer6.push(`<div class="grid gap-3 sm:grid-cols-2"><div class="space-y-1"><label for="comparison-baseline" class="text-sm font-medium">Authorized baseline</label>`);
                        $$renderer6.select(
                          {
                            id: "comparison-baseline",
                            class: "h-11 w-full rounded-md border bg-background px-3 text-sm",
                            value: comparisonBaselineId,
                            onchange: () => loadComparison(comparisonBaselineId, comparisonRevisionId),
                            disabled: comparisonBusy || !(comparisonData?.baselines ?? []).length
                          },
                          ($$renderer7) => {
                            $$renderer7.option({ value: "" }, ($$renderer8) => {
                              $$renderer8.push(`Choose authorized set`);
                            });
                            $$renderer7.push(`<!--[-->`);
                            const each_array_11 = ensure_array_like(comparisonData?.baselines ?? []);
                            for (let $$index_11 = 0, $$length = each_array_11.length; $$index_11 < $$length; $$index_11++) {
                              let baseline = each_array_11[$$index_11];
                              $$renderer7.option({ value: String(baseline.id) }, ($$renderer8) => {
                                $$renderer8.push(`${escape_html(baseline.name)} · v${escape_html(baseline.version)}${escape_html(baseline.in_use ? " · in use" : " · published, not in use")}`);
                              });
                            }
                            $$renderer7.push(`<!--]-->`);
                          }
                        );
                        $$renderer6.push(`</div> <div class="space-y-1"><label for="comparison-revision" class="text-sm font-medium">Exact saved revision</label>`);
                        $$renderer6.select(
                          {
                            id: "comparison-revision",
                            class: "h-11 w-full rounded-md border bg-background px-3 text-sm",
                            value: comparisonRevisionId,
                            onchange: () => loadComparison(comparisonBaselineId, comparisonRevisionId),
                            disabled: comparisonBusy || !benchmarkHistory.items.length
                          },
                          ($$renderer7) => {
                            $$renderer7.option({ value: "" }, ($$renderer8) => {
                              $$renderer8.push(`Choose saved revision`);
                            });
                            $$renderer7.push(`<!--[-->`);
                            const each_array_12 = ensure_array_like(benchmarkHistory.items);
                            for (let $$index_12 = 0, $$length = each_array_12.length; $$index_12 < $$length; $$index_12++) {
                              let revision = each_array_12[$$index_12];
                              $$renderer7.option({ value: String(revision.id) }, ($$renderer8) => {
                                $$renderer8.push(`r${escape_html(revision.id)} · v${escape_html(revision.version)} · ${escape_html(revision.reason)}`);
                              });
                            }
                            $$renderer7.push(`<!--]-->`);
                          }
                        );
                        $$renderer6.push(`</div></div> <p class="text-xs text-muted-foreground">The server filters both exact versions to your authorized programme context. Compare never changes draft, publication, activation, or case state.</p> `);
                        if (comparisonBusy) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p role="status" class="text-sm">Loading authorized comparison…</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--> `);
                        if (comparisonData) {
                          $$renderer6.push("<!--[0-->");
                          TabFmRevisionCompare($$renderer6, {
                            comparison: comparisonData,
                            workingCopy: dirty() ? content : null
                          });
                        } else if (!comparisonBusy) {
                          $$renderer6.push("<!--[1-->");
                          $$renderer6.push(`<p class="rounded-lg border p-4 text-sm text-muted-foreground">Choose a saved revision to load its authorized baseline comparison.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (Tabs_content) {
                    $$renderer5.push("<!--[-->");
                    Tabs_content($$renderer5, {
                      value: "comparison",
                      class: "space-y-3",
                      children: ($$renderer6) => {
                        if (governance.comparison) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<h2 class="font-semibold">${escape_html(governance.comparison.college.name)} · active cutoff comparison</h2> <p class="text-xs text-muted-foreground">Set #${escape_html(governance.comparison.set_id)} · v${escape_html(governance.comparison.version)}. Tier 3 cutoff exemplars only; no applicant outcomes.</p> <div class="overflow-x-auto rounded-lg border"><table class="w-full text-sm"><thead><tr><th scope="col" class="min-w-48 px-3 py-3 text-left">Program</th><!--[-->`);
                          const each_array_13 = ensure_array_like(governance.comparison.schema_features);
                          for (let $$index_13 = 0, $$length = each_array_13.length; $$index_13 < $$length; $$index_13++) {
                            let feature = each_array_13[$$index_13];
                            $$renderer6.push(`<th scope="col" class="min-w-32 px-3 py-3 text-left">${escape_html(feature.label)}<span class="block text-xs font-normal text-muted-foreground">${escape_html(feature.key)} · unit unverified</span></th>`);
                          }
                          $$renderer6.push(`<!--]--></tr></thead><tbody><!--[-->`);
                          const each_array_14 = ensure_array_like(governance.comparison.rows);
                          for (let $$index_15 = 0, $$length = each_array_14.length; $$index_15 < $$length; $$index_15++) {
                            let item = each_array_14[$$index_15];
                            $$renderer6.push(`<tr class="border-t"><th scope="row" class="px-3 py-3 text-left font-normal">`);
                            Link($$renderer6, {
                              href: item.studio_url,
                              class: "underline underline-offset-4",
                              children: ($$renderer7) => {
                                $$renderer7.push(`<!---->${escape_html(item.program.code)} · ${escape_html(item.program.name)}`);
                              },
                              $$slots: { default: true }
                            });
                            $$renderer6.push(`<!----><span class="block text-xs text-muted-foreground">${escape_html(item.tier_level)} · ${escape_html(item.tier_label)}</span></th><!--[-->`);
                            const each_array_15 = ensure_array_like(governance.comparison.schema_features);
                            for (let $$index_14 = 0, $$length2 = each_array_15.length; $$index_14 < $$length2; $$index_14++) {
                              let feature = each_array_15[$$index_14];
                              $$renderer6.push(`<td class="px-3 py-3 tabular-nums">${escape_html(item.values[feature.key] ?? "Missing")}</td>`);
                            }
                            $$renderer6.push(`<!--]--></tr>`);
                          }
                          $$renderer6.push(`<!--]--></tbody></table></div> `);
                          if (governance.comparison.truncated) {
                            $$renderer6.push("<!--[0-->");
                            $$renderer6.push(`<p class="text-xs text-muted-foreground">Showing the first 50 authorized programs. Use the program selector for another context.</p>`);
                          } else {
                            $$renderer6.push("<!--[-1-->");
                          }
                          $$renderer6.push(`<!--]-->`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]-->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
            $$renderer4.push(`</main></div>`);
          }
          $$renderer4.push(`<!--]--> `);
          if (Dialog) {
            $$renderer4.push("<!--[-->");
            Dialog($$renderer4, {
              get open() {
                return previewOpen;
              },
              set open($$value) {
                previewOpen = $$value;
                $$settled = false;
              },
              children: ($$renderer5) => {
                if (Dialog_content) {
                  $$renderer5.push("<!--[-->");
                  Dialog_content($$renderer5, {
                    class: "max-h-[90dvh] overflow-y-auto sm:max-w-4xl",
                    children: ($$renderer6) => {
                      if (Dialog_header) {
                        $$renderer6.push("<!--[-->");
                        Dialog_header($$renderer6, {
                          children: ($$renderer7) => {
                            if (Dialog_title) {
                              $$renderer7.push("<!--[-->");
                              Dialog_title($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Preview TabFM`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                            if (Dialog_description) {
                              $$renderer7.push("<!--[-->");
                              Dialog_description($$renderer7, {
                                children: ($$renderer8) => {
                                  $$renderer8.push(`<!---->Sample inputs only. No applicant record, decision, or evidence changes. Choose the saved source and enter only confirmed values.`);
                                },
                                $$slots: { default: true }
                              });
                              $$renderer7.push("<!--]-->");
                            } else {
                              $$renderer7.push("<!--[!-->");
                              $$renderer7.push("<!--]-->");
                            }
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push("<!--]-->");
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push("<!--]-->");
                      }
                      $$renderer6.push(` <form class="space-y-4"><div class="grid gap-3 sm:grid-cols-3"><div class="space-y-1"><label for="preview-source" class="text-sm font-medium">Benchmark source</label>`);
                      $$renderer6.select(
                        {
                          id: "preview-source",
                          class: "h-11 w-full rounded-md border bg-background px-3 text-sm",
                          value: source
                        },
                        ($$renderer7) => {
                          $$renderer7.option({ value: "active", disabled: !activeSet }, ($$renderer8) => {
                            $$renderer8.push(`Currently in use`);
                          });
                          $$renderer7.option({ value: "draft", disabled: !draft?.current_revision_id }, ($$renderer8) => {
                            $$renderer8.push(`Saved draft v${escape_html(draft?.current_version ?? 0)}`);
                          });
                        }
                      );
                      $$renderer6.push(`</div><div class="space-y-1"><label for="preview-gwa" class="text-sm font-medium">GWA</label>`);
                      Input($$renderer6, {
                        id: "preview-gwa",
                        type: "number",
                        min: 1,
                        max: 100,
                        step: "any",
                        required: true,
                        get value() {
                          return gwa;
                        },
                        set value($$value) {
                          gwa = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div><div class="space-y-1"><label for="preview-strand" class="text-sm font-medium">SHS strand</label>`);
                      Input($$renderer6, {
                        id: "preview-strand",
                        required: true,
                        maxlength: 32,
                        get value() {
                          return strand;
                        },
                        set value($$value) {
                          strand = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer6.push(`<!----></div></div> <fieldset class="grid gap-3 sm:grid-cols-2"><legend class="mb-2 text-sm font-medium">Aptitude results</legend><!--[-->`);
                      const each_array_16 = ensure_array_like(aptitudeAreas());
                      for (let $$index_16 = 0, $$length = each_array_16.length; $$index_16 < $$length; $$index_16++) {
                        let area = each_array_16[$$index_16];
                        $$renderer6.push(`<div class="space-y-1"><label${attr("for", `preview-${area.code}`)} class="text-sm">${escape_html(area.name)}</label>`);
                        Input($$renderer6, {
                          id: `preview-${area.code}`,
                          type: "number",
                          min: 0,
                          max: 100,
                          step: "any",
                          required: true,
                          get value() {
                            return aptitudeInputs[area.code];
                          },
                          set value($$value) {
                            aptitudeInputs[area.code] = $$value;
                            $$settled = false;
                          }
                        });
                        $$renderer6.push(`<!----><p class="text-xs text-muted-foreground">${escape_html(area.unit_unverified !== false ? "Unit not confirmed" : area.declared_unit ?? "No declared unit")}</p></div>`);
                      }
                      $$renderer6.push(`<!--]--></fieldset> `);
                      if (!aptitudeAreas().length) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p class="text-sm text-muted-foreground">The aptitude catalogue is unavailable; preview cannot run.</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> <details class="border-y py-3"><summary class="cursor-pointer text-sm font-medium">Optional subject grades and RIASEC activities</summary><div class="mt-3 space-y-4"><section class="grid gap-3 sm:grid-cols-2" aria-label="Approved subject grade inputs">`);
                      const each_array_17 = ensure_array_like(previewSubjects());
                      if (each_array_17.length !== 0) {
                        $$renderer6.push("<!--[-->");
                        for (let $$index_17 = 0, $$length = each_array_17.length; $$index_17 < $$length; $$index_17++) {
                          let subject = each_array_17[$$index_17];
                          $$renderer6.push(`<div class="space-y-1"><label${attr("for", `preview-subject-${subject.key}`)} class="text-sm">${escape_html(subject.label)} · optional in sample${escape_html(subject.is_required ? " · required in benchmark examples" : "")}</label>`);
                          Input($$renderer6, {
                            id: `preview-subject-${subject.key}`,
                            type: "number",
                            min: subject.min_value ?? void 0,
                            max: subject.max_value ?? void 0,
                            step: "any",
                            get value() {
                              return subjectInputs[subject.key];
                            },
                            set value($$value) {
                              subjectInputs[subject.key] = $$value;
                              $$settled = false;
                            }
                          });
                          $$renderer6.push(`<!----><p class="text-xs text-muted-foreground">${escape_html(subject.unit ?? "Unit unavailable")} · source ${escape_html(subject.source_code)} v${escape_html(subject.source_version)}</p></div>`);
                        }
                      } else {
                        $$renderer6.push("<!--[!-->");
                        $$renderer6.push(`<p class="text-sm text-muted-foreground">No approved subject fields are bound to this saved source.</p>`);
                      }
                      $$renderer6.push(`<!--]--></section><fieldset class="space-y-3"><legend class="text-sm font-medium">RIASEC activity evidence · sample only</legend><!--[-->`);
                      const each_array_18 = ensure_array_like(previewActivityGroups());
                      for (let $$index_19 = 0, $$length = each_array_18.length; $$index_19 < $$length; $$index_19++) {
                        let group = each_array_18[$$index_19];
                        $$renderer6.push(`<div><h3 class="text-sm font-medium">${escape_html(group.parent.replace("riasec_", "").toUpperCase())}</h3><div class="flex flex-wrap gap-2"><!--[-->`);
                        const each_array_19 = ensure_array_like(group.items);
                        for (let $$index_18 = 0, $$length2 = each_array_19.length; $$index_18 < $$length2; $$index_18++) {
                          let activity = each_array_19[$$index_18];
                          $$renderer6.push(`<label class="flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm"><input type="checkbox"${attr("checked", subjectActivities.includes(activity.id), true)}/>${escape_html(activity.label)}</label>`);
                        }
                        $$renderer6.push(`<!--]-->`);
                        if (!group.items.length) {
                          $$renderer6.push("<!--[0-->");
                          $$renderer6.push(`<p class="text-xs text-muted-foreground">No approved activities are defined for this group.</p>`);
                        } else {
                          $$renderer6.push("<!--[-1-->");
                        }
                        $$renderer6.push(`<!--]--></div></div>`);
                      }
                      $$renderer6.push(`<!--]--><label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox"${attr("checked", activitiesReviewed, true)}/>I reviewed this activity assessment. No selections means reviewed empty; leave unchecked if not assessed.</label></fieldset></div></details> <div class="flex flex-wrap gap-2">`);
                      Button($$renderer6, {
                        type: "submit",
                        disabled: !previewComplete() || preview.status === "pending",
                        children: ($$renderer7) => {
                          $$renderer7.push(`<!---->${escape_html(preview.status === "pending" ? "Evaluating…" : "Run preview")}`);
                        },
                        $$slots: { default: true }
                      });
                      $$renderer6.push(`<!---->`);
                      if (preview.status === "pending") {
                        $$renderer6.push("<!--[0-->");
                        Button($$renderer6, {
                          type: "button",
                          variant: "outline",
                          onclick: () => {
                            preview = invalidatePreview(preview);
                          },
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--></div> <p class="text-xs text-muted-foreground">${escape_html(source === "active" ? `Sample evaluation uses current activation #${activeSet?.activation_id ?? "Unavailable"} · set #${activeSet?.id ?? "Unavailable"} · schema ${activeSet?.schema_sha256 ?? "Unavailable"}.` : `Sample evaluation uses saved draft revision #${draft?.current_revision_id ?? "Unavailable"} · v${draft?.current_version ?? "Unavailable"} · schema ${draft?.feature_schema_digest ?? "Unavailable"}.`)}</p></form> `);
                      if (previewHasIncompleteRanges()) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p role="alert" class="mt-3 text-sm text-destructive">This saved draft has a partial range. Complete both ends or clear it before preview.</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> <p role="status" class="mt-3 text-sm text-muted-foreground">${escape_html(preview.status === "stale" ? "Inputs or source changed; the previous result is stale." : preview.status === "pending" ? "Waiting for the server. Previous output is not current." : preview.status === "unavailable" ? "A result is not available for this configuration." : preview.status === "ready" ? `Result from ${source === "draft" ? `saved draft v${draft?.current_version}` : "the set in use"}.` : "Enter the required values to run a preview.")}</p> `);
                      if (preview.error) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<p role="alert" class="mt-2 text-sm text-destructive">${escape_html(preview.error)}</p>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]--> `);
                      if (["ready", "unavailable"].includes(preview.status) && preview.result) {
                        $$renderer6.push("<!--[0-->");
                        $$renderer6.push(`<div class="mt-4 border-t pt-4">`);
                        TabFmResultView($$renderer6, { model: preview.result, sourceKind: source });
                        $$renderer6.push(`<!----></div>`);
                      } else {
                        $$renderer6.push("<!--[-1-->");
                      }
                      $$renderer6.push(`<!--]-->`);
                    },
                    $$slots: { default: true }
                  });
                  $$renderer5.push("<!--]-->");
                } else {
                  $$renderer5.push("<!--[!-->");
                  $$renderer5.push("<!--]-->");
                }
              },
              $$slots: { default: true }
            });
            $$renderer4.push("<!--]-->");
          } else {
            $$renderer4.push("<!--[!-->");
            $$renderer4.push("<!--]-->");
          }
          $$renderer4.push(`</div></div>`);
        }
      });
      $$renderer3.push(`<!----> `);
      if (Dialog) {
        $$renderer3.push("<!--[-->");
        Dialog($$renderer3, {
          get open() {
            return confirmationOpen;
          },
          set open($$value) {
            confirmationOpen = $$value;
            $$settled = false;
          },
          children: ($$renderer4) => {
            if (Dialog_content) {
              $$renderer4.push("<!--[-->");
              Dialog_content($$renderer4, {
                class: "max-h-[85dvh] overflow-y-auto sm:max-w-2xl",
                children: ($$renderer5) => {
                  if (Dialog_header) {
                    $$renderer5.push("<!--[-->");
                    Dialog_header($$renderer5, {
                      children: ($$renderer6) => {
                        if (Dialog_title) {
                          $$renderer6.push("<!--[-->");
                          Dialog_title($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(confirmation?.operation === "publish" ? "Publish exact saved benchmark revision" : confirmation?.operation === "activate" ? "Activate exact published benchmark set" : confirmation?.operation === "note-publish" ? "Publish exact note revision" : "Retract current published note")}`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                        if (Dialog_description) {
                          $$renderer6.push("<!--[-->");
                          Dialog_description($$renderer6, {
                            children: ($$renderer7) => {
                              $$renderer7.push(`<!---->${escape_html(program()?.code)} · ${escape_html(cycle()?.staff_label ?? cycle()?.name)}. Review the exact content; this action is separate from saving a draft.`);
                            },
                            $$slots: { default: true }
                          });
                          $$renderer6.push("<!--]-->");
                        } else {
                          $$renderer6.push("<!--[!-->");
                          $$renderer6.push("<!--]-->");
                        }
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                  $$renderer5.push(` `);
                  if (confirmation?.operation === "publish" || confirmation?.operation === "activate") {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-2 text-sm"><dt>Revision / set</dt><dd>${escape_html(confirmation.operation === "publish" ? `Revision #${confirmation.revisionId} · v${confirmation.version}` : `Set #${confirmation.set.id} · v${confirmation.set.version}`)}</dd><dt>Expected activation</dt><dd>${escape_html(confirmation.activationId ?? "None")}</dd></dl> <details class="rounded-lg border p-3"><summary class="cursor-pointer text-xs font-medium">Technical details</summary><dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-2 text-xs"><dt>Content digest</dt><dd class="break-all">${escape_html(confirmation.operation === "publish" ? confirmation.baseDigest : confirmation.set.content_sha256)}</dd>`);
                    if (confirmation.operation === "publish") {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<dt>Feature schema digest</dt><dd class="break-all">${escape_html(draft?.feature_schema_digest ?? "Unavailable")}</dd>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--></dl></details> <div class="overflow-x-auto"><table class="w-full text-sm"><caption class="py-2 text-left font-medium">Selected-program differences from active content</caption><thead><tr><th class="py-2 text-left" scope="col">Tier / feature</th><th class="text-right" scope="col">Active</th><th class="text-right" scope="col">Reviewed</th></tr></thead><tbody>`);
                    const each_array_20 = ensure_array_like(diffs());
                    if (each_array_20.length !== 0) {
                      $$renderer5.push("<!--[-->");
                      for (let $$index_20 = 0, $$length = each_array_20.length; $$index_20 < $$length; $$index_20++) {
                        let diff = each_array_20[$$index_20];
                        $$renderer5.push(`<tr class="border-t"><td class="py-2">${escape_html(diff.level)} · ${escape_html(diff.feature.label)}</td><td class="text-right">${escape_html(diff.before ?? "Missing")}</td><td class="text-right">${escape_html(diff.after ?? "Missing")}</td></tr>`);
                      }
                    } else {
                      $$renderer5.push("<!--[!-->");
                      $$renderer5.push(`<tr><td colspan="3" class="py-3 text-muted-foreground">No selected-program differences.</td></tr>`);
                    }
                    $$renderer5.push(`<!--]--></tbody></table></div> `);
                    if (confirmation.operation === "publish") {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<section class="space-y-3 rounded-lg border p-3" aria-label="Exact model-value review"><h3 class="text-sm font-semibold">Publication review · exact model values</h3> <div class="overflow-x-auto"><table class="w-full text-xs"><caption class="sr-only">Exact feature values in each model example for this program</caption><thead><tr><th class="py-2 text-left">Score or field</th><!--[-->`);
                      const each_array_21 = ensure_array_like([
                        { key: "1", label: "Honors" },
                        { key: "2", label: "Regular" },
                        { key: "3", label: "Cutoff" },
                        { key: "4", label: "Other-strand" },
                        { key: "5", label: "Below cutoff" }
                      ]);
                      for (let $$index_21 = 0, $$length = each_array_21.length; $$index_21 < $$length; $$index_21++) {
                        let tier = each_array_21[$$index_21];
                        $$renderer5.push(`<th class="px-2 py-2 text-right">${escape_html(tier.label)}</th>`);
                      }
                      $$renderer5.push(`<!--]--></tr></thead><tbody><!--[-->`);
                      const each_array_22 = ensure_array_like(features());
                      for (let $$index_23 = 0, $$length = each_array_22.length; $$index_23 < $$length; $$index_23++) {
                        let feature = each_array_22[$$index_23];
                        $$renderer5.push(`<tr class="border-t"><th scope="row" class="py-2 text-left font-normal">${escape_html(feature.label)}${escape_html(feature.unit_unverified ? " · unit not confirmed" : "")}</th><!--[-->`);
                        const each_array_23 = ensure_array_like(["1", "2", "3", "4", "5"]);
                        for (let $$index_22 = 0, $$length2 = each_array_23.length; $$index_22 < $$length2; $$index_22++) {
                          let level = each_array_23[$$index_22];
                          $$renderer5.push(`<td class="px-2 py-2 text-right tabular-nums">${escape_html(confirmation.content.tiers?.[level]?.values?.[feature.key] ?? "Missing")}</td>`);
                        }
                        $$renderer5.push(`<!--]--></tr>`);
                      }
                      $$renderer5.push(`<!--]--></tbody></table></div> <details open="" class="border-t pt-2"><summary class="cursor-pointer text-xs font-medium">Authored ranges and review choices</summary> `);
                      if (confirmation.content.authoring) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<!--[-->`);
                        const each_array_24 = ensure_array_like([
                          { key: "honors", label: "Honors" },
                          { key: "regular", label: "Regular" },
                          { key: "cutoff", label: "Cutoff" }
                        ]);
                        for (let $$index_25 = 0, $$length = each_array_24.length; $$index_25 < $$length; $$index_25++) {
                          let band = each_array_24[$$index_25];
                          $$renderer5.push(`<h4 class="mt-3 text-xs font-semibold">${escape_html(band.label)} ranges</h4><ul class="text-xs">`);
                          const each_array_25 = ensure_array_like(Object.entries(confirmation.content.authoring.ranges?.[band.key] ?? {}));
                          if (each_array_25.length !== 0) {
                            $$renderer5.push("<!--[-->");
                            for (let $$index_24 = 0, $$length2 = each_array_25.length; $$index_24 < $$length2; $$index_24++) {
                              let [key, pair] = each_array_25[$$index_24];
                              $$renderer5.push(`<li>${escape_html(features().find((feature) => feature.key === key)?.label ?? "Unrecognized feature")}: From ${escape_html(pair[0] ?? "Missing")} · To ${escape_html(pair[1] ?? "Missing")}</li>`);
                            }
                          } else {
                            $$renderer5.push("<!--[!-->");
                            $$renderer5.push(`<li>No ranges recorded.</li>`);
                          }
                          $$renderer5.push(`<!--]--></ul>`);
                        }
                        $$renderer5.push(`<!--]--> <p class="mt-3 text-xs">Preferred strands: ${escape_html(confirmation.content.authoring.preferred_strands === null ? "Not recorded" : confirmation.content.authoring.preferred_strands.length ? confirmation.content.authoring.preferred_strands.join(", ") : "No preference selected")}</p> <p class="text-xs">Career interests: ${escape_html(confirmation.content.authoring.interests === null ? "Not recorded" : confirmation.content.authoring.interests.length ? confirmation.content.authoring.interests.map((key) => features().find((feature) => feature.key === key)?.label ?? "Unrecognized interest").join(", ") : "No preference selected")}</p>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                        $$renderer5.push(`<p class="mt-2 text-xs text-muted-foreground">No ranges or preference choices were authored. Point values remain exact examples.</p>`);
                      }
                      $$renderer5.push(`<!--]--></details></section>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> `);
                    if (confirmation.recovery) {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<section class="space-y-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm" aria-label="Recovered source publication manifest"><h3 class="font-semibold">Exact full recovered revision manifest</h3> <p class="text-xs text-muted-foreground">Source set #${escape_html(confirmation.recoverySourceSetId)} · activation #${escape_html(confirmation.sourceActivationId ?? "None")} · frozen source digest <span class="break-all">${escape_html(confirmation.sourceSnapshotDigest)}</span>. These current revision IDs are supplied by governance and are sent unchanged; no sibling cells are inferred.</p> `);
                      if (confirmation.manifest.length) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<div class="max-h-48 overflow-auto"><table class="w-full text-left text-xs"><caption class="sr-only">Exact revision IDs for every recovered source program</caption><thead><tr><th class="py-1 pr-3">Program ID</th><th>Exact revision ID</th></tr></thead><tbody><!--[-->`);
                        const each_array_26 = ensure_array_like(confirmation.manifest);
                        for (let $$index_26 = 0, $$length = each_array_26.length; $$index_26 < $$length; $$index_26++) {
                          let [programKey, revisionKey] = each_array_26[$$index_26];
                          $$renderer5.push(`<tr class="border-t"><td class="py-1 pr-3">${escape_html(programKey)}</td><td>${escape_html(revisionKey)}</td></tr>`);
                        }
                        $$renderer5.push(`<!--]--></tbody></table></div>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--> `);
                      if (confirmation.incomplete.length) {
                        $$renderer5.push("<!--[0-->");
                        $$renderer5.push(`<h4 class="font-medium">Programs still incomplete</h4> <ul class="list-inside list-disc text-xs"><!--[-->`);
                        const each_array_27 = ensure_array_like(confirmation.incomplete);
                        for (let $$index_27 = 0, $$length = each_array_27.length; $$index_27 < $$length; $$index_27++) {
                          let item = each_array_27[$$index_27];
                          $$renderer5.push(`<li>Program #${escape_html(item.program_id)}${escape_html(item.program_code ? ` · ${item.program_code}` : "")}: ${escape_html(item.missing_cells?.length ? item.missing_cells.map((cell) => `Tier ${cell.tier} ${cell.feature_key}`).join(", ") : "no missing feature cells")}${escape_html(item.negative_anchor_missing ? " · Negative anchor missing" : "")}</li>`);
                        }
                        $$renderer5.push(`<!--]--></ul>`);
                      } else if (confirmation.publishBlocked) {
                        $$renderer5.push("<!--[1-->");
                        $$renderer5.push(`<p role="alert" class="text-xs text-destructive">The complete exact manifest is unavailable for this authorized scope or does not include the selected current revision. Publication is blocked; no partial manifest will be sent.</p>`);
                      } else {
                        $$renderer5.push("<!--[-1-->");
                      }
                      $$renderer5.push(`<!--]--></section>`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]--> <p class="text-xs text-muted-foreground">Publication preserves other programs. Activation applies the complete published set, not unsaved cells.</p>`);
                    if (confirmation.operation === "publish") {
                      $$renderer5.push("<!--[0-->");
                      $$renderer5.push(`<label for="publish-reason" class="text-sm font-medium">Publication reason</label>`);
                      Textarea($$renderer5, {
                        id: "publish-reason",
                        rows: 2,
                        maxlength: 500,
                        get value() {
                          return reason;
                        },
                        set value($$value) {
                          reason = $$value;
                          $$settled = false;
                        }
                      });
                      $$renderer5.push(`<!---->`);
                    } else {
                      $$renderer5.push("<!--[-1-->");
                    }
                    $$renderer5.push(`<!--]-->`);
                  } else if (confirmation) {
                    $$renderer5.push("<!--[1-->");
                    $$renderer5.push(`<p class="text-sm font-medium">Revision #${escape_html(confirmation.revisionId)}</p><p class="whitespace-pre-wrap break-words text-sm">${escape_html(confirmation.content)}</p>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> <label class="flex items-start gap-2 text-sm"><input type="checkbox"${attr("checked", confirmed, true)} class="mt-1"/>I reviewed this exact revision, full recovery manifest, or published set for ${escape_html(cycle()?.staff_label ?? cycle()?.name)}.</label> `);
                  if (error) {
                    $$renderer5.push("<!--[0-->");
                    $$renderer5.push(`<p role="alert" class="text-sm text-destructive">${escape_html(error)}</p>`);
                  } else {
                    $$renderer5.push("<!--[-1-->");
                  }
                  $$renderer5.push(`<!--]--> `);
                  if (Dialog_footer) {
                    $$renderer5.push("<!--[-->");
                    Dialog_footer($$renderer5, {
                      children: ($$renderer6) => {
                        Button($$renderer6, {
                          variant: "outline",
                          disabled: busy,
                          onclick: () => {
                            confirmationOpen = false;
                          },
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->Cancel`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!---->`);
                        Button($$renderer6, {
                          disabled: busy || !confirmed || confirmation?.operation === "publish" && confirmation.publishBlocked,
                          onclick: async () => {
                            if (confirmation.operation.startsWith("note-")) {
                              await noteAction(confirmation.operation === "note-publish" ? "publish" : "retract");
                              if (!error) confirmationOpen = false;
                            } else await confirmSet();
                          },
                          children: ($$renderer7) => {
                            $$renderer7.push(`<!---->${escape_html(busy ? "Submitting…" : confirmation?.operation === "publish" ? confirmation.recovery ? "Publish complete recovered set" : "Publish reviewed revision" : confirmation?.operation === "activate" ? "Activate reviewed set" : confirmation?.operation === "note-publish" ? "Publish reviewed note" : "Retract reviewed note")}`);
                          },
                          $$slots: { default: true }
                        });
                        $$renderer6.push(`<!---->`);
                      },
                      $$slots: { default: true }
                    });
                    $$renderer5.push("<!--]-->");
                  } else {
                    $$renderer5.push("<!--[!-->");
                    $$renderer5.push("<!--]-->");
                  }
                },
                $$slots: { default: true }
              });
              $$renderer4.push("<!--]-->");
            } else {
              $$renderer4.push("<!--[!-->");
              $$renderer4.push("<!--]-->");
            }
          },
          $$slots: { default: true }
        });
        $$renderer3.push("<!--]-->");
      } else {
        $$renderer3.push("<!--[!-->");
        $$renderer3.push("<!--]-->");
      }
    }
    do {
      $$settled = true;
      $$inner_renderer = $$renderer2.copy();
      $$render_inner($$inner_renderer);
    } while (!$$settled);
    $$renderer2.subsume($$inner_renderer);
  });
}
export {
  TabFmStudio as default
};
