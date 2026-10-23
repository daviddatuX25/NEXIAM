<?php

namespace App\Scheduling\Queries;

use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;

final class SessionCodeSuggestions
{
    /**
     * @param  list<int>  $cycleIds
     * @return array<int, string>
     */
    public function forCycles(string $domain, array $cycleIds): array
    {
        $model = match ($domain) {
            'exam' => ExamSession::class,
            'release' => ConsultationSession::class,
            default => throw new \InvalidArgumentException('Unsupported session-code domain.'),
        };
        $prefix = $domain === 'exam' ? 'Batch-' : 'Release-';
        $suggestions = [];

        foreach ($cycleIds as $cycleId) {
            $suggestions[$cycleId] = $prefix.'01';
        }

        if ($cycleIds === []) {
            return $suggestions;
        }

        $highestByCycle = array_fill_keys($cycleIds, '0');
        $pattern = '/^'.preg_quote($prefix, '/').'([0-9]+)$/D';
        $codes = $model::query()
            ->whereIn('academic_cycle_id', $cycleIds)
            ->where('code', 'like', $prefix.'%')
            ->select(['academic_cycle_id', 'code'])
            ->toBase()
            ->cursor();

        foreach ($codes as $session) {
            if (preg_match($pattern, $session->code, $matches) !== 1) {
                continue;
            }

            $digits = ltrim($matches[1], '0');
            $highest = $highestByCycle[$session->academic_cycle_id];
            if (strlen($digits) > strlen($highest)
                || (strlen($digits) === strlen($highest) && strcmp($digits, $highest) > 0)) {
                $highestByCycle[$session->academic_cycle_id] = $digits;
            }
        }

        foreach ($highestByCycle as $cycleId => $highest) {
            $suggestions[$cycleId] = $prefix.str_pad(str_increment($highest), 2, '0', STR_PAD_LEFT);
        }

        return $suggestions;
    }
}
