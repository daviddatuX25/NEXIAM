<?php

namespace App\Ai\Context;

use App\Ai\Surface\SurfaceProfileKey;
use LogicException;

/**
 * Build-time route-name → pack map. Duplicate route registrations fail closed.
 */
final class PageCapabilityPackRegistry
{
    /** @var array<string, PageCapabilityPack> */
    private array $byRoute = [];

    /** @var array<string, PageCapabilityPack> */
    private array $byKey = [];

    public function __construct()
    {
        foreach ($this->definitions() as $pack) {
            $this->register($pack);
        }
    }

    public function forRoute(?string $routeName): ?PageCapabilityPack
    {
        if ($routeName === null) {
            return null;
        }

        return $this->byRoute[$routeName] ?? null;
    }

    public function forKey(string $key): ?PageCapabilityPack
    {
        return $this->byKey[$key] ?? null;
    }

    /**
     * @return list<PageCapabilityPack>
     */
    public function all(): array
    {
        return array_values($this->byKey);
    }

    private function register(PageCapabilityPack $pack): void
    {
        if (isset($this->byRoute[$pack->pageIdentity])) {
            throw new LogicException("Duplicate PageCapabilityPack route registration: {$pack->pageIdentity}");
        }

        if (isset($this->byKey[$pack->key])) {
            throw new LogicException("Duplicate PageCapabilityPack key registration: {$pack->key}");
        }

        $this->byRoute[$pack->pageIdentity] = $pack;
        $this->byKey[$pack->key] = $pack;
    }

    /**
     * First foundation packs only (OpenSpec 1).
     *
     * @return list<PageCapabilityPack>
     */
    private function definitions(): array
    {
        return [
            new PageCapabilityPack(
                key: 'staff.workspace.home@1',
                version: 1,
                pageIdentity: 'staff.home',
                allowedModes: ['inquiry'],
                defaultMode: 'inquiry',
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'setup.index', 'label' => 'Open Setup readiness'],
                ],
            ),
            new PageCapabilityPack(
                key: 'staff.setup.readiness@1',
                version: 1,
                pageIdentity: 'setup.index',
                allowedModes: ['inquiry'],
                defaultMode: 'inquiry',
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'setup.index', 'label' => 'Stay on Setup'],
                ],
            ),
            new PageCapabilityPack(
                key: 'applicant.schedule@1',
                version: 1,
                pageIdentity: 'applicant.schedule',
                allowedModes: ['inquiry', 'assistant'],
                defaultMode: 'assistant',
                privacyClass: 'applicant_own_minimized',
                surface: SurfaceProfileKey::Applicant,
                capabilities: [
                    'exams.slots.list@1',
                    'exams.slot.reserve@1',
                ],
                skills: ['applicant.choose_slot@1'],
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'applicant.schedule', 'label' => 'Open schedule'],
                    ['type' => 'skill_start', 'skill_key' => 'applicant.choose_slot@1', 'label' => 'Start slot choice skill'],
                ],
            ),
            new PageCapabilityPack(
                key: 'staff.knowledge.sources@1',
                version: 1,
                pageIdentity: 'knowledge.index',
                allowedModes: ['inquiry'],
                defaultMode: 'inquiry',
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'knowledge.index', 'label' => 'Open Knowledge'],
                ],
            ),
            new PageCapabilityPack(
                key: 'staff.exams.sessions.index@1',
                version: 1,
                pageIdentity: 'exams.sessions.index',
                allowedModes: ['inquiry', 'assistant'],
                defaultMode: 'inquiry',
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                capabilities: [
                    'exams.sessions.list@1',
                    'exams.session.show@1',
                    'exams.session.reservation.open@1',
                    'exams.session.assign@1',
                    'exams.session.publish@1',
                ],
                skills: ['schedule.exam_session@1'],
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'exams.sessions.index', 'label' => 'Exam sessions'],
                    ['type' => 'skill_start', 'skill_key' => 'schedule.exam_session@1', 'label' => 'Start exam scheduling skill'],
                ],
            ),
            new PageCapabilityPack(
                key: 'staff.exams.sessions.show@1',
                version: 1,
                pageIdentity: 'exams.sessions.show',
                allowedModes: ['inquiry', 'assistant'],
                defaultMode: 'inquiry',
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                capabilities: [
                    'exams.sessions.list@1',
                    'exams.session.show@1',
                    'exams.session.reservation.open@1',
                    'exams.session.assign@1',
                    'exams.session.publish@1',
                ],
                skills: ['schedule.exam_session@1'],
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'exams.sessions.show', 'label' => 'Stay on session'],
                    ['type' => 'skill_start', 'skill_key' => 'schedule.exam_session@1', 'label' => 'Start exam scheduling skill'],
                ],
            ),
        ];
    }
}
