import { describe, expect, it } from 'vitest';
import {
  buildBubbleLayerRecipe,
  cropAspectRatio,
  cropBox,
  isOverlayGeometry,
  isRegion,
  normalizeOverlayInput,
  overlayAccessibleText,
  overlayLayerClass,
  overlayStateIcon,
  toRegion,
  transformOptionRegion,
  transformRegionToViewport,
} from '../../resources/js/lib/omrRegionCrop.js';

const validHash = 'a'.repeat(64);
const geometryManifestHash = 'b'.repeat(64);

const standardGeometry = {
  item_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
  crop_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
  options: [
    { code: 'A', region: { x: 0.2, y: 0.22, w: 0.04, h: 0.04 } },
    { code: 'B', region: { x: 0.4, y: 0.22, w: 0.04, h: 0.04 } },
    { code: 'C', region: { x: 0.6, y: 0.22, w: 0.04, h: 0.04 } },
    { code: 'D', region: { x: 0.75, y: 0.22, w: 0.04, h: 0.04 } },
  ],
  aligned_asset: {
    url: '/aligned.png',
    width: 1000,
    height: 1400,
    sha256: validHash,
    page_no: 1,
    kind: 'aligned_page',
  },
  crop_asset: null,
  crop_source: null,
  geometry_manifest_hash: geometryManifestHash,
  evidence_version: 'v1',
  page_no: 1,
};

function createRecipe(
  machine: Record<string, unknown>,
  expected: string | null = null,
  geometryOverrides: Record<string, unknown> = {},
  review: Record<string, unknown> | null = null,
) {
  return buildBubbleLayerRecipe({
    machine: {
      confidence: 0.95,
      option_observations: [],
      issue_codes: [],
      evidence_hash: validHash,
      ...machine,
    },
    expected: { option: expected },
    geometry: { ...standardGeometry, ...geometryOverrides },
    review,
  });
}

describe('OMR bubble evidence geometry and viewport calculations', () => {
  it('derives a padded normalized crop box with page clamping', () => {
    const box = cropBox({ x: 0.1, y: 0.2, w: 0.7, h: 0.08 });
    expect(box).toEqual(expect.objectContaining({ x: 0 }));
    expect(box!.y).toBeCloseTo(0.18, 10);
    expect(box!.w).toBeCloseTo(0.889, 10);
    expect(box!.h).toBeCloseTo(0.116, 10);
    expect(cropBox(null)).toBeNull();
    expect(cropBox({ x: 0, y: 0, w: 0, h: 0 })).toBeNull();
    expect(isRegion({ x: 0, y: 0, w: 0.1, h: 0.1 })).toBe(true);
    expect(toRegion('nope')).toBeNull();
    expect(isOverlayGeometry({ item_rect: { x: 0, y: 0, w: 1, h: 1 } })).toBe(true);
  });

  it('transforms option geometry into the crop viewport without pixel tuning', () => {
    const viewport = cropBox(standardGeometry.item_rect)!;
    const transformed = transformOptionRegion(standardGeometry.options[1].region, viewport, null);
    expect(transformed!.x).toBeCloseTo((0.4 - viewport.x) / viewport.w, 8);
    expect(transformed!.y).toBeCloseTo((0.22 - viewport.y) / viewport.h, 8);
    expect(transformed!.w).toBeCloseTo(0.04 / viewport.w, 8);
    expect(transformed!.h).toBeCloseTo(0.04 / viewport.h, 8);
  });

  it('computes correct crop aspect ratios for full page crops', () => {
    const viewport = cropBox(standardGeometry.item_rect)!;
    const ratio = cropAspectRatio(viewport, standardGeometry.aligned_asset);
    expect(ratio).toBeCloseTo((viewport.w * 1000) / (viewport.h * 1400), 6);
    expect(cropAspectRatio(null, standardGeometry.aligned_asset)).toBeNull();
    expect(cropAspectRatio(viewport, null)).toBeNull();
  });
});

describe('OMR overlay recipe - OpenSpec 6.2 layer rendering', () => {
  it('renders concentric green fill and larger expected ring for a definite match', () => {
    const result = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
    );
    expect(result.status).toBe('ready');
    expect(result.machine_state).toBe('marked');
    expect(result.expected_option).toBe('A');

    // Exactly two layers on option A
    expect(result.layers).toHaveLength(2);
    const fillLayer = result.layers.find((l) => l.kind === 'machine-fill');
    const ringLayer = result.layers.find((l) => l.kind === 'expected-ring');

    expect(fillLayer).toBeDefined();
    expect(fillLayer!.code).toBe('A');
    expect(fillLayer!.tone).toBe('match');
    expect(fillLayer!.fill).toBe('currentColor');
    expect(fillLayer!.stroke).toBe('none');
    expect(fillLayer!.icon).toBe('✓');

    expect(ringLayer).toBeDefined();
    expect(ringLayer!.code).toBe('A');
    expect(ringLayer!.tone).toBe('expected');
    expect(ringLayer!.fill).toBe('none');
    expect(ringLayer!.stroke).toBe('currentColor');
    expect(ringLayer!.icon).toBe('○');

    // Concentric: centers are identical
    expect(ringLayer!.ellipse.cx).toBeCloseTo(fillLayer!.ellipse.cx, 8);
    expect(ringLayer!.ellipse.cy).toBeCloseTo(fillLayer!.ellipse.cy, 8);

    // Expected ring is geometrically larger (0.58 vs 0.40)
    expect(ringLayer!.ellipse.rx).toBeGreaterThan(fillLayer!.ellipse.rx);
    expect(ringLayer!.ellipse.ry).toBeGreaterThan(fillLayer!.ellipse.ry);
    expect(ringLayer!.ellipse.rx / fillLayer!.ellipse.rx).toBeCloseTo(0.58 / 0.4, 4);

    // Visuals description
    const visualA = result.visuals.find((v) => v.code === 'A')!;
    expect(visualA.fill_tone).toBe('match');
    expect(visualA.expected_ring).toBe(true);
    expect(visualA.aria_label).toContain('machine match');
    expect(visualA.aria_label).toContain('expected answer');

    expect(result.state_label).toBe('Marked');
  });

  it('renders independent machine fill and expected ring for a definite mismatch', () => {
    const result = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'B',
    );
    expect(result.status).toBe('ready');
    expect(result.layers).toHaveLength(2);

    const fillA = result.layers.find((l) => l.code === 'A' && l.kind === 'machine-fill')!;
    const ringB = result.layers.find((l) => l.code === 'B' && l.kind === 'expected-ring')!;

    expect(fillA.tone).toBe('mismatch');
    expect(fillA.icon).toBe('×');
    expect(ringB.tone).toBe('expected');
    expect(ringB.icon).toBe('○');

    // No expected ring on A; no machine fill on B
    expect(result.layers.some((l) => l.code === 'A' && l.kind === 'expected-ring')).toBe(false);
    expect(result.layers.some((l) => l.code === 'B' && l.kind === 'machine-fill')).toBe(false);

    const visualA = result.visuals.find((v) => v.code === 'A')!;
    expect(visualA.fill_tone).toBe('mismatch');
    expect(visualA.expected_ring).toBe(false);

    const visualB = result.visuals.find((v) => v.code === 'B')!;
    expect(visualB.fill_tone).toBeNull();
    expect(visualB.expected_ring).toBe(true);
  });

  it('renders expected ring only and no machine fill for an expected blank item', () => {
    const result = createRecipe(
      { state: 'blank', selected_option: null, observed_options: [] },
      'C',
    );
    expect(result.status).toBe('ready');
    expect(result.machine_state).toBe('blank');
    expect(result.layers).toHaveLength(1);

    const ringC = result.layers[0];
    expect(ringC.kind).toBe('expected-ring');
    expect(ringC.code).toBe('C');
    expect(ringC.tone).toBe('expected');
    expect(ringC.icon).toBe('○');

    expect(result.layers.some((l) => l.kind === 'machine-fill')).toBe(false);
    expect(result.visuals.every((v) => v.fill_tone === null)).toBe(true);
    expect(result.visuals.find((v) => v.code === 'C')!.expected_ring).toBe(true);
    expect(result.state_label).toBe('Blank');
    expect(overlayStateIcon(result)).toBe('○');
  });

  it('renders amber candidate fills and keeps expected ring when expected is one ambiguous candidate', () => {
    const result = createRecipe(
      { state: 'ambiguous', selected_option: null, observed_options: ['A', 'B'] },
      'B',
    );
    expect(result.status).toBe('ready');
    expect(result.machine_state).toBe('ambiguous');

    // Layers: fill A (amber), fill B (amber), ring B (green expected)
    expect(result.layers).toHaveLength(3);
    const fills = result.layers.filter((l) => l.kind === 'machine-fill');
    expect(fills.map((l) => l.code)).toEqual(['A', 'B']);
    expect(fills.every((l) => l.tone === 'ambiguous')).toBe(true);
    expect(fills.every((l) => l.icon === '?')).toBe(true);

    const ring = result.layers.find((l) => l.kind === 'expected-ring')!;
    expect(ring.code).toBe('B');
    expect(ring.tone).toBe('expected');

    // On option B, fill and expected ring both exist and remain concentric
    const fillB = fills.find((l) => l.code === 'B')!;
    expect(ring.ellipse.cx).toBeCloseTo(fillB.ellipse.cx, 8);
    expect(ring.ellipse.cy).toBeCloseTo(fillB.ellipse.cy, 8);
    expect(ring.ellipse.rx).toBeGreaterThan(fillB.ellipse.rx);

    const visualB = result.visuals.find((v) => v.code === 'B')!;
    expect(visualB.fill_tone).toBe('ambiguous');
    expect(visualB.expected_ring).toBe(true);
    expect(visualB.aria_label).toContain('ambiguous machine candidate; expected answer');
  });

  it('renders amber candidate fills and independent ring when expected is not an ambiguous candidate', () => {
    const result = createRecipe(
      { state: 'ambiguous', selected_option: null, observed_options: ['A', 'B'] },
      'D',
    );
    expect(result.status).toBe('ready');
    expect(result.layers).toHaveLength(3);

    const fills = result.layers.filter((l) => l.kind === 'machine-fill');
    expect(fills.map((l) => l.code)).toEqual(['A', 'B']);
    expect(fills.every((l) => l.tone === 'ambiguous')).toBe(true);

    const ring = result.layers.find((l) => l.kind === 'expected-ring')!;
    expect(ring.code).toBe('D');
    expect(result.layers.some((l) => l.code === 'D' && l.kind === 'machine-fill')).toBe(false);
  });

  it('renders unreadable candidate hatching without asserted-answer fill and retains independent expected ring', () => {
    const result = createRecipe(
      { state: 'unreadable', selected_option: null, observed_options: ['A'] },
      'B',
    );
    expect(result.status).toBe('ready');
    expect(result.machine_state).toBe('unreadable');

    // No machine-fill layer must ever be asserted for unreadable state
    expect(result.layers.some((l) => l.kind === 'machine-fill')).toBe(false);

    // Hatching on candidate A
    const hatchA = result.layers.find((l) => l.kind === 'candidate-hatch')!;
    expect(hatchA).toBeDefined();
    expect(hatchA.code).toBe('A');
    expect(hatchA.tone).toBe('unreadable');
    expect(hatchA.pattern).toBe('unreadable-hatch');
    expect(hatchA.icon).toBe('!');
    expect(overlayLayerClass(hatchA)).toBe('candidate-hatch unreadable');

    // Independent expected ring on B
    const ringB = result.layers.find((l) => l.kind === 'expected-ring')!;
    expect(ringB).toBeDefined();
    expect(ringB.code).toBe('B');

    expect(result.state_label).toBe('Unreadable');
    expect(overlayStateIcon(result)).toBe('!');
  });

  it('renders neutral blue fills and no correctness ring when expected truth is absent', () => {
    // Marked without expected truth
    const markedNeutral = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      null,
    );
    expect(markedNeutral.status).toBe('ready');
    expect(markedNeutral.layers).toHaveLength(1);
    expect(markedNeutral.layers[0].kind).toBe('machine-fill');
    expect(markedNeutral.layers[0].code).toBe('A');
    expect(markedNeutral.layers[0].tone).toBe('neutral');
    expect(markedNeutral.layers[0].icon).toBe('•');
    expect(markedNeutral.layers.some((l) => l.kind === 'expected-ring')).toBe(false);
    expect(markedNeutral.state_label).toBe('Marked; no expected answer');

    // Ambiguous without expected truth
    const ambiguousNeutral = createRecipe(
      { state: 'ambiguous', selected_option: null, observed_options: ['A', 'C'] },
      null,
    );
    expect(ambiguousNeutral.layers).toHaveLength(2);
    expect(ambiguousNeutral.layers.every((l) => l.tone === 'neutral')).toBe(true);
    expect(ambiguousNeutral.layers.some((l) => l.kind === 'expected-ring')).toBe(false);
    expect(ambiguousNeutral.state_label).toBe('Ambiguous; no expected answer');

    // Unreadable without expected truth
    const unreadableNeutral = createRecipe(
      { state: 'unreadable', selected_option: null, observed_options: ['B'] },
      null,
    );
    expect(unreadableNeutral.layers).toHaveLength(1);
    expect(unreadableNeutral.layers[0].kind).toBe('candidate-hatch');
    expect(unreadableNeutral.layers[0].tone).toBe('neutral');
    expect(unreadableNeutral.layers.some((l) => l.kind === 'expected-ring')).toBe(false);
  });
});

describe('OMR overlay recipe - OpenSpec 6.6 geometry transforms and corners', () => {
  it('correctly registers items and bubbles placed at all 4 extreme corners of the page', () => {
    const corners = [
      {
        name: 'top-left',
        item: { x: 0.001, y: 0.001, w: 0.2, h: 0.06 },
        option: { x: 0.005, y: 0.005, w: 0.03, h: 0.03 },
      },
      {
        name: 'top-right',
        item: { x: 0.79, y: 0.001, w: 0.2, h: 0.06 },
        option: { x: 0.95, y: 0.005, w: 0.03, h: 0.03 },
      },
      {
        name: 'bottom-left',
        item: { x: 0.001, y: 0.93, w: 0.2, h: 0.06 },
        option: { x: 0.005, y: 0.94, w: 0.03, h: 0.03 },
      },
      {
        name: 'bottom-right',
        item: { x: 0.79, y: 0.93, w: 0.2, h: 0.06 },
        option: { x: 0.95, y: 0.94, w: 0.03, h: 0.03 },
      },
    ];

    for (const corner of corners) {
      const cornerGeo = {
        item_rect: corner.item,
        crop_rect: corner.item,
        options: [{ code: 'A', region: corner.option }],
      };
      const result = createRecipe(
        { state: 'marked', selected_option: 'A', observed_options: ['A'] },
        'A',
        cornerGeo,
      );
      expect(result.status, `Corner ${corner.name} should be ready`).toBe('ready');
      expect(result.layers).toHaveLength(2);
      const fill = result.layers.find((l) => l.kind === 'machine-fill')!;
      expect(fill.ellipse.cx).toBeGreaterThan(0);
      expect(fill.ellipse.cx).toBeLessThan(1);
      expect(fill.ellipse.cy).toBeGreaterThan(0);
      expect(fill.ellipse.cy).toBeLessThan(1);
      expect(fill.ellipse.rx).toBeGreaterThan(0);
      expect(fill.ellipse.ry).toBeGreaterThan(0);
    }
  });

  it('transforms options accurately through dedicated crop source rect and unit viewport', () => {
    const sourceRect = { x: 0.1, y: 0.2, w: 0.7, h: 0.08 };
    const dedicatedAsset = {
      url: '/crops/item-12.png',
      width: 700,
      height: 80,
      sha256: validHash,
      page_no: 1,
      kind: 'dedicated_crop',
    };
    const dedicatedGeometry = {
      ...standardGeometry,
      crop_asset: dedicatedAsset,
      crop_source: {
        kind: 'dedicated_crop',
        type: 'dedicated_crop',
        source_rect: sourceRect,
        transform: null,
      },
    };

    const result = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'B',
      dedicatedGeometry,
    );
    expect(result.status).toBe('ready');

    // Viewport must be the unit space of the dedicated crop asset
    expect(result.viewport).toEqual({ x: 0, y: 0, w: 1, h: 1 });
    expect(result.source_rect).toEqual(sourceRect);

    // Aspect ratio must equal the physical dedicated crop asset ratio 700 / 80 = 8.75
    const ratio = cropAspectRatio(result.viewport, result.asset);
    expect(ratio).toBeCloseTo(700 / 80, 6);

    // Option A was at x: 0.2 on full page. Relative to sourceRect (x: 0.1, w: 0.7):
    // expected local x = (0.2 - 0.1) / 0.7 = 0.142857
    const visualA = result.visuals.find((v) => v.code === 'A')!;
    expect(visualA.geometry.x).toBeCloseTo((0.2 - 0.1) / 0.7, 6);
    expect(visualA.geometry.w).toBeCloseTo(0.04 / 0.7, 6);
  });

  it('transforms option regions through affine matrix on dedicated crop', () => {
    const sourceRect = { x: 0.1, y: 0.2, w: 0.7, h: 0.08 };
    const optionRegion = { x: 0.2, y: 0.22, w: 0.04, h: 0.04 };

    // Standard local region without matrix
    const local = transformRegionToViewport(optionRegion, sourceRect)!;

    // Affine matrix applying 1.1x scale and +0.02 translate
    const matrix = [1.1, 0, 0, 1.1, 0.02, 0.01];
    const transformed = transformOptionRegion(optionRegion, sourceRect, { matrix });

    expect(transformed).not.toBeNull();
    expect(transformed!.x).toBeCloseTo(local.x * 1.1 + 0.02, 6);
    expect(transformed!.y).toBeCloseTo(local.y * 1.1 + 0.01, 6);
    expect(transformed!.w).toBeCloseTo(local.w * 1.1, 6);
    expect(transformed!.h).toBeCloseTo(local.h * 1.1, 6);
  });

  it('transforms option regions through explicit scale and translation', () => {
    const sourceRect = { x: 0.1, y: 0.2, w: 0.7, h: 0.08 };
    const optionRegion = { x: 0.2, y: 0.22, w: 0.04, h: 0.04 };

    const local = transformRegionToViewport(optionRegion, sourceRect)!;
    const transform = { scale_x: 0.9, scale_y: 0.95, translate_x: 0.03, translate_y: 0.01 };
    const transformed = transformOptionRegion(optionRegion, sourceRect, transform);

    expect(transformed).not.toBeNull();
    expect(transformed!.x).toBeCloseTo(local.x * 0.9 + 0.03, 6);
    expect(transformed!.y).toBeCloseTo(local.y * 0.95 + 0.01, 6);
    expect(transformed!.w).toBeCloseTo(local.w * 0.9, 6);
    expect(transformed!.h).toBeCloseTo(local.h * 0.95, 6);
  });
});

describe('OMR overlay recipe - OpenSpec 6.6 fail-closed provenance validation', () => {
  it('fails closed when machine evidence is completely missing', () => {
    const result = buildBubbleLayerRecipe({
      machine: null,
      expected: { option: 'A' },
      geometry: standardGeometry,
    });
    expect(result.status).toBe('unavailable');
    expect(result.unavailable_reasons).toContain('missing-machine-evidence');
  });

  it('fails closed when marked machine evidence lacks a selected option', () => {
    const result = createRecipe(
      { state: 'marked', selected_option: null, observed_options: [] },
      'A',
    );
    expect(result.status).toBe('unavailable');
    expect(result.unavailable_reasons).toContain('missing-machine-evidence');
  });

  it('fails closed when ambiguous machine evidence has fewer than 2 observed candidates', () => {
    const result = createRecipe(
      { state: 'ambiguous', selected_option: null, observed_options: ['A'] },
      'A',
    );
    expect(result.status).toBe('unavailable');
    expect(result.unavailable_reasons).toContain('missing-machine-evidence');
  });

  it('fails closed when item geometry is missing or out of bounds', () => {
    const missing = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { item_rect: null, crop_rect: null },
    );
    expect(missing.status).toBe('unavailable');
    expect(missing.unavailable_reasons).toContain('missing-item-geometry');

    const outOfBounds = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { item_rect: { x: -0.05, y: 0.2, w: 0.5, h: 0.08 } },
    );
    expect(outOfBounds.status).toBe('unavailable');
    expect(outOfBounds.unavailable_reasons).toContain('geometry-out-of-bounds');
  });

  it('fails closed when option geometries are duplicate or out of bounds', () => {
    const duplicate = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      {
        options: [
          { code: 'A', region: { x: 0.2, y: 0.22, w: 0.04, h: 0.04 } },
          { code: 'A', region: { x: 0.3, y: 0.22, w: 0.04, h: 0.04 } },
        ],
      },
    );
    expect(duplicate.status).toBe('unavailable');
    expect(duplicate.unavailable_reasons).toContain('duplicate-option-geometry');

    const outOfBounds = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      {
        options: [{ code: 'A', region: { x: 1.05, y: 0.22, w: 0.04, h: 0.04 } }],
      },
    );
    expect(outOfBounds.status).toBe('unavailable');
    expect(outOfBounds.unavailable_reasons).toContain('geometry-out-of-bounds');
  });

  it('fails closed when expected option or observed candidate has no matching geometry', () => {
    const missingExpected = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'Z', // Z is not in options A, B, C, D
    );
    expect(missingExpected.status).toBe('unavailable');
    expect(missingExpected.unavailable_reasons).toContain('missing-expected-geometry');

    const missingObserved = createRecipe(
      { state: 'ambiguous', selected_option: null, observed_options: ['A', 'Z'] },
      'A',
    );
    expect(missingObserved.status).toBe('unavailable');
    expect(missingObserved.unavailable_reasons).toContain('missing-observed-geometry');
  });

  it('fails closed when asset provenance or dimensions or hash are missing or invalid', () => {
    // Missing asset entirely
    const noAsset = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { aligned_asset: null, crop_asset: null },
    );
    expect(noAsset.status).toBe('unavailable');
    expect(noAsset.unavailable_reasons).toContain('missing-asset');

    // Missing dimensions
    const noDims = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { aligned_asset: { ...standardGeometry.aligned_asset, width: null, height: 0 } },
    );
    expect(noDims.status).toBe('unavailable');
    expect(noDims.unavailable_reasons).toContain('missing-asset-dimensions');

    // Invalid hash
    const badHash = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { aligned_asset: { ...standardGeometry.aligned_asset, sha256: 'short-hash' } },
    );
    expect(badHash.status).toBe('unavailable');
    expect(badHash.unavailable_reasons).toContain('missing-asset-hash');
  });

  it('fails closed when evidence hash or geometry manifest hash or version is missing', () => {
    const noEvidenceHash = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'], evidence_hash: null },
      'A',
    );
    expect(noEvidenceHash.status).toBe('unavailable');
    expect(noEvidenceHash.unavailable_reasons).toContain('missing-evidence-hash');

    const noGeoHash = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { geometry_manifest_hash: null },
    );
    expect(noGeoHash.status).toBe('unavailable');
    expect(noGeoHash.unavailable_reasons).toContain('missing-geometry-hash');

    const noVersion = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { evidence_version: null },
    );
    expect(noVersion.status).toBe('unavailable');
    expect(noVersion.unavailable_reasons).toContain('missing-evidence-version');
  });

  it('fails closed when page number is missing or mismatches between geometry and asset', () => {
    // Missing page
    const noPage = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { page_no: null, aligned_asset: { ...standardGeometry.aligned_asset, page_no: null } },
    );
    expect(noPage.status).toBe('unavailable');
    expect(noPage.unavailable_reasons).toContain('missing-page');

    // Mismatched page (geometry page 1 vs asset page 2)
    const mismatchedPage = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      { page_no: 1, aligned_asset: { ...standardGeometry.aligned_asset, page_no: 2 } },
    );
    expect(mismatchedPage.status).toBe('unavailable');
    expect(mismatchedPage.unavailable_reasons).toContain('missing-page');
  });

  it('fails closed when dedicated crop has missing source rect or invalid transform', () => {
    const dedicatedAsset = {
      url: '/crop.png',
      width: 700,
      height: 80,
      sha256: validHash,
      page_no: 1,
      kind: 'dedicated_crop',
    };

    // Missing source_rect
    const missingSourceRect = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      {
        crop_asset: dedicatedAsset,
        crop_source: { kind: 'dedicated_crop', source_rect: null },
      },
    );
    expect(missingSourceRect.status).toBe('unavailable');
    expect(missingSourceRect.unavailable_reasons).toContain('missing-crop-transform');

    // Source rect does not contain item_rect
    const notContaining = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      {
        crop_asset: dedicatedAsset,
        crop_source: {
          kind: 'dedicated_crop',
          source_rect: { x: 0.5, y: 0.5, w: 0.2, h: 0.08 }, // does not contain item at x: 0.1
        },
      },
    );
    expect(notContaining.status).toBe('unavailable');
    expect(notContaining.unavailable_reasons).toContain('geometry-out-of-bounds');

    // Invalid affine matrix (length !== 6)
    const badMatrix = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      {
        crop_asset: dedicatedAsset,
        crop_source: {
          kind: 'dedicated_crop',
          source_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
          transform: { matrix: [1, 2, 3] }, // invalid length
        },
      },
    );
    expect(badMatrix.status).toBe('unavailable');
    expect(badMatrix.unavailable_reasons).toContain('invalid-crop-transform');

    // Degenerate affine matrix (zero determinant)
    const degenerateMatrix = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      {
        crop_asset: dedicatedAsset,
        crop_source: {
          kind: 'dedicated_crop',
          source_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
          transform: { matrix: [0, 0, 0, 0, 0, 0] },
        },
      },
    );
    expect(degenerateMatrix.status).toBe('unavailable');
    expect(degenerateMatrix.unavailable_reasons).toContain('invalid-crop-transform');

    // Non-positive scale factor
    const badScale = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
      {
        crop_asset: dedicatedAsset,
        crop_source: {
          kind: 'dedicated_crop',
          source_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 },
          transform: { scale_x: -1, scale_y: 1 },
        },
      },
    );
    expect(badScale.status).toBe('unavailable');
    expect(badScale.unavailable_reasons).toContain('invalid-crop-transform');
  });

  it('selects dedicated crop sourceUrl when dedicated crop is configured', () => {
    const dedicatedAsset = {
      url: '/crop.png',
      width: 700,
      height: 80,
      sha256: validHash,
      page_no: 1,
      kind: 'dedicated_crop',
    };
    const input = normalizeOverlayInput({
      machine: { state: 'marked', selected_option: 'A', observed_options: ['A'], evidence_hash: validHash },
      geometry: {
        ...standardGeometry,
        crop_asset: dedicatedAsset,
        crop_source: { kind: 'dedicated_crop', source_rect: { x: 0.1, y: 0.2, w: 0.7, h: 0.08 } },
      },
      imageUrl: '/aligned.png',
    });
    // Must select the dedicated crop URL, NOT the full aligned page URL
    expect(input.source_url).toBe('/crop.png');
  });
});

describe('OMR overlay recipe - OpenSpec 6.5 & 6.6 zoom stability and accessibility equivalents', () => {
  it('maintains strict concentric centers and proportional scaling between fill and expected ring', () => {
    const result = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'A',
    );
    const fill = result.layers.find((l) => l.kind === 'machine-fill')!;
    const ring = result.layers.find((l) => l.kind === 'expected-ring')!;

    // Exactly concentric regardless of dimensions
    expect(fill.ellipse.cx).toBe(ring.ellipse.cx);
    expect(fill.ellipse.cy).toBe(ring.ellipse.cy);

    // Radii scale proportionally with bubble size; ring is strictly 45% larger
    expect(ring.ellipse.rx / fill.ellipse.rx).toBeCloseTo(0.58 / 0.40, 6);
    expect(ring.ellipse.ry / fill.ellipse.ry).toBeCloseTo(0.58 / 0.40, 6);

    // Non-zero radii
    expect(fill.ellipse.rx).toBeGreaterThan(0.001);
    expect(fill.ellipse.ry).toBeGreaterThan(0.001);
  });

  it('assigns truthful layer CSS classes and fill/stroke separation', () => {
    const result = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'] },
      'B',
    );
    const fill = result.layers.find((l) => l.kind === 'machine-fill')!;
    const ring = result.layers.find((l) => l.kind === 'expected-ring')!;

    expect(overlayLayerClass(fill)).toBe('machine-fill mismatch');
    expect(overlayLayerClass(ring)).toBe('expected-ring');
  });

  it('provides complete textual state labels and ARIA descriptions', () => {
    const result = createRecipe(
      { state: 'marked', selected_option: 'A', observed_options: ['A'], issue_codes: ['faint_mark'] },
      'B',
      {},
      { decision: 'set_option', selected_option: 'B', text: 'Reviewed: set_option B' },
    );

    expect(result.state_label).toBe('Marked');
    expect(result.description).toContain('Machine state: Marked');
    expect(result.description).toContain('Observed options: A');
    expect(result.description).toContain('Expected option: B');
    expect(result.description).toContain('Issues: faint_mark');

    // Review summary is appended to accessible text
    const text = overlayAccessibleText(result);
    expect(text).toContain('Reviewed: set_option B');
  });

  it('provides correct state icons for all machine states', () => {
    expect(overlayStateIcon(createRecipe({ state: 'marked', selected_option: 'A', observed_options: ['A'] }, 'A'))).toBe('●');
    expect(overlayStateIcon(createRecipe({ state: 'blank', selected_option: null, observed_options: [] }, 'A'))).toBe('○');
    expect(overlayStateIcon(createRecipe({ state: 'ambiguous', selected_option: null, observed_options: ['A', 'B'] }, 'A'))).toBe('?');
    expect(overlayStateIcon(createRecipe({ state: 'unreadable', selected_option: null, observed_options: ['A'] }, 'A'))).toBe('!');

    const unavailable = buildBubbleLayerRecipe({ machine: null, geometry: standardGeometry });
    expect(overlayStateIcon(unavailable)).toBe('□');
  });

  it('describes unavailable states truthfully with all missing provenance reasons', () => {
    const result = buildBubbleLayerRecipe({
      machine: null,
      expected: { option: 'A' },
      geometry: { ...standardGeometry, aligned_asset: null, geometry_manifest_hash: null },
    });
    expect(result.status).toBe('unavailable');
    const text = overlayAccessibleText(result);
    expect(text).toContain('Evidence unavailable:');
    expect(text).toContain('missing-machine-evidence');
    expect(text).toContain('missing-asset');
    expect(text).toContain('missing-geometry-hash');
    expect(text).toContain('Machine evidence remains unchanged.');
  });
});
