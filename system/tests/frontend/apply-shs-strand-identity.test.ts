// @vitest-environment jsdom
import { createHash } from 'node:crypto';
import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import ApplicantStrandSelect from '../../resources/js/Components/Admission/ApplicantStrandSelect.svelte';
import { buildShsStrandOptions } from '../../resources/js/lib/shs-strand-options';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

afterEach(() => cleanup());

describe('Apply SHS option identity', () => {
    it('renders both current STEM catalog rows while retaining their shared admission value', () => {
        const catalog = JSON.parse(
            readFileSync(resolve(process.cwd(), 'database/data/application-suggestions.json'), 'utf8'),
        );
        const records = catalog.lists.shs.filter((record) => record.aliases.includes('STEM'));
        const suggestions = records.map((record) => {
            const suffix = record.category === 'legacy-shs-strand'
                ? ' (legacy SHS strand)'
                : ' (Strengthened SHS elective cluster)';
            const label = `${record.label}${suffix}`;

            return {
                key: createHash('sha256').update(label.toLowerCase()).digest('hex'),
                label,
                aliases: record.aliases,
            };
        });
        const options = buildShsStrandOptions(suggestions);

        expect(records).toHaveLength(2);
        expect(options.map((option) => option.key).filter((key, index, all) => all.indexOf(key) === index)).toHaveLength(2);
        expect(options.map((option) => option.code)).toEqual(['STEM', 'STEM']);

        render(ApplicantStrandSelect, { props: { options, value: '' } });

        expect(screen.getAllByRole('option')).toHaveLength(3);
        expect(screen.getByRole('option', { name: /legacy SHS strand/ })).toBeTruthy();
        expect(screen.getByRole('option', { name: /Strengthened SHS elective cluster/ })).toBeTruthy();
    });
});
