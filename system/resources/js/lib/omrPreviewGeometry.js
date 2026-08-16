export const DEFAULT_BUBBLE_DIMENSIONS = [56, 56];
export const OMR_PPI = 242;

export function bubbleDimensionsOf(dimensions = DEFAULT_BUBBLE_DIMENSIONS) {
  const width = Number(dimensions?.[0] ?? DEFAULT_BUBBLE_DIMENSIONS[0]);
  const height = Number(dimensions?.[1] ?? DEFAULT_BUBBLE_DIMENSIONS[1]);

  return [width > 0 ? width : DEFAULT_BUBBLE_DIMENSIONS[0], height > 0 ? height : DEFAULT_BUBBLE_DIMENSIONS[1]];
}

export function blockValues(block) {
  if (Array.isArray(block?.bubbleValues) && block.bubbleValues.length > 0) {
    return block.bubbleValues.map(String);
  }

  return block?.fieldType === 'QTYPE_INT'
    ? ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']
    : block?.fieldType === 'QTYPE_MCQ5'
      ? ['A', 'B', 'C', 'D', 'E']
      : ['A', 'B', 'C', 'D'];
}

export function isVerticalBlock(block) {
  return block?.fieldType === 'QTYPE_INT' || (block?.direction ?? 'vertical') === 'vertical';
}

export function blockBounds(block, dimensions = DEFAULT_BUBBLE_DIMENSIONS) {
  const [bubbleWidth, bubbleHeight] = bubbleDimensionsOf(dimensions);
  const values = blockValues(block);
  const vertical = isVerticalBlock(block);
  const columns = vertical ? Math.max(1, Number(block?.fieldType === 'QTYPE_INT' ? (block?.birthdate ? 6 : 10) : block?.questions ?? 0)) : values.length;
  const rows = vertical ? values.length : Math.max(1, Number(block?.questions ?? 0));
  const xGap = vertical ? Number(block?.labelsGap ?? 0) : Number(block?.bubblesGap ?? 0);
  const yGap = vertical ? Number(block?.bubblesGap ?? 0) : Number(block?.labelsGap ?? 0);

  return {
    w: Math.max(0, columns - 1) * xGap + bubbleWidth,
    h: Math.max(0, rows - 1) * yGap + bubbleHeight,
  };
}

export function bubbleCenter(block, row, column, dimensions = DEFAULT_BUBBLE_DIMENSIONS) {
  const [bubbleWidth, bubbleHeight] = bubbleDimensionsOf(dimensions);
  const vertical = isVerticalBlock(block);
  const xGap = vertical ? Number(block?.labelsGap ?? 0) : Number(block?.bubblesGap ?? 0);
  const yGap = vertical ? Number(block?.bubblesGap ?? 0) : Number(block?.labelsGap ?? 0);
  const origin = block?.origin ?? [0, 0];

  return {
    x: Number(origin[0]) + column * xGap + bubbleWidth / 2,
    y: Number(origin[1]) + row * yGap + bubbleHeight / 2,
  };
}

export function mmToPx(mm) {
  return Number(mm) * OMR_PPI / 25.4;
}

export function ptToPx(points) {
  return Number(points) * OMR_PPI / 72;
}

export const OMR_LABEL_FONT_PT = 9;
export const OMR_LABEL_GAP_PX = 8;
export const OMR_LABEL_WIDTH_MM = 6;
export const OMR_LEFT_LABEL_FONT_PT = 8;
export const OMR_LEFT_LABEL_WIDTH_MM = 5;

export function labelTop(originY) {
  return Number(originY) - ptToPx(OMR_LABEL_FONT_PT) - OMR_LABEL_GAP_PX;
}

export function labelBaseline(originY) {
  return labelTop(originY) + ptToPx(OMR_LABEL_FONT_PT) * 0.82;
}

export function visualBlockBounds(block, dimensions = DEFAULT_BUBBLE_DIMENSIONS) {
  const [bubbleWidth, bubbleHeight] = bubbleDimensionsOf(dimensions);
  const bubble = blockBounds(block, dimensions);
  const vertical = isVerticalBlock(block);
  const values = blockValues(block);
  const columns = vertical
    ? Math.max(1, Number(block?.fieldType === 'QTYPE_INT' ? (block?.birthdate ? 6 : 10) : block?.questions ?? 0))
    : values.length;
  const rows = vertical ? values.length : Math.max(1, Number(block?.questions ?? 0));
  const origin = block?.origin ?? [0, 0];
  const xGap = vertical ? Number(block?.labelsGap ?? 0) : Number(block?.bubblesGap ?? 0);
  const yGap = vertical ? Number(block?.bubblesGap ?? 0) : Number(block?.labelsGap ?? 0);
  const topLabelWidth = mmToPx(OMR_LABEL_WIDTH_MM);
  const leftLabelWidth = mmToPx(OMR_LEFT_LABEL_WIDTH_MM);
  const leftLabelHeight = ptToPx(OMR_LEFT_LABEL_FONT_PT);
  const firstCenterX = Number(origin[0]) + bubbleWidth / 2;
  const lastCenterX = Number(origin[0]) + Math.max(0, columns - 1) * xGap + bubbleWidth / 2;
  const firstCenterY = Number(origin[1]) + bubbleHeight / 2;
  const lastCenterY = Number(origin[1]) + Math.max(0, rows - 1) * yGap + bubbleHeight / 2;
  const leftLabelRight = Number(origin[0]) - (vertical ? 3 : 3.5);
  const leftLabelBottom = lastCenterY + leftLabelHeight / 2;
  const left = Math.min(Number(origin[0]), firstCenterX - topLabelWidth / 2, leftLabelRight - leftLabelWidth);
  const top = Math.min(Number(origin[1]), labelTop(origin[1]));
  const right = Math.max(Number(origin[0]) + bubble.w, lastCenterX + topLabelWidth / 2);
  const bottom = Math.max(Number(origin[1]) + bubble.h, leftLabelBottom);

  return { x: left, y: top, w: right - left, h: bottom - top };
}
