export type AvatarDimensions = {
    cardWidth: number;
    cardHeight: number;
    spriteSize: number;
    gap: number;
};

export type RowSlotCapacity = {
    narrowWidth: number;
    wideWidth: number;
};

export function computeAvatarDimensions(viewportWidth: number): AvatarDimensions {
    if (viewportWidth >= 640) {
        return { cardWidth: 136, cardHeight: 176, spriteSize: 132, gap: 12 };
    }

    return { cardWidth: 118, cardHeight: 152, spriteSize: 116, gap: 12 };
}

export function computeRowSlotCapacity(
    containerWidth: number,
    avatarDimensions: Pick<AvatarDimensions, 'cardWidth' | 'gap'>,
): RowSlotCapacity {
    return {
        narrowWidth: Math.max(120, containerWidth - avatarDimensions.cardWidth - avatarDimensions.gap),
        wideWidth: containerWidth,
    };
}

export function isRowCleared(rowBottomFromBottom: number, avatarHeight: number, safetyGap = 8): boolean {
    return rowBottomFromBottom >= avatarHeight + safetyGap;
}

export function classifyRowZone(rowBottomFromBottom: number, avatarHeight: number, safetyGap = 8): 'narrow' | 'wide' {
    return isRowCleared(rowBottomFromBottom, avatarHeight, safetyGap) ? 'wide' : 'narrow';
}

export function calculateScrollFollow(scrollTop: number, scrollHeight: number, clientHeight: number, threshold = 24): boolean {
    return scrollHeight - (scrollTop + clientHeight) <= threshold;
}

export function computeScrollAnchorAdjustment(anchorOffsetBefore: number, anchorOffsetAfter: number): number {
    return anchorOffsetAfter - anchorOffsetBefore;
}
