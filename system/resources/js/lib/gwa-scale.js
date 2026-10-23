export const GWA_SCALES = [
    {
        value: 'percentage_100',
        label: '0–100 (100 best)',
        shortLabel: '0–100',
        min: 70,
        max: 100,
        step: '0.01',
        placeholder: 'e.g. 91.40',
        suffix: '/ 100',
    },
    {
        value: 'ph_1_highest',
        label: '1–5 (1.00 best)',
        shortLabel: '1.00 best',
        min: 1,
        max: 5,
        step: '0.01',
        placeholder: 'e.g. 1.25',
        suffix: '/ 5.00',
    },
    {
        value: 'gpa_4_highest',
        label: '1–4 (4.00 best)',
        shortLabel: '4.00 best',
        min: 1,
        max: 4,
        step: '0.01',
        placeholder: 'e.g. 3.65',
        suffix: '/ 4.00',
    },
    {
        value: 'gpa_5_highest',
        label: '1–5 (5.00 best)',
        shortLabel: '5.00 best',
        min: 1,
        max: 5,
        step: '0.01',
        placeholder: 'e.g. 4.75',
        suffix: '/ 5.00',
    },
];

export function getGwaScaleConfig(scale) {
    return GWA_SCALES.find((item) => item.value === scale) ?? GWA_SCALES[0];
}

function fromPhOneHighest(raw) {
    if (raw <= 3.0) {
        return Math.round((98.0 - ((raw - 1.0) / 2.0) * 23.0) * 100) / 100;
    }
    return Math.round((75.0 - ((raw - 3.0) / 2.0) * 15.0) * 100) / 100;
}

export function normalizeGwaValue(rawInput, scale = 'percentage_100') {
    if (rawInput === null || rawInput === undefined || String(rawInput).trim() === '') {
        return null;
    }
    const raw = Number.parseFloat(String(rawInput));
    if (!Number.isFinite(raw)) {
        return null;
    }

    const config = getGwaScaleConfig(scale);
    if (raw < config.min || raw > config.max) {
        return null;
    }

    if (config.value === 'ph_1_highest') {
        return fromPhOneHighest(raw);
    }
    if (config.value === 'gpa_5_highest') {
        return fromPhOneHighest(6.0 - raw);
    }
    if (config.value === 'gpa_4_highest') {
        return Math.round((75.0 + ((raw - 1.0) / 3.0) * 23.0) * 100) / 100;
    }
    return Math.round(raw * 100) / 100;
}
