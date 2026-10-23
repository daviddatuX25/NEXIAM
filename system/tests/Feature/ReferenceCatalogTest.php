<?php

use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;

test('reference catalogs seed programs rooms aptitude areas and windows', function (): void {
    seedE2Foundation();

    expect(Program::query()->active()->pluck('code')->all())->toContain('BSIT', 'BSBA', 'BPA', 'BSEd', 'BSN', 'BSPSY');
    expect(Room::query()->active()->pluck('code')->all())->toContain('ITBR-104', 'ITBR-105', 'CC-01', 'REG-WIN-1');
    expect(AptitudeArea::query()->active()->pluck('code')->all())->toEqualCanonicalizing([
        'VERBAL', 'QUANT', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE',
    ]);
    expect(AptitudeArea::query()->whereIn('code', ['LOGIC', 'VERBAL', 'QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE'])->pluck('name', 'code')->all())
        ->toMatchArray([
            'LOGIC' => 'General Ability',
            'VERBAL' => 'Verbal Aptitude',
            'QUANT' => 'Numerical Aptitude',
            'SPATIAL' => 'Spatial Aptitude',
            'MEMORY' => 'Perceptual Aptitude',
            'LANGUAGE' => 'Manual Dexterity',
        ]);
    expect(ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->value('status'))->toBe('published');
});
