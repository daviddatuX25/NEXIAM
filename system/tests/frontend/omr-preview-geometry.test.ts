import { describe, expect, it } from 'vitest';
import { blockBounds, bubbleCenter, labelBaseline, labelTop, ptToPx, visualBlockBounds } from '../../resources/js/lib/omrPreviewGeometry.js';

describe('OMR preview geometry', () => {
  it('uses native top-left origins and canonical bubble dimensions', () => {
    const block = {
      fieldType: 'QTYPE_MCQ4',
      direction: 'horizontal',
      questions: 25,
      bubblesGap: 66,
      labelsGap: 80,
      origin: [180, 900],
    };

    expect(blockBounds(block)).toEqual({ w: 254, h: 1976 });
    expect(bubbleCenter(block, 0, 0)).toEqual({ x: 208, y: 928 });
    expect(bubbleCenter(block, 24, 3)).toEqual({ x: 406, y: 2848 });
  });

  it('keeps six birthdate positions across and ten symbols down', () => {
    const block = {
      fieldType: 'QTYPE_INT',
      fieldLabels: ['BIRTHDATE_1..6'],
      bubblesGap: 60,
      labelsGap: 66,
      origin: [1000, 260],
      birthdate: true,
    };

    expect(blockBounds(block)).toEqual({ w: 386, h: 596 });
    expect(bubbleCenter(block, 9, 5)).toEqual({ x: 1358, y: 828 });
  });

  it('keeps printed column labels above the first bubble row', () => {
    const block = {
      fieldType: 'QTYPE_MCQ4',
      direction: 'horizontal',
      questions: 25,
      bubblesGap: 66,
      labelsGap: 80,
      origin: [180, 900],
    };

    expect(labelTop(block.origin[1]) + ptToPx(9)).toBeLessThan(block.origin[1]);
    expect(labelBaseline(block.origin[1])).toBeLessThan(block.origin[1]);
    expect(visualBlockBounds(block).y).toBe(labelTop(block.origin[1]));
    expect(visualBlockBounds(block).h).toBeGreaterThan(blockBounds(block).h);
  });
});
