<?php

use App\Models\Assessment\GradingSession;
use App\ResultSheet\Queries\PrintJobHistoryQuery;

test('print job history query accepts an assessment grading session model without type error', function (): void {
    $session = GradingSession::query()->first();
    if ($session === null) {
        $session = new GradingSession;
        $session->id = 1;
    }

    $history = app(PrintJobHistoryQuery::class)->forSession($session);

    expect($history)->toBeArray();
});
