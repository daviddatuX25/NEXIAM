<?php

namespace App\Assessment\Queries;

use App\Assessment\Omr\Queries\BookletContractQuery;
use App\Assessment\Support\KitCompositionContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

final class TestKitCompositionQuery
{
    public function __construct(
        private readonly KitCompositionContext $contexts,
        private readonly BookletContractQuery $bookletContract,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /** @return array<string, mixed> */
    public function forPreparation(User $actor, TestKit $kit, TestKitPreparation $preparation): array
    {
        $this->assertAccess($actor, $kit);
        if ((int) $preparation->test_kit_id !== (int) $kit->id
            || $preparation->state !== TestKitPreparation::STATE_PREPARING) {
            throw new RuntimeException('The composition editor requires the current open preparation for this Kit.');
        }

        $resolved = $this->contexts->resolvePreparation($preparation);
        $profileApproved = $resolved['profile'] instanceof TestInstrumentVersion
            && $resolved['profile']->status === TestInstrumentVersion::STATUS_APPROVED;
        $requirements = $profileApproved ? ($resolved['requirements'] ?? null) : null;
        $applicableResources = $profileApproved ? ($resolved['applicable_resources'] ?? null) : null;
        $omrApplicable = $profileApproved ? ($resolved['omr_applicable'] ?? null) : null;
        $mapApplicable = $profileApproved ? ($resolved['map_applicable'] ?? null) : null;
        $groups = $mapApplicable === true ? $this->groups($resolved) : [];
        $context = [
            'preparation_id' => (int) $preparation->id,
            'preparation_revision' => (int) $preparation->revision,
            'composition_revision' => (int) $preparation->composition_revision,
            'component_vector_sha256' => (string) $resolved['component_vector_sha256'],
        ];
        $previewUrl = $mapApplicable !== true || $resolved['template_version_id'] === null
            ? null
            : route('setup.exam-materials.test-kits.preparation.composition.preview', [
                'testKit' => (int) $kit->id,
                ...$context,
            ], absolute: false);

        return [
            'preparation_id' => (int) $preparation->id,
            'state' => $profileApproved ? $resolved['state'] : 'incomplete',
            'revision' => (int) $preparation->revision,
            'requirements' => $requirements,
            'required_resources' => is_array($requirements) ? $requirements['required_resources'] : null,
            'applicable_resources' => $applicableResources,
            'omr_applicable' => $omrApplicable,
            'attachment_state' => $resolved['attachment_state'] ?? 'absent',
            'map_applicable' => $mapApplicable,
            'map_complete' => $mapApplicable === true && ($resolved['map_complete'] ?? false),
            'groups' => $groups,
            'rows' => $mapApplicable === true ? $this->publicRows($resolved) : [],
            'nonphysical_practice_items' => $mapApplicable === true ? $this->nonphysicalPracticeItems($resolved) : [],
            'issues' => $profileApproved
                ? $resolved['issues']
                : [
                    ...$resolved['issues'],
                    [
                        'code' => 'approved_profile_required',
                        'message' => 'Applicability is shown only for an approved Test Setup.',
                    ],
                ],
            'matched' => $mapApplicable === true ? (int) $resolved['matched'] : null,
            'expected' => $mapApplicable === true ? (int) $resolved['expected'] : null,
            'context' => $context,
            'save_url' => route('setup.exam-materials.test-kits.preparation.composition.update', [
                'testKit' => (int) $kit->id,
            ], absolute: false),
            'preview_url' => $previewUrl,
        ];
    }

    /** @return array<string, mixed> */
    public function forVersion(User $actor, TestKit $kit, TestKitVersion $version): array
    {
        $this->assertAccess($actor, $kit);
        if ((int) $version->test_kit_id !== (int) $kit->id) {
            throw new RuntimeException('The selected sealed Kit version belongs to another Kit.');
        }

        try {
            $resolved = $this->contexts->resolveVersion($version);
        } catch (RuntimeException $exception) {
            return [
                'revision' => null,
                'preparation_revision' => null,
                'requirements' => null,
                'required_resources' => null,
                'applicable_resources' => null,
                'omr_applicable' => null,
                'attachment_state' => 'absent',
                'map_applicable' => null,
                'map_complete' => false,
                'component_vector' => [],
                'groups' => [],
                'rows' => [],
                'nonphysical_practice_items' => [],
                'issues' => [['code' => 'sealed_composition_unavailable', 'message' => $exception->getMessage()]],
                'matched' => null,
                'expected' => null,
                'context' => [
                    'test_kit_version_id' => (int) $version->id,
                    'composition_revision' => $version->composition_revision === null ? null : (int) $version->composition_revision,
                    'composition_sha256' => $version->composition_sha256,
                    'manifest_sha256' => (string) $version->manifest_sha256,
                ],
                'preview_url' => null,
            ];
        }

        $compositionRevision = $version->composition_revision === null
            ? null
            : (int) $version->composition_revision;
        $context = [
            'test_kit_version_id' => (int) $version->id,
            'composition_revision' => $compositionRevision,
            'composition_sha256' => $version->composition_sha256,
            'manifest_sha256' => (string) $version->manifest_sha256,
        ];
        $mapApplicable = $resolved['map_applicable'] ?? ($resolved['item_map'] !== null);
        $previewUrl = $mapApplicable
            ? route('setup.exam-materials.test-kits.version.composition.preview', [
                'testKit' => (int) $kit->id,
                'version' => (int) $version->id,
                ...$context,
            ], absolute: false)
            : null;

        return [
            'state' => 'ready',
            'revision' => $compositionRevision,
            'preparation_revision' => null,
            'requirements' => $resolved['requirements'] ?? null,
            'required_resources' => $resolved['requirements']['required_resources'] ?? null,
            'applicable_resources' => $resolved['applicable_resources'] ?? null,
            'omr_applicable' => $resolved['omr_applicable'] ?? null,
            'map_applicable' => $mapApplicable,
            'map_complete' => $resolved['map_complete'] ?? ($resolved['item_map'] !== null),
            'component_vector' => $resolved['component_vector'],
            'groups' => $this->groups($resolved),
            'rows' => $this->publicRows($resolved),
            'nonphysical_practice_items' => [],
            'issues' => $resolved['issues'] ?? [],
            'matched' => $mapApplicable === true ? (int) $resolved['matched'] : null,
            'expected' => $mapApplicable === true ? (int) $resolved['expected'] : null,
            'context' => $context,
            'preview_url' => $previewUrl,
        ];
    }

    /** @param array<string, mixed> $resolved @return list<array<string, mixed>> */
    private function groups(array $resolved): array
    {
        $inventory = $resolved['physical_inventory'];
        $physicalBySection = [];
        foreach ($inventory['groups'] as $group) {
            $physicalBySection[(string) $group['printed_section_uid']] = [
                'physical_section_uid' => (string) $group['printed_section_uid'],
                'label' => (string) $group['section_name'],
                'matched' => 0,
                'expected' => (int) $group['expected_item_count'],
                'physical_locations' => [],
                'booklet_sections' => [],
                'formal_subtests' => [],
            ];
        }
        foreach ($inventory['items'] as $location) {
            $sectionUid = (string) $location['printed_section_uid'];
            if (! isset($physicalBySection[$sectionUid])) {
                continue;
            }
            $physicalBySection[$sectionUid]['physical_locations'][] = [
                'physical_item_uid' => (string) $location['physical_item_uid'],
                'sheet_item_id' => (int) $location['sheet_item_id'],
                'page_no' => (int) $location['page_no'],
                'native_label' => (string) $location['native_label'],
                'option_tokens' => array_values($location['option_tokens']),
            ];
        }

        $booklet = $resolved['booklet'];
        if ($booklet !== null) {
            $contract = $this->bookletContract->forBooklet($booklet);
            $bookletItemsBySection = $booklet->items->groupBy('booklet_section_uid');
            $bookletSections = [];
            foreach ($contract['areas'] as $section) {
                $sectionUid = (string) ($section['uid'] ?? '');
                $items = [];
                foreach ($bookletItemsBySection->get($sectionUid, collect())->sortBy([['authored_position', 'asc'], ['id', 'asc']]) as $item) {
                    $items[] = [
                        'booklet_item_id' => (int) $item->id,
                        'booklet_item_uid' => (string) $item->booklet_item_uid,
                        'label' => sprintf('%s %d', (string) $section['name'], (int) $item->authored_position),
                        'option_tokens' => array_values((array) $item->option_tokens),
                    ];
                }
                $bookletSections[] = [
                    'booklet_section_uid' => $sectionUid,
                    'label' => (string) $section['name'],
                    'items' => $items,
                ];
            }
            foreach ($physicalBySection as $sectionUid => $_group) {
                $physicalBySection[$sectionUid]['booklet_sections'] = $bookletSections;
            }
        }

        $profile = $resolved['profile'];
        if ($profile !== null) {
            $formalSubtests = [];
            foreach ($profile->subtests->sortBy([['display_order', 'asc'], ['id', 'asc']]) as $subtest) {
                $items = [];
                foreach ($subtest->items->sortBy([['display_order', 'asc'], ['id', 'asc']]) as $item) {
                    $items[] = [
                        'formal_item_id' => (int) $item->id,
                        'label' => (string) $item->item_code,
                        'option_tokens' => array_values((array) $item->option_tokens),
                        'is_scored' => (bool) $item->is_scored,
                    ];
                }
                $formalSubtests[] = [
                    'formal_subtest_id' => (int) $subtest->id,
                    'label' => (string) $subtest->name,
                    'items' => $items,
                ];
            }
            foreach ($physicalBySection as $sectionUid => $_group) {
                $physicalBySection[$sectionUid]['formal_subtests'] = $formalSubtests;
            }
        }

        $mappedSectionByUid = [];
        foreach ($resolved['rows'] as $row) {
            if (($row['disposition'] ?? null) !== 'physical') {
                continue;
            }
            $location = null;
            foreach ($inventory['items'] as $candidate) {
                if ((string) $candidate['physical_item_uid'] === (string) $row['physical_item_uid']) {
                    $location = $candidate;
                    break;
                }
            }
            if ($location === null) {
                continue;
            }
            $sectionUid = (string) $location['printed_section_uid'];
            if (isset($physicalBySection[$sectionUid])) {
                $physicalBySection[$sectionUid]['matched']++;
            }
            $mappedSectionByUid[(string) $row['physical_item_uid']] = true;
        }

        return array_values($physicalBySection);
    }

    /** @param array<string, mixed> $resolved @return list<array<string, mixed>> */
    private function publicRows(array $resolved): array
    {
        $physicalByUid = [];
        foreach ($resolved['physical_inventory']['items'] as $location) {
            $physicalByUid[(string) $location['physical_item_uid']] = $location;
        }

        return array_map(static function (array $row) use ($physicalByUid): array {
            $physicalUid = $row['physical_item_uid'] ?? null;
            $physical = is_string($physicalUid) ? ($physicalByUid[$physicalUid] ?? null) : null;

            return [
                'physical_item_uid' => $physicalUid,
                'booklet_item_id' => $row['booklet_item_id'] === null ? null : (int) $row['booklet_item_id'],
                'formal_subtest_id' => (int) ($row['test_instrument_subtest_id'] ?? $row['formal_subtest_id'] ?? 0),
                'formal_item_id' => (int) ($row['test_instrument_item_id'] ?? $row['formal_item_id'] ?? 0),
                'option_tokens' => $physical === null ? [] : array_values($physical['option_tokens']),
                'disposition' => (string) $row['disposition'],
            ];
        }, $resolved['rows']);
    }

    /** @param array<string, mixed> $resolved @return list<array<string, mixed>> */
    private function nonphysicalPracticeItems(array $resolved): array
    {
        $profile = $resolved['profile'];
        if ($profile === null) {
            return [];
        }

        $mapped = [];
        foreach ($resolved['rows'] as $row) {
            if (($row['disposition'] ?? null) === 'nonphysical_practice') {
                $mapped[(int) $row['test_instrument_item_id']] = true;
            }
        }
        $items = [];
        foreach ($profile->subtests as $subtest) {
            foreach ($subtest->items as $item) {
                if ((bool) $item->is_scored) {
                    continue;
                }
                $items[] = [
                    'formal_subtest_id' => (int) $subtest->id,
                    'subtest_label' => (string) $subtest->name,
                    'formal_item_id' => (int) $item->id,
                    'label' => (string) $item->item_code,
                    'option_tokens' => array_values((array) $item->option_tokens),
                    'is_scored' => false,
                    'disposition' => isset($mapped[(int) $item->id]) ? 'nonphysical_practice' : null,
                ];
            }
        }

        return $items;
    }

    private function assertAccess(User $actor, TestKit $kit): void
    {
        if (! $this->authorizer->decideFresh(
            $actor,
            CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
            ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
        )->allowed) {
            throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
        }
    }
}
