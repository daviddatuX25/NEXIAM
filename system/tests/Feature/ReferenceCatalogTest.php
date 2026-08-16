<?php

use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;

test('reference catalogs seed programs rooms aptitude areas and windows', function (): void {
    seedE2Foundation();

    expect(Program::query()->active()->pluck('code')->all())->toEqualCanonicalizing(['BSN', 'BSPSY']);
    expect(Room::query()->active()->pluck('code')->all())->toEqualCanonicalizing(['RM-101', 'RM-LabA']);
    expect(AptitudeArea::query()->active()->pluck('code')->all())->toEqualCanonicalizing([
        'VERBAL', 'QUANT', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE',
    ]);
    expect(ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->value('status'))->toBe('published');
});
