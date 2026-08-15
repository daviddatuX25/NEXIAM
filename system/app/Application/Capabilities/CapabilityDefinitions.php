<?php

namespace App\Application\Capabilities;

use App\Application\Capabilities\Handlers\KnowledgeSourcePublishHandler;
use App\Application\Capabilities\Handlers\KnowledgeSourceRevokeHandler;
use App\Application\Capabilities\Handlers\KnowledgeSourcesListHandler;
use App\Application\Capabilities\Handlers\SetupReadinessReadHandler;
use App\Application\Capabilities\Handlers\Exams\ExamSessionAssignHandler;
use App\Application\Capabilities\Handlers\Exams\ExamSessionPublishHandler;
use App\Application\Capabilities\Handlers\Exams\ExamSessionReservationOpenHandler;
use App\Application\Capabilities\Handlers\Exams\ExamSessionShowHandler;
use App\Application\Capabilities\Handlers\Exams\ExamSessionsListHandler;
use App\Application\Capabilities\Handlers\Exams\ExamSlotReserveHandler;
use App\Application\Capabilities\Handlers\Exams\ExamSlotsListHandler;
use App\Authorization\CapabilityCatalog;

/**
 * Code-owned capability catalog for the Kernel (OpenSpec 2+4).
 */
final class CapabilityDefinitions
{
    /**
     * @return list<CapabilityDefinition>
     */
    public static function all(): array
    {
        return [
            new CapabilityDefinition(
                key: 'setup.readiness.read',
                version: 1,
                kind: CapabilityKind::Read,
                applicationCapabilities: [
                    CapabilityCatalog::REGISTRAR_SETUP_MANAGE,
                    CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                    CapabilityCatalog::PROGRAM_QUOTA_MANAGE,
                ],
                authorizationMode: 'any',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [],
                ],
                handlerClass: SetupReadinessReadHandler::class,
                channels: ['http_page', 'nexi_safe'],
                ruleSetRefs: ['setup.readiness.catalog@1'],
                publicationBinding: 'setup.readiness.catalog@1',
                description: 'Institutional setup readiness categories (create-then-activate checks).',
            ),
            new CapabilityDefinition(
                key: 'knowledge.sources.list',
                version: 1,
                kind: CapabilityKind::Read,
                applicationCapabilities: [
                    CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE,
                    CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE,
                ],
                authorizationMode: 'any',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'search' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                    ],
                ],
                handlerClass: KnowledgeSourcesListHandler::class,
                channels: ['http_page', 'nexi_safe'],
                ruleSetRefs: ['knowledge.sources.catalog@1'],
                publicationBinding: 'knowledge.sources.catalog@1',
                description: 'Staff knowledge source catalog (latest version status).',
            ),
            new CapabilityDefinition(
                key: 'knowledge.source.publish',
                version: 1,
                kind: CapabilityKind::Command,
                applicationCapabilities: [
                    CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE,
                ],
                authorizationMode: 'all',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['version_id'],
                    'properties' => [
                        'version_id' => ['type' => 'string'],
                    ],
                ],
                handlerClass: KnowledgeSourcePublishHandler::class,
                channels: ['http_page'],
                ruleSetRefs: ['knowledge.lifecycle@1'],
                publicationBinding: 'knowledge.lifecycle@1',
                description: 'Publish approved knowledge version into active corpus via fake index.',
            ),
            new CapabilityDefinition(
                key: 'knowledge.source.revoke',
                version: 1,
                kind: CapabilityKind::Command,
                applicationCapabilities: [
                    CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE,
                ],
                authorizationMode: 'all',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['version_id'],
                    'properties' => [
                        'version_id' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                    ],
                ],
                handlerClass: KnowledgeSourceRevokeHandler::class,
                channels: ['http_page'],
                ruleSetRefs: ['knowledge.lifecycle@1'],
                publicationBinding: 'knowledge.lifecycle@1',
                description: 'Revoke knowledge version with synchronous retrieval denial.',
            ),
            new CapabilityDefinition(
                key: 'exams.sessions.list',
                version: 1,
                kind: CapabilityKind::Read,
                applicationCapabilities: CapabilityCatalog::examSessionManagementCapabilities(),
                authorizationMode: 'any',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'search' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'academic_cycle_id' => ['type' => 'integer'],
                    ],
                ],
                handlerClass: ExamSessionsListHandler::class,
                channels: ['http_page', 'nexi_safe'],
                ruleSetRefs: ['exams.sessions.catalog@1'],
                publicationBinding: 'exams.sessions.catalog@1',
                description: 'List exam sessions (same projection family as /exams/sessions index).',
            ),
            new CapabilityDefinition(
                key: 'exams.session.show',
                version: 1,
                kind: CapabilityKind::Read,
                applicationCapabilities: CapabilityCatalog::examSessionManagementCapabilities(),
                authorizationMode: 'any',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['session_id'],
                    'properties' => [
                        'session_id' => ['type' => 'integer'],
                    ],
                ],
                handlerClass: ExamSessionShowHandler::class,
                channels: ['http_page', 'nexi_safe'],
                ruleSetRefs: ['exams.sessions.catalog@1'],
                publicationBinding: 'exams.sessions.catalog@1',
                description: 'Show one exam session roster projection.',
            ),
            new CapabilityDefinition(
                key: 'exams.slots.list',
                version: 1,
                kind: CapabilityKind::Read,
                applicationCapabilities: [CapabilityCatalog::APPLICANT_EXAM_SLOTS_VIEW],
                authorizationMode: 'any',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'day' => ['type' => 'string'],
                    ],
                ],
                handlerClass: ExamSlotsListHandler::class,
                channels: ['nexi_safe'],
                ruleSetRefs: ['exams.slots.catalog@1'],
                publicationBinding: 'exams.slots.catalog@1',
                description: 'List open exam slots for the applicant own accepted attempt (Slice 9).',
            ),
            new CapabilityDefinition(
                key: 'exams.slot.reserve',
                version: 1,
                kind: CapabilityKind::Propose,
                applicationCapabilities: [CapabilityCatalog::APPLICANT_EXAM_SLOT_RESERVE],
                authorizationMode: 'any',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['exam_session_id', 'admission_attempt_id'],
                    'properties' => [
                        'exam_session_id' => ['type' => 'integer'],
                        'admission_attempt_id' => ['type' => 'integer'],
                        'execute' => ['type' => 'boolean'],
                        'idempotency_key' => ['type' => 'string'],
                    ],
                ],
                handlerClass: ExamSlotReserveHandler::class,
                channels: ['nexi_safe'],
                ruleSetRefs: ['exams.slot.reserve@1'],
                publicationBinding: 'exams.slot.reserve@1',
                description: 'Propose/execute ReserveExamSlot for the applicant own accepted attempt (Slice 9).',
            ),
            new CapabilityDefinition(
                key: 'exams.session.reservation.open',
                version: 1,
                kind: CapabilityKind::Command,
                applicationCapabilities: CapabilityCatalog::examSessionManagementCapabilities(),
                authorizationMode: 'all',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['session_id', 'expected_version'],
                    'properties' => [
                        'session_id' => ['type' => 'integer'],
                        'expected_version' => ['type' => 'integer'],
                        'execute' => ['type' => 'boolean'],
                        'reason' => ['type' => 'string'],
                    ],
                ],
                handlerClass: ExamSessionReservationOpenHandler::class,
                channels: ['http_page', 'nexi_safe'],
                ruleSetRefs: ['exams.session.reservation@1'],
                publicationBinding: 'exams.session.reservation@1',
                description: 'Propose/execute OpenExamSessionReservation (page-identical Action).',
            ),
            new CapabilityDefinition(
                key: 'exams.session.assign',
                version: 1,
                kind: CapabilityKind::Command,
                applicationCapabilities: CapabilityCatalog::examSessionManagementCapabilities(),
                authorizationMode: 'all',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['session_id', 'admission_attempt_id'],
                    'properties' => [
                        'session_id' => ['type' => 'integer'],
                        'admission_attempt_id' => ['type' => 'integer'],
                        'execute' => ['type' => 'boolean'],
                    ],
                ],
                handlerClass: ExamSessionAssignHandler::class,
                channels: ['http_page', 'nexi_safe'],
                ruleSetRefs: ['exams.session.assign@1'],
                publicationBinding: 'exams.session.assign@1',
                description: 'Propose/execute AssignApplicantToExamSession.',
            ),
            new CapabilityDefinition(
                key: 'exams.session.publish',
                version: 1,
                kind: CapabilityKind::Command,
                applicationCapabilities: CapabilityCatalog::examSessionManagementCapabilities(),
                authorizationMode: 'all',
                inputSchema: [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['session_id', 'expected_version'],
                    'properties' => [
                        'session_id' => ['type' => 'integer'],
                        'expected_version' => ['type' => 'integer'],
                        'execute' => ['type' => 'boolean'],
                    ],
                ],
                handlerClass: ExamSessionPublishHandler::class,
                channels: ['http_page', 'nexi_safe'],
                ruleSetRefs: ['exams.session.publish@1'],
                publicationBinding: 'exams.session.publish@1',
                description: 'Propose/execute PublishExamSession.',
            ),
        ];
    }
}
