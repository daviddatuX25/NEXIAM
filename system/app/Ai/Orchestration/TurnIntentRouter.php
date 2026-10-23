<?php

declare(strict_types=1);

namespace App\Ai\Orchestration;

use App\Ai\Context\InvocationContext;

final class TurnIntentRouter
{
    private const POLICY_KEYWORDS = [
        'policy', 'policies', 'quota', 'ceiling', 'score', 'passing', 'cutoff', 'requirement',
        'appeal', 'rule', 'rules', 'retake', 'fee', 'waiver', 'deadline', 'faq', 'bsmath',
        'program', 'release', 'attestation', 'grade', 'result',
    ];

    private const AMBIENT_PHRASES = [
        'whats up today', 'what is up today', 'what can we work', 'what should we work',
        'work on today', 'on the agenda', 'whats on today', 'daily briefing',
        'workspace summary', 'pending today', 'sessions today', 'workload',
        'what do we have today', 'status today', 'overview for today', 'priorities today',
        'how many seats', 'seats left', 'seats are left', 'how many slots', 'slots left',
        'what about tomorrow', 'schedule tomorrow', 'check the schedule', 'check again',
        'sessions tomorrow', 'available tomorrow', 'any sessions tomorrow', 'remaining seats',
    ];

    private const CONVERSATIONAL_PHRASES = [
        'hello', 'hi', 'hey', 'hello there', 'hi there', 'good morning', 'good afternoon',
        'good evening', 'greetings', 'whats up', 'what is up', 'how are you',
        'hows it going', 'help', 'orient me', 'what can you do', 'who are you',
        'what are you', 'how can you help', 'kamusta', 'kumusta', 'magandang araw',
        'thanks', 'thank you',
    ];

    public function route(string $inputText, InvocationContext $context, ?string $turnMode = null): TurnPlan
    {
        $normalized = mb_strtolower(trim($inputText));
        $normalized = str_replace(["'", '’'], '', $normalized);
        $normalized = preg_replace('/[^\\p{L}\\p{N}]+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim(preg_replace('/\\s+/u', ' ', $normalized) ?? $normalized);

        if ($context->actorType === 'staff' && $this->isReadinessOrTemplateQuery($normalized)) {
            return new TurnPlan(
                TurnLane::AmbientBriefing,
                'workspace-briefing',
                ['setup.readiness.read@1', 'result_sheet.templates.list@1', 'results.release.overview@1', 'workspace.summary@1'],
                false,
            );
        }

        if ($this->containsPolicyKeyword($normalized)) {
            return new TurnPlan(TurnLane::PolicyInquiry, 'policy-inquiry', [], true);
        }

        if ($context->actorType === 'staff' && $this->containsPhrase($normalized, self::AMBIENT_PHRASES)) {
            return new TurnPlan(
                TurnLane::AmbientBriefing,
                'workspace-briefing',
                ['workspace.summary@1', 'exams.sessions.today@1'],
                false,
            );
        }

        if ($this->requestsSchedulingAction($normalized)) {
            return new TurnPlan(
                TurnLane::ActionProposal,
                $context->actorType === 'applicant' ? 'applicant-slot-booking' : 'exam-scheduling',
                [],
                false,
            );
        }

        if ($this->isConversational($normalized)) {
            return new TurnPlan(TurnLane::Conversational, 'conversation', [], false);
        }

        if ($turnMode === 'assistant') {
            return new TurnPlan(
                TurnLane::ActionProposal,
                $context->actorType === 'applicant' ? 'applicant-slot-booking' : 'exam-scheduling',
                [],
                false,
            );
        }

        return new TurnPlan(TurnLane::PolicyInquiry, 'policy-inquiry', [], true);
    }

    private function isReadinessOrTemplateQuery(string $text): bool
    {
        return $this->containsPhrase($text, [
            'ready for release',
            'ready to release',
            'release readiness',
            'what is blocking',
            'whats blocking',
            'whats holding us up',
            'what is holding us up',
            'result sheet',
            'result sheets',
            'result template',
            'result templates',
            'templates of result',
            'active template',
            'active templates',
            'how many templates',
        ]);
    }

    private function containsPolicyKeyword(string $text): bool
    {
        foreach (self::POLICY_KEYWORDS as $keyword) {
            if ($keyword === 'faq' || $keyword === 'bsmath') {
                if (str_contains($text, $keyword)) {
                    return true;
                }

                continue;
            }

            if ($keyword === 'requirement' || $keyword === 'eligib') {
                if (str_contains($text, $keyword === 'eligib' ? 'eligib' : $keyword)) {
                    return true;
                }

                continue;
            }

            if (preg_match('/\\b'.preg_quote($keyword, '/').'\\b/u', $text) === 1) {
                return true;
            }
        }

        return str_contains($text, 'eligib');
    }

    /** @param list<string> $phrases */
    private function containsPhrase(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function requestsSchedulingAction(string $text): bool
    {
        return preg_match('/\\b(reserve|book|assign|publish)\\b.{0,60}\\b(slot|session|the first one|that one|one)\\b/u', $text) === 1
            || preg_match('/\\b(slot|session)\\b.{0,60}\\b(reserve|book|assign|publish)\\b/u', $text) === 1
            || preg_match('/\\bopen\\s+reservations?\\b/u', $text) === 1
            || preg_match('/\\b(book|reserve)\\s+(that\\s+one|the\\s+first\\s+one|for\\s+me)\\b/u', $text) === 1;

    }

    private function isConversational(string $text): bool
    {
        return in_array($text, self::CONVERSATIONAL_PHRASES, true)
            || preg_match('/^(hello|hi|hey|greetings|kumusta|kamusta|good morning|good afternoon|good evening)\\b/u', $text) === 1;
    }
}
