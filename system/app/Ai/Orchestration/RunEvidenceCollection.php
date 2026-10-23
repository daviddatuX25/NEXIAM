<?php

namespace App\Ai\Orchestration;

use App\Ai\Policy\InputMinimizer;

final class RunEvidenceCollection
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private static array $byRun = [];

    /** @param array<string, mixed> $citation */
    public static function addCitation(string $runId, array $citation): void
    {
        if ($runId === '') {
            return;
        }

        $citationId = (string) ($citation['citation_id'] ?? md5(json_encode($citation) ?: ''));
        self::$byRun[$runId][$citationId] = $citation;
    }

    /** @param array<string, mixed> $citation */
    private static function hasServerIdentity(array $citation): bool
    {
        foreach (['citation_id', 'source_id', 'version_id'] as $field) {
            if (! is_string($citation[$field] ?? null) || $citation[$field] === '') {
                return false;
            }
        }

        return true;
    }

    private static function safeText(InputMinimizer $minimizer, mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return str_replace(['<', '>'], ['&lt;', '&gt;'], $minimizer->minimize($value));
    }

    private static function minimizeValues(mixed $value, InputMinimizer $minimizer): mixed
    {
        if (is_string($value)) {
            return str_replace(['<', '>'], ['&lt;', '&gt;'], $minimizer->minimize($value));
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::minimizeValues($item, $minimizer);
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $projectedData */
    public static function addToolCitation(string $runId, string $capabilityId, array $projectedData): void
    {
        if ($runId === '') {
            return;
        }
        if (in_array($projectedData['page_state'] ?? null, ['unavailable', 'denied'], true)
            || in_array($projectedData['outcome'] ?? null, ['no_support', 'unavailable'], true)) {
            return;
        }

        $minimizer = new InputMinimizer;

        if ($capabilityId === 'knowledge.search@1') {
            foreach ($projectedData['citations'] ?? [] as $citation) {
                if (! is_array($citation)
                    || ! in_array($citation['source_class'] ?? null, ['public_faq', 'institution_policy'], true)
                    || ! self::hasServerIdentity($citation)) {
                    continue;
                }

                $title = self::safeText($minimizer, $citation['title'] ?? null);
                $excerpt = self::safeText($minimizer, $citation['excerpt'] ?? null);
                if ($title === '' || $excerpt === '') {
                    continue;
                }

                self::addCitation($runId, [
                    'citation_id' => $citation['citation_id'],
                    'source_class' => $citation['source_class'],
                    'source_id' => $citation['source_id'],
                    'version_id' => $citation['version_id'],
                    'title' => $title,
                    'excerpt' => $excerpt,
                    'score' => is_numeric($citation['score'] ?? null) ? (float) $citation['score'] : 0.0,
                    'locator' => is_string($citation['locator'] ?? null) ? $citation['locator'] : null,
                    'as_of' => is_string($citation['as_of'] ?? null) ? $citation['as_of'] : null,
                ]);
            }

            return;
        }

        $titles = [
            'setup.readiness.read@1' => 'Institutional Setup Readiness Check',
            'result_sheet.templates.list@1' => 'Result Sheet Templates Catalog',
            'results.release.overview@1' => 'Result Release Overview',
            'workspace.summary@1' => 'Live Staff Workspace Summary',
            'exams.sessions.today@1' => 'Today Exam Sessions Schedule',
            'exams.sessions.list@1' => 'Exam Sessions List',
            'exams.session.show@1' => 'Exam Session Roster Detail',
            'exams.slots.list@1' => 'Available Exam Slots',
            'admission.office_inquiries.options@1' => 'Office inquiry options',
        ];

        $safeData = self::minimizeValues($projectedData, $minimizer);
        $excerpt = substr(json_encode($safeData, JSON_UNESCAPED_SLASHES) ?: '{}', 0, 500);
        self::addCitation($runId, [
            'citation_id' => 'cite_tool_'.substr(hash('sha256', $capabilityId.$runId), 0, 16),
            'source_class' => $capabilityId === 'admission.office_inquiries.options@1'
                ? 'actor_owned_record'
                : 'staff_operational_fact',
            'version_id' => 'live_v1',
            'source_id' => $capabilityId,
            'title' => $titles[$capabilityId] ?? $capabilityId,
            'excerpt' => $excerpt,
            'score' => 1.0,
            'locator' => $capabilityId,
            'as_of' => now()->toIso8601String(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function forRun(string $runId): array
    {
        return array_values(self::$byRun[$runId] ?? []);
    }

    public static function clear(string $runId): void
    {
        unset(self::$byRun[$runId]);
    }
}
