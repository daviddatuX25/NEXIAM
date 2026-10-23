const TARGET_PPI = 300;
const MM_PER_INCH = 25.4;
const PX_PER_MM = TARGET_PPI / MM_PER_INCH;
const MIN_BUBBLE_MM = 4.2;
const MIN_UPLOADED_BUBBLE_MM = 2.5;
const OMR_BUBBLE_DIAMETER_MM = 4.5;
const MIN_OPTION_GAP_MM = 1.26;
const GRID_BUBBLE_ORIGIN_OFFSET_MM = { x: 5, y: 8 };
const ROLL_BUBBLE_ORIGIN_OFFSET_MM = { x: 6.4, y: 14 };
const ALLOWED_INSET_RANGE_MM = [3, 40];
function bubbleLabelLayout(label, diameterMm) {
  const rawDiameterPx = Math.max(1, (Number(diameterMm) || 4.5) * 2.476);
  const innerDiameterPx = Math.max(0, rawDiameterPx - 2);
  const cleanLabel = String(label ?? "").trim();
  const len = cleanLabel.length;
  if (len === 0) {
    return { fitsInside: false, fontSizePx: 0, showInside: false };
  }
  if (len === 1) {
    const optimalSize2 = Math.max(3.5, Math.min(8, innerDiameterPx * 0.75));
    const fitsInside2 = innerDiameterPx >= 3;
    return {
      fitsInside: fitsInside2,
      fontSizePx: Number(optimalSize2.toFixed(1)),
      showInside: fitsInside2
    };
  }
  if (len === 2) {
    const optimalSize2 = Math.max(4, Math.min(6.5, innerDiameterPx * 0.85 / 1.3));
    const fitsInside2 = innerDiameterPx >= 6.5;
    return {
      fitsInside: fitsInside2,
      fontSizePx: Number(optimalSize2.toFixed(1)),
      showInside: fitsInside2
    };
  }
  const estCharWidthFactor = 0.58;
  const maxFontForWidth = innerDiameterPx / (len * estCharWidthFactor);
  const optimalSize = Math.min(6, maxFontForWidth);
  const fitsInside = optimalSize >= 4 && innerDiameterPx >= 10;
  return {
    fitsInside,
    fontSizePx: Number(Math.max(4, optimalSize).toFixed(1)),
    showInside: fitsInside
  };
}
function mmToPx(mm) {
  return Math.round(mm * PX_PER_MM);
}
function pxToMm(px) {
  return Number((px / PX_PER_MM).toFixed(2));
}
function minimumRecognitionPitchMm(bubbleDiameterMm = OMR_BUBBLE_DIAMETER_MM, minBubbleMm = MIN_BUBBLE_MM) {
  const bubble = Number(bubbleDiameterMm);
  const effectiveBubble = Number.isFinite(bubble) && bubble > 0 ? Math.max(minBubbleMm, bubble) : OMR_BUBBLE_DIAMETER_MM;
  return Number((effectiveBubble + MIN_OPTION_GAP_MM).toFixed(2));
}
function effectiveRecognitionPitchMm(pitchMm, bubbleDiameterMm = OMR_BUBBLE_DIAMETER_MM, fallbackMm = OMR_BUBBLE_DIAMETER_MM + MIN_OPTION_GAP_MM, minBubbleMm = MIN_BUBBLE_MM) {
  const requested = Number(pitchMm);
  const fallback = Number(fallbackMm);
  const minimum = minimumRecognitionPitchMm(
    bubbleDiameterMm,
    minBubbleMm
  );
  const requestedOrFallback = Number.isFinite(requested) && requested > 0 ? requested : Number.isFinite(fallback) && fallback > 0 ? fallback : minimum;
  return Math.max(minimum, requestedOrFallback);
}
function effectiveRecognitionGeometryMm(input) {
  const bubble = Number(input.bubbleDiameterMm);
  const minBubble = input.minBubbleMm ?? MIN_BUBBLE_MM;
  const bubbleDiameterMm = Number.isFinite(bubble) && bubble > 0 ? Math.max(minBubble, bubble) : OMR_BUBBLE_DIAMETER_MM;
  const defaults = input.kind === "roll" ? { bubblePitchMm: 5.5, rowPitchMm: 4.8 } : { bubblePitchMm: 6, rowPitchMm: 6.8 };
  return {
    bubbleDiameterMm,
    bubblePitchMm: effectiveRecognitionPitchMm(
      input.bubblePitchMm,
      bubbleDiameterMm,
      defaults.bubblePitchMm,
      minBubble
    ),
    rowPitchMm: effectiveRecognitionPitchMm(
      input.rowPitchMm,
      bubbleDiameterMm,
      defaults.rowPitchMm,
      minBubble
    )
  };
}
function contentBlockSizeMm(options, questions, bubblePitchMm, rowPitchMm, bubbleDiameterMm = OMR_BUBBLE_DIAMETER_MM) {
  const opts = Math.max(2, options || 4);
  const qs = Math.min(Math.max(1, questions || 1), 50);
  const geometry = effectiveRecognitionGeometryMm({
    kind: "grid",
    bubbleDiameterMm,
    bubblePitchMm,
    rowPitchMm
  });
  const {
    bubbleDiameterMm: bubble,
    bubblePitchMm: bPitch,
    rowPitchMm: rPitch
  } = geometry;
  const w_mm = Number((bubble + (opts - 1) * bPitch + 1.5).toFixed(1));
  const h_mm = Number((bubble + (qs - 1) * rPitch + 1.5).toFixed(1));
  return { w_mm, h_mm };
}
function rotateBubblePositionsPx(rows, bubbleWidthPx, angleDeg) {
  if (angleDeg === 0) {
    return rows;
  }
  const first = rows[0]?.[0];
  if (!first || !Number.isFinite(angleDeg)) {
    return rows;
  }
  const centerX = first[0] + bubbleWidthPx / 2;
  const centerY = first[1] + bubbleWidthPx / 2;
  const radians = angleDeg * Math.PI / 180;
  const cos = Math.cos(radians);
  const sin = Math.sin(radians);
  return rows.map(
    (row) => row.map(([x, y]) => {
      const dx = x + bubbleWidthPx / 2 - centerX;
      const dy = y + bubbleWidthPx / 2 - centerY;
      return [
        Math.round(centerX + dx * cos - dy * sin - bubbleWidthPx / 2),
        Math.round(centerY + dx * sin + dy * cos - bubbleWidthPx / 2)
      ];
    })
  );
}
function verticalRollBubblePositionsPx(input) {
  const rows = Array.from(
    { length: input.digitCount },
    (_, digitIndex) => Array.from({ length: 10 }, (_2, value) => [
      input.originX + digitIndex * input.digitPitchPx,
      input.originY + value * input.valuePitchPx
    ])
  );
  return rotateBubblePositionsPx(
    rows,
    input.bubbleWidthPx,
    input.rotationDeg ?? 0
  );
}
function recognitionOriginOffsetMm(block) {
  return block.type === "roll" ? ROLL_BUBBLE_ORIGIN_OFFSET_MM : GRID_BUBBLE_ORIGIN_OFFSET_MM;
}
function emitNativeMapping(pageWidthMm, pageHeightMm, blocks, pageCount, options) {
  const nativeBlocks = [];
  for (const block of blocks) {
    if (["mcq", "tf", "likert", "custom"].includes(block.type)) {
      const qs = Math.max(1, block.questions ?? 10);
      const cols = Math.max(1, block.cols ?? 1);
      let opts = 4;
      if (block.type === "tf") opts = 2;
      else if (block.type === "likert") opts = 5;
      else if (block.type === "custom" && block.custom_values && block.custom_values.length > 0)
        opts = block.custom_values.length;
      else opts = block.options === 5 ? 5 : block.options === 2 ? 2 : 4;
      const start = Math.max(1, block.start_q ?? 1);
      const rowsPerCol = Math.ceil(qs / cols);
      const geometry = effectiveRecognitionGeometryMm({
        kind: "grid",
        bubbleDiameterMm: block.bubble_diameter_mm,
        bubblePitchMm: block.bubble_pitch_mm,
        rowPitchMm: block.row_pitch_mm,
        minBubbleMm: options?.minBubbleMm
      });
      const bubbleSizePx = mmToPx(geometry.bubbleDiameterMm);
      const hasCustomOptionPitch = Number.isFinite(Number(block.bubble_pitch_mm)) && Number(block.bubble_pitch_mm) > 0;
      const hasCustomRowPitch = Number.isFinite(Number(block.row_pitch_mm)) && Number(block.row_pitch_mm) > 0;
      const customOptionPitch = hasCustomOptionPitch ? mmToPx(geometry.bubblePitchMm) : null;
      const customRowPitch = hasCustomRowPitch ? mmToPx(geometry.rowPitchMm) : null;
      const optionLabels = block.bubble_values ?? block.optionLabels;
      const isStandardOptionLabels = optionLabels?.length === 4 && optionLabels.every(
        (label, index) => label === ["A", "B", "C", "D"][index]
      ) || optionLabels?.length === 5 && optionLabels.every(
        (label, index) => label === ["A", "B", "C", "D", "E"][index]
      );
      const customOptionLabels = Array.isArray(optionLabels) && optionLabels.every((label) => typeof label === "string") && !isStandardOptionLabels;
      if (cols === 1) {
        const labels = Array.from(
          { length: qs },
          (_, i) => block.area_code ? `${block.area_code}_Q_${start + i}` : `q${start + i}`
        );
        const rawBlockW = mmToPx(block.w_mm);
        const rawBlockH = mmToPx(block.h_mm);
        const optionPitch = customOptionPitch ?? Math.max(bubbleSizePx + 15, Math.round(rawBlockW / opts));
        const rowPitch = customRowPitch ?? Math.max(
          bubbleSizePx + 15,
          Math.round(rawBlockH / rowsPerCol)
        );
        const explicitPositions = qs === 1 && block.option_points_mm?.length === opts ? [
          block.option_points_mm.map((point) => [
            mmToPx(point.x_mm) - Math.floor(bubbleSizePx / 2),
            mmToPx(point.y_mm) - Math.floor(bubbleSizePx / 2)
          ])
        ] : void 0;
        const explicitXs = explicitPositions?.[0].map((point) => point[0]) ?? [];
        const explicitYs = explicitPositions?.[0].map((point) => point[1]) ?? [];
        const explicitWidth = explicitPositions && explicitXs.length > 0 ? Math.max(...explicitXs) - Math.min(...explicitXs) + bubbleSizePx : null;
        const explicitHeight = explicitPositions && explicitYs.length > 0 ? Math.max(...explicitYs) - Math.min(...explicitYs) + bubbleSizePx : null;
        const requiredW = bubbleSizePx + (opts - 1) * optionPitch;
        const requiredH = bubbleSizePx + (rowsPerCol - 1) * rowPitch;
        const blockW = rowsPerCol === 1 ? requiredW : Math.max(rawBlockW, requiredW);
        const blockH = rowsPerCol === 1 ? requiredH : Math.max(rawBlockH, requiredH);
        nativeBlocks.push({
          name: block.id,
          page_no: block.page_no ?? 1,
          field_type: opts === 5 ? "QTYPE_MCQ5" : "QTYPE_MCQ4",
          label_prefix: "q",
          labels,
          ...block.subtest_code ? { subtest_code: block.subtest_code } : {},
          ...block.test_instrument_subtest_id != null ? {
            test_instrument_subtest_id: block.test_instrument_subtest_id
          } : {},
          rows: rowsPerCol,
          options: optionLabels?.length ?? opts,
          ...customOptionLabels ? { bubble_values: optionLabels } : {},
          origin_x: mmToPx(
            block.x_mm + recognitionOriginOffsetMm(block).x
          ),
          origin_y: mmToPx(
            block.y_mm + recognitionOriginOffsetMm(block).y
          ),
          width: explicitWidth ?? blockW,
          height: explicitHeight ?? blockH,
          option_pitch_px: optionPitch,
          row_pitch_px: rowPitch,
          bubble_width_px: bubbleSizePx,
          bubble_height_px: bubbleSizePx,
          ...explicitPositions ? { bubble_positions: explicitPositions } : {}
        });
      } else {
        const colWidthMm = block.w_mm / cols;
        for (let c = 0; c < cols; c++) {
          const colStart = start + c * rowsPerCol;
          const colCount = Math.min(rowsPerCol, qs - c * rowsPerCol);
          if (colCount <= 0) break;
          const labels = Array.from(
            { length: colCount },
            (_, i) => block.area_code ? `${block.area_code}_Q_${colStart + i}` : `q${colStart + i}`
          );
          const colX = block.x_mm + c * colWidthMm;
          const blockW = mmToPx(colWidthMm);
          const blockH = mmToPx(block.h_mm);
          const optionPitch = customOptionPitch ?? Math.max(bubbleSizePx + 15, Math.round(blockW / opts));
          const rowPitch = customRowPitch ?? Math.max(
            bubbleSizePx + 15,
            Math.round(blockH / rowsPerCol)
          );
          nativeBlocks.push({
            name: `${block.id}_col${c + 1}`,
            page_no: block.page_no ?? 1,
            field_type: opts === 5 ? "QTYPE_MCQ5" : "QTYPE_MCQ4",
            label_prefix: "q",
            labels,
            rows: colCount,
            options: optionLabels?.length ?? opts,
            ...block.subtest_code ? { subtest_code: block.subtest_code } : {},
            ...block.test_instrument_subtest_id != null ? {
              test_instrument_subtest_id: block.test_instrument_subtest_id
            } : {},
            ...customOptionLabels ? { bubble_values: optionLabels } : {},
            origin_x: mmToPx(
              colX + recognitionOriginOffsetMm(block).x
            ),
            origin_y: mmToPx(
              block.y_mm + recognitionOriginOffsetMm(block).y
            ),
            width: blockW,
            height: blockH,
            option_pitch_px: optionPitch,
            row_pitch_px: rowPitch,
            bubble_width_px: bubbleSizePx,
            bubble_height_px: bubbleSizePx
          });
        }
      }
    } else if (block.type === "roll") {
      const digits = Math.max(2, Math.min(10, block.digits ?? 7));
      const geometry = effectiveRecognitionGeometryMm({
        kind: "roll",
        bubbleDiameterMm: block.bubble_diameter_mm,
        bubblePitchMm: block.bubble_pitch_mm,
        rowPitchMm: block.row_pitch_mm,
        minBubbleMm: options?.minBubbleMm
      });
      const bubbleSizePx = mmToPx(geometry.bubbleDiameterMm);
      const labels = Array.from(
        { length: digits },
        (_, i) => `${block.id.toLowerCase()}_${i + 1}`
      );
      const minimumPitchPx = mmToPx(
        minimumRecognitionPitchMm(
          geometry.bubbleDiameterMm,
          options?.minBubbleMm
        )
      );
      const hasCustomOptionPitch = Number.isFinite(Number(block.bubble_pitch_mm)) && Number(block.bubble_pitch_mm) > 0;
      const hasCustomRowPitch = Number.isFinite(Number(block.row_pitch_mm)) && Number(block.row_pitch_mm) > 0;
      const colPitch = hasCustomOptionPitch ? mmToPx(geometry.bubblePitchMm) : Math.max(
        minimumPitchPx,
        Math.round(mmToPx(block.w_mm) / digits)
      );
      const rowPitch = hasCustomRowPitch ? mmToPx(geometry.rowPitchMm) : Math.max(minimumPitchPx, Math.round(mmToPx(block.h_mm) / 10));
      const blockW = Math.max(
        mmToPx(block.w_mm),
        bubbleSizePx + (digits - 1) * colPitch + 15
      );
      const blockH = Math.max(
        mmToPx(block.h_mm),
        bubbleSizePx + (10 - 1) * rowPitch + 15
      );
      const originX = mmToPx(
        block.x_mm + recognitionOriginOffsetMm(block).x
      );
      const originY = mmToPx(
        block.y_mm + recognitionOriginOffsetMm(block).y
      );
      const tiltedIdentityGrid = block.identity_grid === true && block.roll_direction !== "horizontal" && Number(block.rotation_deg ?? 0) !== 0;
      const bubblePositions = tiltedIdentityGrid ? verticalRollBubblePositionsPx({
        originX,
        originY,
        digitCount: digits,
        digitPitchPx: colPitch,
        valuePitchPx: rowPitch,
        bubbleWidthPx: bubbleSizePx,
        rotationDeg: Number(block.rotation_deg ?? 0)
      }) : void 0;
      const bubbleXs = bubblePositions?.flatMap(
        (row) => row.map(([x]) => x)
      ) ?? [];
      const bubbleYs = bubblePositions?.flatMap(
        (row) => row.map(([, y]) => y)
      ) ?? [];
      const positionedWidth = bubblePositions && bubbleXs.length > 0 ? Math.max(...bubbleXs) - Math.min(...bubbleXs) + bubbleSizePx : blockW;
      const positionedHeight = bubblePositions && bubbleYs.length > 0 ? Math.max(...bubbleYs) - Math.min(...bubbleYs) + bubbleSizePx : blockH;
      nativeBlocks.push({
        name: block.id,
        page_no: block.page_no ?? 1,
        field_type: "QTYPE_INT",
        label_prefix: `${block.id.toLowerCase()}_`,
        labels,
        rows: 10,
        options: 10,
        direction: "vertical",
        origin_x: originX,
        origin_y: originY,
        width: positionedWidth,
        height: positionedHeight,
        option_pitch_px: rowPitch,
        row_pitch_px: colPitch,
        bubble_width_px: bubbleSizePx,
        bubble_height_px: bubbleSizePx,
        ...bubblePositions ? { bubble_positions: bubblePositions } : {}
      });
    }
  }
  return {
    page_width: mmToPx(pageWidthMm),
    page_height: mmToPx(pageHeightMm),
    page_count: Math.max(
      pageCount ?? 1,
      ...blocks.map((block) => block.page_no ?? 1)
    ),
    blocks: nativeBlocks
  };
}
function emitOverlayBoxes(blocks, _pageWidthMm, _pageHeightMm) {
  const boxes = [];
  for (const block of blocks) {
    if (block.type === "qr") {
      boxes.push({
        name: block.id,
        page_no: block.page_no ?? 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: block.tag ?? "blank_instance_qr",
        scope: block.scope ?? "blank_instance",
        overflow: "truncate",
        content: null
      });
    } else if (block.type === "header" || block.type === "text" && block.tag === "header_title") {
      boxes.push({
        name: block.id,
        page_no: block.page_no ?? 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: "header_title",
        scope: "template",
        overflow: block.overflow ?? "shrink",
        font_size: block.font_size ?? 14,
        content: block.content ?? block.title ?? "EXAMINATION ANSWER SHEET"
      });
    } else if (block.type === "instructions" || block.tag === "instructions_box" || block.tag === "instruction_box") {
      boxes.push({
        name: block.id,
        page_no: block.page_no ?? 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: "instructions_box",
        scope: "template",
        overflow: block.overflow ?? "wrap",
        font_size: block.font_size ?? 10,
        content: block.content ?? block.title ?? "INSTRUCTIONS: USE 2B PENCIL ONLY"
      });
    } else if (block.type === "signature") {
      boxes.push({
        name: block.id,
        page_no: block.page_no ?? 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: "signature_box",
        scope: "template",
        overflow: block.overflow ?? "truncate",
        font_size: block.font_size ?? 9,
        content: block.content ?? block.title ?? "EXAMINEE SIGNATURE"
      });
    } else if (block.type === "metadata_box") {
      boxes.push({
        name: block.id,
        page_no: block.page_no ?? 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: "metadata_box",
        scope: "template",
        overflow: block.overflow ?? "truncate",
        font_size: block.font_size ?? 10,
        content: block.content ?? block.title ?? "EXAM METADATA"
      });
    } else if (block.type === "custom_text" || block.type === "text") {
      boxes.push({
        name: block.id,
        page_no: block.page_no ?? 1,
        x: mmToPx(block.x_mm),
        y: mmToPx(block.y_mm),
        width: mmToPx(block.w_mm),
        height: mmToPx(block.h_mm),
        tag: block.tag ?? "custom_text",
        scope: block.scope ?? "template",
        overflow: block.overflow ?? "truncate",
        font_size: block.font_size ?? 11,
        content: block.content ?? block.title ?? "CUSTOM TEXT"
      });
    }
  }
  return { boxes };
}
function validateStudioLayout(paper, insets, blocks) {
  const warnings = [];
  const m = paper.margin_mm;
  const minInset = ALLOWED_INSET_RANGE_MM[0];
  const maxInset = ALLOWED_INSET_RANGE_MM[1];
  for (const [side, val] of Object.entries(insets)) {
    if (val < minInset || val > maxInset) {
      warnings.push({
        code: "inset_out_of_range",
        message: `Marker inset on ${side.replace("_mm", "")} (${val}mm) must be between ${minInset}mm and ${maxInset}mm.`,
        severity: "error"
      });
    }
  }
  for (const b of blocks) {
    if (b.x_mm < m || b.y_mm < m || b.x_mm + b.w_mm > paper.width_mm - m || b.y_mm + b.h_mm > paper.height_mm - m) {
      warnings.push({
        block_id: b.id,
        code: "margin_breach",
        message: `Block "${b.id}" extends beyond the ${m}mm printable margin.`,
        severity: "warning"
      });
    }
  }
  for (const block of blocks) {
    if (block.rotation_deg !== 0 && block.rotation_deg != null && block.type !== "roll" && !block.option_points_mm?.length) {
      warnings.push({
        block_id: block.id,
        code: "tilted_points_missing",
        message: `Tilted question "${block.id}" has no explicit option points; saving an axis-aligned fallback is blocked.`,
        severity: "error"
      });
    }
    if (block.identity_grid === true && Number(block.rotation_deg ?? 0) !== 0 && (!Number.isFinite(Number(block.rotation_deg)) || Math.abs(Number(block.rotation_deg)) > 5)) {
      warnings.push({
        block_id: block.id,
        code: "identity_tilt_out_of_range",
        message: `Applicant Number grid "${block.id}" tilt must remain within ±5 degrees.`,
        severity: "error"
      });
    }
    if (block.identity_grid === true && block.roll_direction === "horizontal" && Number(block.rotation_deg ?? 0) !== 0) {
      warnings.push({
        block_id: block.id,
        code: "identity_tilt_unsupported",
        message: `Applicant Number grid "${block.id}" must be vertical to use machine-recognized tilt.`,
        severity: "error"
      });
    }
  }
  const nativeMapping = emitNativeMapping(
    paper.width_mm,
    paper.height_mm,
    blocks
  );
  const pageWidthPx = mmToPx(paper.width_mm);
  const pageHeightPx = mmToPx(paper.height_mm);
  for (const block of nativeMapping.blocks) {
    if (!block.bubble_positions) continue;
    const outOfBounds = block.bubble_positions.some(
      (row) => row.some(
        ([x, y]) => !Number.isInteger(x) || !Number.isInteger(y) || x < 0 || y < 0 || x + block.bubble_width_px > pageWidthPx || y + block.bubble_height_px > pageHeightPx
      )
    );
    if (outOfBounds) {
      warnings.push({
        block_id: block.name,
        code: "bubble_out_of_bounds",
        message: `A tilted bubble in "${block.name}" extends beyond the paper boundary.`,
        severity: "error"
      });
    }
  }
  for (let i = 0; i < blocks.length; i++) {
    for (let j = i + 1; j < blocks.length; j++) {
      const a = blocks[i];
      const b = blocks[j];
      if (a.page_no !== b.page_no) continue;
      const overlapX = a.x_mm < b.x_mm + b.w_mm && a.x_mm + a.w_mm > b.x_mm;
      const overlapY = a.y_mm < b.y_mm + b.h_mm && a.y_mm + a.h_mm > b.y_mm;
      if (overlapX && overlapY) {
        warnings.push({
          block_id: a.id,
          code: "block_collision",
          message: `Block "${a.id}" overlaps with block "${b.id}".`,
          severity: "error"
        });
      }
    }
  }
  return {
    valid: warnings.filter((w) => w.severity === "error").length === 0,
    warnings
  };
}
function assessUploadedMappingReadiness(input) {
  const issues = [];
  const declarationsByPage = /* @__PURE__ */ new Map();
  const usedGeometryIds = /* @__PURE__ */ new Set();
  for (let pageNo = 1; pageNo <= input.pageCount; pageNo++) {
    declarationsByPage.set(
      pageNo,
      input.inventory.filter(
        (declaration) => Number(declaration.page_no) === pageNo
      )
    );
  }
  for (let pageNo = 1; pageNo <= input.pageCount; pageNo++) {
    const declarations = declarationsByPage.get(pageNo) ?? [];
    if (declarations.length === 0) {
      issues.push(`Page ${pageNo}: declare its scored question inventory.`);
    }
    const areaCodes = /* @__PURE__ */ new Set();
    for (const declaration of declarations) {
      const areaCode = String(declaration.area_code ?? "").trim();
      if (!/^[A-Z][A-Z0-9_]*$/.test(areaCode)) {
        issues.push(
          `Page ${pageNo}: enter an uppercase area code using letters, numbers, or underscores.`
        );
      } else if (areaCodes.has(areaCode)) {
        issues.push(
          `Page ${pageNo}: area code ${areaCode} is declared more than once; mapping is ambiguous.`
        );
      } else {
        areaCodes.add(areaCode);
      }
      const questionCount = Number(declaration.question_count);
      if (!Number.isInteger(questionCount) || questionCount < 1) {
        issues.push(
          `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: enter the exact question count.`
        );
      }
      const optionLabels = Array.isArray(declaration.option_labels) ? declaration.option_labels.map((label) => String(label).trim()) : [];
      if (optionLabels.length < 2 || optionLabels.some((label) => !label)) {
        issues.push(
          `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: enter every option label.`
        );
      }
      const normalizedOptionLabels = optionLabels.map(
        (label) => label.toUpperCase()
      );
      if (normalizedOptionLabels.some(
        (label, index) => normalizedOptionLabels.indexOf(label) !== index
      )) {
        issues.push(
          `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: option labels contain a duplicate and are ambiguous.`
        );
      }
      const matchingAreaSections = input.sections.filter(
        (candidate) => candidate && candidate.type !== "roll" && Number(candidate.page_no) === pageNo && String(
          candidate.area_code ?? candidate.aptitudeAreaCode ?? ""
        ).trim() === areaCode
      );
      const summedAreaQuestions = matchingAreaSections.reduce(
        (sum, s) => sum + (Number(s.questionsCount) || 0),
        0
      );
      const geometryId = String(declaration.geometry_id ?? "");
      if (!geometryId && matchingAreaSections.length === 0) {
        issues.push(
          `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: map a recognition geometry block.`
        );
        continue;
      }
      if (geometryId) {
        if (usedGeometryIds.has(geometryId)) {
          issues.push(
            `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: recognition geometry is assigned more than once; mapping is ambiguous.`
          );
        }
        usedGeometryIds.add(geometryId);
      }
      const primarySection = input.sections.find(
        (candidate) => candidate.id === geometryId
      ) ?? matchingAreaSections[0];
      if (!primarySection || primarySection.type === "roll" || Number(primarySection.page_no) !== pageNo) {
        issues.push(
          `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: selected recognition geometry is missing or belongs to another page.`
        );
        continue;
      }
      const primaryAreaCode = String(
        primarySection.area_code ?? primarySection.aptitudeAreaCode ?? ""
      ).trim();
      if (primaryAreaCode !== areaCode) {
        issues.push(
          `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: recognition geometry area code does not match its declaration.`
        );
      }
      const effectiveMappedCount = matchingAreaSections.length > 1 && primaryAreaCode === areaCode ? summedAreaQuestions : Number(primarySection.questionsCount);
      if (effectiveMappedCount !== questionCount) {
        issues.push(
          `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: recognition geometry question count does not match its declaration.`
        );
      }
      const sectionsToValidate = matchingAreaSections.length > 1 && primaryAreaCode === areaCode ? matchingAreaSections : [primarySection];
      for (const section of sectionsToValidate) {
        const mappedOptionLabels = String(
          section.customValuesStr ?? ""
        ).split(",").map((label) => label.trim()).filter(Boolean);
        if (mappedOptionLabels.length !== optionLabels.length || mappedOptionLabels.some(
          (label, index) => label !== optionLabels[index]
        )) {
          issues.push(
            `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: recognition geometry option labels do not match the declaration.`
          );
          break;
        }
        for (const [label, value] of [
          ["origin X", section.posX],
          ["origin Y", section.posY],
          ["bubble diameter", section.bubbleDiameterMm],
          ["option pitch", section.bubblePitchMm],
          ["row pitch", section.rowPitchMm]
        ]) {
          const numericValue = Number(value);
          if (value === "" || value == null || !Number.isFinite(numericValue) || numericValue <= 0) {
            issues.push(
              `Page ${pageNo}${areaCode ? ` · ${areaCode}` : ""}: set a positive ${label} for the mapped geometry.`
            );
          }
        }
      }
    }
  }
  for (const declaration of input.inventory) {
    const pageNo = Number(declaration.page_no);
    if (!Number.isInteger(pageNo) || pageNo < 1 || pageNo > input.pageCount) {
      issues.push("An inventory area references a page that is not in this draft.");
    }
  }
  const identity = input.identity;
  const hasBubbles = Boolean(
    identity?.has_bubbles || identity?.strategy === "bubbles_and_write_in" || identity?.strategy === "bubbles_only" || identity?.method === "shaded_grid"
  );
  const hasWriteIn = Boolean(
    identity?.has_write_in || identity?.strategy === "bubbles_and_write_in" || identity?.strategy === "write_in_only" || identity?.method === "write_in_manual" || Array.isArray(identity?.write_in_boxes) && identity.write_in_boxes.length > 0 || Array.isArray(identity?.regions) && identity.regions.length > 0
  );
  if (!hasBubbles && !hasWriteIn) {
    issues.push("Select an explicit Applicant Number identity method.");
  }
  if (hasBubbles) {
    const digitCount = Number(identity?.digit_count);
    if (!Number.isInteger(digitCount) || digitCount < 2 || digitCount > 10) {
      issues.push(
        "Applicant Number grid: enter the exact digit count from the source."
      );
    }
    for (let pageNo = 1; pageNo <= input.pageCount; pageNo++) {
      const grids = input.sections.filter(
        (section) => section.identity_grid === true && section.type === "roll" && Number(section.page_no) === pageNo
      );
      if (grids.length === 0) {
        issues.push(
          `Page ${pageNo}: map the vertical shaded Applicant Number grid.`
        );
      } else if (grids.length > 1) {
        issues.push(
          `Page ${pageNo}: multiple Applicant Number grids are mapped; identity is ambiguous.`
        );
      }
      for (const grid of grids) {
        if (Number(grid.questionsCount) !== digitCount) {
          issues.push(
            `Page ${pageNo}: Applicant Number grid digit count does not match the identity declaration.`
          );
        }
        for (const [label, value] of [
          ["origin X", grid.posX],
          ["origin Y", grid.posY],
          ["bubble diameter", grid.bubbleDiameterMm],
          ["digit pitch", grid.bubblePitchMm],
          ["row pitch", grid.rowPitchMm]
        ]) {
          const numericValue = Number(value);
          if (value === "" || value == null || !Number.isFinite(numericValue) || numericValue <= 0) {
            issues.push(
              `Page ${pageNo}: set a positive ${label} for the Applicant Number grid.`
            );
          }
        }
      }
    }
  }
  if (hasWriteIn) {
    const boxes = Array.isArray(identity?.write_in_boxes) ? identity.write_in_boxes : [];
    const regions = Array.isArray(identity?.regions) ? identity.regions : [];
    if (boxes.length > 0) {
      for (let pageNo = 1; pageNo <= input.pageCount; pageNo++) {
        const pageBoxes = boxes.filter(
          (box) => Number(box.page_no) === pageNo
        );
        if (pageBoxes.length === 0) {
          issues.push(
            `Page ${pageNo}: map the visible handwritten Applicant Number box.`
          );
        } else if (pageBoxes.length > 1) {
          issues.push(
            `Page ${pageNo}: multiple handwritten Applicant Number boxes are mapped; identity is ambiguous.`
          );
        }
        for (const box of pageBoxes) {
          const x = Number(box.x_mm);
          const y = Number(box.y_mm);
          const width = Number(box.w_mm);
          const height = Number(box.h_mm);
          if (!Number.isFinite(x) || !Number.isFinite(y) || !Number.isFinite(width) || !Number.isFinite(height) || x < 0 || y < 0 || width <= 0 || height <= 0) {
            issues.push(
              `Page ${pageNo}: set valid positive dimensions for the handwritten Applicant Number box.`
            );
          } else if (x + width > 210 || y + height > 297) {
            issues.push(
              `Page ${pageNo}: handwritten Applicant Number box extends beyond the page.`
            );
          }
        }
      }
    } else if (regions.length > 0) {
      for (let pageNo = 1; pageNo <= input.pageCount; pageNo++) {
        const pageRegions = regions.filter(
          (region) => Number(region.page_no) === pageNo
        );
        if (pageRegions.length === 0) {
          issues.push(
            `Page ${pageNo}: map the visible write-in Applicant Number region.`
          );
        } else if (pageRegions.length > 1) {
          issues.push(
            `Page ${pageNo}: multiple write-in Applicant Number regions are mapped; identity is ambiguous.`
          );
        }
        for (const region of pageRegions) {
          const x = Number(region.x);
          const y = Number(region.y);
          const width = Number(region.w);
          const height = Number(region.h);
          if (!Number.isFinite(x) || !Number.isFinite(y) || !Number.isFinite(width) || !Number.isFinite(height) || x < 0 || y < 0 || width <= 0 || height <= 0) {
            issues.push(
              `Page ${pageNo}: enter a visible write-in region with non-negative position and positive size in recognition pixels.`
            );
          } else if (x + width > (input.pageWidthPx ?? mmToPx(210)) || y + height > (input.pageHeightPx ?? mmToPx(297))) {
            issues.push(
              `Page ${pageNo}: write-in Applicant Number region extends beyond the reference page.`
            );
          }
        }
      }
    } else {
      issues.push(
        "Handwritten Applicant Number box: map at least one review box on the canvas."
      );
    }
  }
  return { ready: issues.length === 0, issues };
}
function normalizeOmrAreaCode(raw) {
  const cleaned = String(raw ?? "").trim().toUpperCase().replace(/[^A-Z0-9_]+/g, "_").replace(/^_+|_+$/g, "");
  if (!cleaned) return "GENERAL";
  return /^[A-Z]/.test(cleaned) ? cleaned : `AREA_${cleaned}`;
}
function nextItemNumberForArea(sections, areaCode) {
  const targetArea = normalizeOmrAreaCode(areaCode);
  let maxQ = 0;
  for (const sec of sections) {
    if (!sec || sec.type === "roll" || sec.identity_grid) continue;
    const secArea = normalizeOmrAreaCode(sec.area_code);
    if (secArea !== targetArea) continue;
    const start = Math.max(1, Number(sec.startQ) || 1);
    const count = Math.max(1, Number(sec.questionsCount) || 1);
    maxQ = Math.max(maxQ, start + count - 1);
  }
  return maxQ + 1;
}
function buildSolidItemFromOptionPoints(input) {
  const pageNo = Math.max(1, Math.round(Number(input.pageNo) || 1));
  const areaCode = normalizeOmrAreaCode(input.areaCode);
  const itemNumber = Math.max(1, Math.round(Number(input.itemNumber) || 1));
  const labels = Array.isArray(input.optionLabels) && input.optionLabels.length >= 2 ? input.optionLabels.map((l) => String(l).trim()).filter(Boolean) : ["A", "B", "C", "D"];
  const count = labels.length;
  const minBubble = input.minBubbleMm ?? MIN_BUBBLE_MM;
  const requestedDiameter = Number(input.bubbleDiameterMm) || OMR_BUBBLE_DIAMETER_MM;
  const clampedDiameter = Number(
    Math.max(minBubble, Math.min(9, requestedDiameter)).toFixed(2)
  );
  const optionPointsMm = labels.map((label, idx) => {
    const pt = input.pointsMm[idx] ?? input.pointsMm[input.pointsMm.length - 1] ?? {
      x: 30 + idx * 6,
      y: 60
    };
    return {
      label,
      x_mm: Number(Math.max(2, Math.min(208, Number(pt.x) || 30)).toFixed(2)),
      y_mm: Number(Math.max(2, Math.min(295, Number(pt.y) || 60)).toFixed(2))
    };
  });
  const firstPt = optionPointsMm[0];
  const lastPt = optionPointsMm[optionPointsMm.length - 1];
  const avgY = optionPointsMm.reduce((acc, pt) => acc + pt.y_mm, 0) / Math.max(1, optionPointsMm.length);
  const rawSpanX = Math.abs(lastPt.x_mm - firstPt.x_mm);
  const rawPitchMm = count > 1 && rawSpanX > 1 ? rawSpanX / (count - 1) : 6;
  const effective = effectiveRecognitionGeometryMm({
    kind: "grid",
    bubbleDiameterMm: clampedDiameter,
    bubblePitchMm: Number(rawPitchMm.toFixed(2)),
    rowPitchMm: 6.8,
    minBubbleMm: minBubble
  });
  const xPitchPx = mmToPx(effective.bubblePitchMm);
  const minCenterX = Math.min(firstPt.x_mm, lastPt.x_mm);
  const maxCenterX = Math.max(firstPt.x_mm, lastPt.x_mm);
  const firstTopLeftXPx = (minCenterX - effective.bubbleDiameterMm / 2) * PX_PER_MM;
  const lastTopLeftXPx = (maxCenterX - effective.bubbleDiameterMm / 2) * PX_PER_MM;
  const centeredOriginXPx = count > 1 ? Math.round(
    (firstTopLeftXPx + lastTopLeftXPx - (count - 1) * xPitchPx) / 2
  ) : Math.round(firstTopLeftXPx);
  const originYPx = Math.round(
    (avgY - effective.bubbleDiameterMm / 2) * PX_PER_MM
  );
  const posX = Number(
    Math.max(
      0,
      centeredOriginXPx / PX_PER_MM - GRID_BUBBLE_ORIGIN_OFFSET_MM.x
    ).toFixed(2)
  );
  const posY = Number(
    Math.max(
      0,
      originYPx / PX_PER_MM - GRID_BUBBLE_ORIGIN_OFFSET_MM.y
    ).toFixed(2)
  );
  const bubblePitchMm = pxToMm(xPitchPx);
  const bubbleDiameterMm = Number(effective.bubbleDiameterMm.toFixed(2));
  const w_mm = Number(
    (bubbleDiameterMm + Math.max(0, count - 1) * bubblePitchMm).toFixed(2)
  );
  const h_mm = Number(bubbleDiameterMm.toFixed(2));
  const id = input.id || `${areaCode}_P${pageNo}_Q${itemNumber}`;
  return {
    id,
    name: `${areaCode} · Q${itemNumber}`,
    type: "mcq",
    page_no: pageNo,
    area_code: areaCode,
    aptitudeAreaCode: areaCode,
    startQ: itemNumber,
    questionsCount: 1,
    optionsCount: count,
    customValuesStr: labels.join(", "),
    posX,
    posY,
    w_mm,
    h_mm,
    bubbleDiameterMm,
    bubblePitchMm,
    rowPitchMm: 6.8,
    cols: 1,
    isLocked: false,
    optionPointsMm
  };
}
function detectAdjacentVerticalStepMm(sections, targetArea, pageNo = 1) {
  const normArea = normalizeOmrAreaCode(targetArea);
  const areaItems = (sections || []).filter(
    (s) => s && s.type !== "roll" && Number(s.page_no || 1) === Number(pageNo || 1) && normalizeOmrAreaCode(String(s.area_code || s.aptitudeAreaCode || "")) === normArea
  ).map((s) => ({
    itemNum: Number(s.startQ) || 1,
    y: Number(s.posY) || 0
  })).sort((a, b) => a.itemNum - b.itemNum);
  if (areaItems.length >= 2) {
    for (let i = 0; i < areaItems.length - 1; i++) {
      if (areaItems[i + 1].itemNum === areaItems[i].itemNum + 1) {
        const diff = Number((areaItems[i + 1].y - areaItems[i].y).toFixed(2));
        if (diff > 1 && diff < 60) {
          return diff;
        }
      }
    }
  }
  return null;
}
function multiplyItemsDownward(source, options, existingSections = []) {
  const count = Math.max(1, Math.min(100, Math.round(Number(options.count) || 1)));
  const distancePerItemMm = Number(options.distancePerItemMm) || Number(source.rowPitchMm) || 6.8;
  const pageNo = Number(options.pageNo ?? source.page_no) || 1;
  const targetArea = normalizeOmrAreaCode(options.areaCode ?? source.area_code);
  const labels = (source.customValuesStr || "A, B, C, D").split(",").map((s) => s.trim()).filter(Boolean);
  const sourcePoints = Array.isArray(source.optionPointsMm) && source.optionPointsMm.length === labels.length ? source.optionPointsMm.map((pt) => ({ x: pt.x_mm, y: pt.y_mm })) : labels.map((_, idx) => ({
    x: Number(source.posX) + GRID_BUBBLE_ORIGIN_OFFSET_MM.x + Number(source.bubbleDiameterMm) / 2 + idx * Number(source.bubblePitchMm),
    y: Number(source.posY) + GRID_BUBBLE_ORIGIN_OFFSET_MM.y + Number(source.bubbleDiameterMm) / 2
  }));
  const result = [];
  const combined = [...existingSections];
  for (let k = 1; k <= count; k++) {
    const nextQ = options.startItemNumber != null ? options.startItemNumber + (k - 1) : nextItemNumberForArea(combined, targetArea);
    const newId = `${targetArea}_P${pageNo}_Q${nextQ}_${existingSections.length + k}`;
    const isHorizontal = options.direction === "horizontal";
    const slantDeg = Number(options.slantAngleDeg) || 0;
    const slantRad = slantDeg * Math.PI / 180;
    const cosSlant = Math.cos(slantRad);
    const sinSlant = Math.sin(slantRad);
    const stepDX = isHorizontal ? distancePerItemMm * cosSlant : distancePerItemMm * sinSlant;
    const stepDY = isHorizontal ? distancePerItemMm * sinSlant : distancePerItemMm * cosSlant;
    const anchor0 = sourcePoints[0] ?? {
      x: Number(source.posX),
      y: Number(source.posY)
    };
    const newAnchor = {
      x: anchor0.x + k * stepDX,
      y: anchor0.y + k * stepDY
    };
    const translatedPoints = sourcePoints.map((pt) => {
      const relX = pt.x - anchor0.x;
      const relY = pt.y - anchor0.y;
      const rotX = relX * cosSlant - relY * sinSlant;
      const rotY = relX * sinSlant + relY * cosSlant;
      return {
        x: Number((newAnchor.x + rotX).toFixed(2)),
        y: Number((newAnchor.y + rotY).toFixed(2))
      };
    });
    const newItem = buildSolidItemFromOptionPoints({
      id: newId,
      pageNo,
      areaCode: targetArea,
      itemNumber: nextQ,
      optionLabels: labels,
      pointsMm: translatedPoints,
      bubbleDiameterMm: source.bubbleDiameterMm
    });
    if (isHorizontal) {
      newItem.bubblePitchMm = distancePerItemMm;
    } else {
      newItem.rowPitchMm = distancePerItemMm;
    }
    if (source.subtest_code) {
      newItem.subtest_code = source.subtest_code;
    }
    if (source.test_instrument_subtest_id != null) {
      newItem.test_instrument_subtest_id = source.test_instrument_subtest_id;
    }
    result.push(newItem);
    combined.push(newItem);
  }
  return result;
}
function syncDeclaredInventoryFromSections(sections, existingInventory = []) {
  const grouped = /* @__PURE__ */ new Map();
  for (const sec of sections) {
    if (!sec || sec.type === "roll" || sec.identity_grid) continue;
    const pageNo = Math.max(1, Number(sec.page_no) || 1);
    const areaCode = normalizeOmrAreaCode(sec.area_code);
    const qCount = Math.max(1, Number(sec.questionsCount) || 1);
    const explicitOptionLabels = [
      sec.optionLabels,
      sec.bubble_values,
      sec.option_labels
    ].find(
      (labels) => Array.isArray(labels) && labels.length >= 2 && labels.length <= 10 && labels.every((label) => typeof label === "string")
    );
    const optCount = Number(sec.optionsCount) === 5 ? 5 : 4;
    const optLabels = explicitOptionLabels ?? (optCount === 5 ? ["A", "B", "C", "D", "E"] : ["A", "B", "C", "D"]);
    const key = `${pageNo}:${areaCode}`;
    const prev = grouped.get(key);
    if (prev) {
      prev.question_count += qCount;
    } else {
      grouped.set(key, {
        page_no: pageNo,
        area_code: areaCode,
        question_count: qCount,
        option_labels: optLabels,
        geometry_id: String(sec.id ?? "")
      });
    }
  }
  if (grouped.size === 0) {
    return existingInventory;
  }
  const result = [];
  let idx = 0;
  for (const [, entry] of grouped) {
    idx += 1;
    const existingMatch = existingInventory.find(
      (item) => Number(item?.page_no) === entry.page_no && normalizeOmrAreaCode(item?.area_code) === entry.area_code
    );
    result.push({
      id: existingMatch?.id || `inventory-${entry.page_no}-${idx}`,
      page_no: entry.page_no,
      area_code: entry.area_code,
      question_count: entry.question_count,
      option_labels: entry.option_labels,
      geometry_id: entry.geometry_id
    });
  }
  return result;
}
export {
  GRID_BUBBLE_ORIGIN_OFFSET_MM as G,
  MIN_UPLOADED_BUBBLE_MM as M,
  OMR_BUBBLE_DIAMETER_MM as O,
  PX_PER_MM as P,
  emitNativeMapping as a,
  emitOverlayBoxes as b,
  contentBlockSizeMm as c,
  effectiveRecognitionPitchMm as d,
  effectiveRecognitionGeometryMm as e,
  bubbleLabelLayout as f,
  multiplyItemsDownward as g,
  mmToPx as h,
  verticalRollBubblePositionsPx as i,
  MIN_BUBBLE_MM as j,
  assessUploadedMappingReadiness as k,
  detectAdjacentVerticalStepMm as l,
  minimumRecognitionPitchMm as m,
  nextItemNumberForArea as n,
  normalizeOmrAreaCode as o,
  pxToMm as p,
  recognitionOriginOffsetMm as r,
  syncDeclaredInventoryFromSections as s,
  validateStudioLayout as v
};
