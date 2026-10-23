import { describe, expect, it } from 'vitest';
import {
    calculateScrollFollow,
    classifyRowZone,
    computeAvatarDimensions,
    computeRowSlotCapacity,
    computeScrollAnchorAdjustment,
    isRowCleared,
} from '../../resources/js/lib/xiam-panel-layout';

describe('Xiam panel layout geometry', () => {
    it('uses desktop avatar dimensions at and above the breakpoint', () => {
        expect(computeAvatarDimensions(640)).toEqual({ cardWidth: 136, cardHeight: 176, spriteSize: 132, gap: 12 });
        expect(computeAvatarDimensions(1440)).toEqual({ cardWidth: 136, cardHeight: 176, spriteSize: 132, gap: 12 });
    });

    it('uses mobile avatar dimensions below the breakpoint', () => {
        expect(computeAvatarDimensions(639)).toEqual({ cardWidth: 118, cardHeight: 152, spriteSize: 116, gap: 12 });
        expect(computeAvatarDimensions(390)).toEqual({ cardWidth: 118, cardHeight: 152, spriteSize: 116, gap: 12 });
    });

    it('computes a mobile narrow slot beside the avatar and a full-width slot above it', () => {
        const capacity = computeRowSlotCapacity(390, { cardWidth: 118, gap: 12 });

        expect(capacity.narrowWidth).toBe(260);
        expect(capacity.narrowWidth).toBeGreaterThanOrEqual(210);
        expect(capacity.narrowWidth).toBeLessThanOrEqual(260);
        expect(capacity.wideWidth).toBe(390);
    });

    it('computes desktop slot capacity and applies its minimum narrow width', () => {
        expect(computeRowSlotCapacity(500, { cardWidth: 136, gap: 12 })).toEqual({ narrowWidth: 352, wideWidth: 500 });
        expect(computeRowSlotCapacity(100, { cardWidth: 136, gap: 12 })).toEqual({ narrowWidth: 120, wideWidth: 100 });
    });

    it('clears a row at the avatar-height plus safety-gap boundary', () => {
        expect(isRowCleared(183, 176)).toBe(false);
        expect(isRowCleared(184, 176)).toBe(true);
        expect(classifyRowZone(183, 176)).toBe('narrow');
        expect(classifyRowZone(184, 176)).toBe('wide');
    });

    it('honors explicit safety gaps in clearance checks and classification', () => {
        expect(isRowCleared(151, 140, 12)).toBe(false);
        expect(isRowCleared(152, 140, 12)).toBe(true);
        expect(classifyRowZone(151, 140, 12)).toBe('narrow');
        expect(classifyRowZone(152, 140, 12)).toBe('wide');
        expect(isRowCleared(148, 140, 0)).toBe(true);
    });

    it('follows the end at bottom and within the default 24px threshold', () => {
        expect(calculateScrollFollow(400, 1000, 600)).toBe(true);
        expect(calculateScrollFollow(376, 1000, 600)).toBe(true);
        expect(calculateScrollFollow(375.9, 1000, 600)).toBe(false);
        expect(calculateScrollFollow(375, 1000, 600)).toBe(false);
    });

    it('handles negative and zero scroll positions using the same end-distance rule', () => {
        expect(calculateScrollFollow(-20, 1000, 600)).toBe(false);
        expect(calculateScrollFollow(0, 600, 600)).toBe(true);
        expect(calculateScrollFollow(0, 624, 600)).toBe(true);
        expect(calculateScrollFollow(0, 625, 600)).toBe(false);
    });

    it('returns anchor offset changes for scroll compensation', () => {
        expect(computeScrollAnchorAdjustment(120, 155)).toBe(35);
        expect(computeScrollAnchorAdjustment(155, 120)).toBe(-35);
        expect(computeScrollAnchorAdjustment(120, 120)).toBe(0);
    });
});
