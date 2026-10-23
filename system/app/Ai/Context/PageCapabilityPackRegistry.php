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

        if (str_starts_with($routeName, 'knowledge.')) {
            return $this->byRoute['knowledge.index'] ?? null;
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
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                capabilities: [
                    'workspace.summary@1',
                    'exams.sessions.today@1',
                    'setup.readiness.read@1',
                    'result_sheet.templates.list@1',
                    'results.release.overview@1',
                    'knowledge.search@1',
                    'consultation.sessions.list@1',
                    'exams.rooms.available@1',
                    'foundation.academic_cycles.list@1',
                    'consultation.session.create@1',
                    'consultation.session.reservation.open@1',
                ],
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'setup.index', 'label' => 'Open Setup readiness'],
                ],
            ),
            new PageCapabilityPack(
                key: 'staff.consultation.sessions.index@1',
                version: 1,
                pageIdentity: 'consultation.sessions.index',
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                capabilities: [
                    'consultation.sessions.list@1',
                    'exams.rooms.available@1',
                    'foundation.academic_cycles.list@1',
                    'consultation.session.create@1',
                    'consultation.session.reservation.open@1',
                    'knowledge.search@1',
                ],
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'consultation.sessions.index', 'label' => 'Release Day Scheduling'],
                ],
            ),
            new PageCapabilityPack(
                key: 'staff.setup.readiness@1',
                version: 1,
                pageIdentity: 'setup.index',
                privacyClass: 'staff_operational_minimized',
                capabilities: [
                    'setup.readiness.read@1',
                    'workspace.summary@1',
                    'result_sheet.templates.list@1',
                    'results.release.overview@1',
                    'knowledge.search@1',
                    'setup.rooms.create_draft@1',
                    'setup.rooms.update@1',
                    'setup.cycles.create_draft@1',
                    'setup.cycles.activate@1',
                    'setup.quotas.upsert@1',
                    'setup.notifications.update_admission@1',
                    'setup.notifications.update_exam@1',
                    'setup.windows.store@1',
                ],
                surface: SurfaceProfileKey::Staff,
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'setup.index', 'label' => 'Stay on Setup'],
                ],
            ),
            new PageCapabilityPack(
                key: 'applicant.schedule@1',
                version: 1,
                pageIdentity: 'applicant.schedule',
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
                key: 'applicant.portal@1',
                version: 1,
                pageIdentity: 'applicant.portal',
                privacyClass: 'applicant_own_minimized',
                surface: SurfaceProfileKey::Applicant,
                capabilities: [
                    'admission.office_inquiries.options@1',
                    'admission.office_inquiries.open@1',
                    'admission.office_inquiries.reply@1',
                ],
            ),
            new PageCapabilityPack(
                key: 'applicant.inquiries.index@1',
                version: 1,
                pageIdentity: 'applicant.inquiries.index',
                privacyClass: 'applicant_own_minimized',
                surface: SurfaceProfileKey::Applicant,
                capabilities: [
                    'admission.office_inquiries.options@1',
                    'admission.office_inquiries.open@1',
                    'admission.office_inquiries.reply@1',
                ],
            ),
            new PageCapabilityPack(
                key: 'applicant.inquiries.show@1',
                version: 1,
                pageIdentity: 'applicant.inquiries.show',
                privacyClass: 'applicant_own_minimized',
                surface: SurfaceProfileKey::Applicant,
                capabilities: [
                    'admission.office_inquiries.options@1',
                    'admission.office_inquiries.open@1',
                    'admission.office_inquiries.reply@1',
                ],
            ),
            new PageCapabilityPack(
                key: 'staff.knowledge.sources@1',
                version: 1,
                pageIdentity: 'knowledge.index',
                privacyClass: 'staff_operational_minimized',
                capabilities: [
                    'knowledge.search@1',
                ],
                surface: SurfaceProfileKey::Staff,
                uiIntents: [
                    ['type' => 'navigate', 'route_key' => 'knowledge.index', 'label' => 'Open Knowledge'],
                ],
            ),
            new PageCapabilityPack(
                key: 'staff.exams.sessions.index@1',
                version: 1,
                pageIdentity: 'exams.sessions.index',
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                capabilities: [
                    'exams.sessions.list@1',
                    'exams.session.show@1',
                    'exams.session.create@1',
                    'exams.rooms.available@1',
                    'foundation.academic_cycles.list@1',
                    'scheduling.candidate_pool.list@1',
                    'exams.session.reservation.open@1',
                    'exams.session.assign@1',
                    'exams.session.publish@1',
                    'setup.notifications.update_exam@1',
                    'knowledge.search@1',
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
                privacyClass: 'staff_operational_minimized',
                surface: SurfaceProfileKey::Staff,
                capabilities: [
                    'exams.sessions.list@1',
                    'exams.session.show@1',
                    'exams.session.create@1',
                    'exams.rooms.available@1',
                    'foundation.academic_cycles.list@1',
                    'scheduling.candidate_pool.list@1',
                    'exams.session.reservation.open@1',
                    'exams.session.assign@1',
                    'exams.session.publish@1',
                    'knowledge.search@1',
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
