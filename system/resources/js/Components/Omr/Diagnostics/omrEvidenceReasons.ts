const evidenceReasonMessages: Record<string, string> = {
    'missing-machine-evidence':
        'Answer recognition is unavailable for this capture',
    'missing-asset': 'Captured sheet image is unavailable',
    'missing-evidence-hash': 'Scan evidence signature is unavailable',
    'missing-geometry-hash': 'Answer-sheet geometry signature is unavailable',
    'missing-crop-bounds': 'Item crop coordinates are unavailable',
    'missing-page-dimensions': 'Page dimensions are unavailable',
};

export function presentOmrEvidenceReason(reason: string): {
    message: string;
    technicalDetail: string;
} {
    const normalizedReason = String(reason || 'unknown-evidence-reason');

    const hasFriendlyMessage = Object.prototype.hasOwnProperty.call(
        evidenceReasonMessages,
        normalizedReason,
    );

    return {
        message: hasFriendlyMessage
            ? evidenceReasonMessages[normalizedReason]
            : 'Evidence could not be verified',
        technicalDetail: normalizedReason,
    };
}
