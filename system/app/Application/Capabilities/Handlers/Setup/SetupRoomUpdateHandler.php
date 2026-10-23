<?php

namespace App\Application\Capabilities\Handlers\Setup;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Foundation\Actions\UpdateRoom;
use App\Foundation\Support\RoomUpdateEvaluator;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class SetupRoomUpdateHandler
{
    private const CAPABILITY_ID = 'setup.rooms.update@1';

    public function __construct(
        private readonly UpdateRoom $action,
        private readonly RoomUpdateEvaluator $evaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $roomId = $input['room_id'] ?? null;
        $newCapacity = $input['capacity'] ?? null;
        $rawSpaceKind = $input['space_kind'] ?? null;
        $newIsExamRoom = $input['is_exam_room'] ?? null;
        if ($rawSpaceKind !== null && $rawSpaceKind !== '') {
            if (! is_string($rawSpaceKind) || SpaceKind::tryFrom($rawSpaceKind) === null) {
                throw new RuntimeException('space_kind_invalid');
            }
            $targetSpaceKind = SpaceKind::from($rawSpaceKind);
            $newIsExamRoom = ($targetSpaceKind === SpaceKind::Room);
        } else {
            $targetSpaceKind = null;
        }
        if (! is_int($roomId) || $roomId < 1) {
            throw new RuntimeException('room_id_invalid');
        }
        if (! is_int($newCapacity) || $newCapacity < 1 || $newCapacity > 500) {
            throw new RuntimeException('capacity_invalid');
        }
        if (! is_bool($newIsExamRoom)) {
            throw new RuntimeException('is_exam_room_invalid');
        }

        $room = Room::query()->find($roomId);
        if (! $room instanceof Room) {
            throw new RuntimeException('room_not_found');
        }

        $hasLatKey = array_key_exists('latitude', $input);
        $hasLonKey = array_key_exists('longitude', $input);
        $rawLat = $input['latitude'] ?? null;
        $rawLon = $input['longitude'] ?? null;
        $hasLat = $rawLat !== null && $rawLat !== '';
        $hasLon = $rawLon !== null && $rawLon !== '';

        $coordinatePairValid = true;
        $coordinateBoundsValid = true;
        $coordinateError = null;
        $lat = null;
        $lon = null;

        if ($hasLatKey || $hasLonKey || $hasLat || $hasLon) {
            if ($hasLatKey && $hasLonKey && $rawLat === null && $rawLon === null) {
                $lat = null;
                $lon = null;
            } elseif (! ($hasLat && $hasLon)) {
                $coordinatePairValid = false;
                $coordinateError = 'Both latitude and longitude are required when providing coordinates.';
            } elseif (! (is_numeric($rawLat) && is_numeric($rawLon))) {
                $coordinateBoundsValid = false;
                $coordinateError = 'Coordinates must be valid numbers.';
            } else {
                $lat = (float) $rawLat;
                $lon = (float) $rawLon;
                if ($lat < -90.0 || $lat > 90.0) {
                    $coordinateBoundsValid = false;
                    $coordinateError = "Latitude {$lat} is out of valid range [-90, +90].";
                } elseif ($lon < -180.0 || $lon > 180.0) {
                    $coordinateBoundsValid = false;
                    $coordinateError = "Longitude {$lon} is out of valid range [-180, +180].";
                }
            }
        }

        $hasBuildingKey = array_key_exists('building_name', $input);
        $hasFloorKey = array_key_exists('floor_level', $input);
        $hasDirectionsKey = array_key_exists('directions_hint', $input);

        $buildingName = isset($input['building_name']) && is_string($input['building_name']) && trim($input['building_name']) !== '' ? trim($input['building_name']) : null;
        if ($buildingName !== null && mb_strlen($buildingName) > 100) {
            throw new RuntimeException('building_name_invalid');
        }
        $floorLevel = isset($input['floor_level']) && (is_string($input['floor_level']) || is_numeric($input['floor_level'])) && trim((string) $input['floor_level']) !== '' ? trim((string) $input['floor_level']) : null;
        if ($floorLevel !== null && mb_strlen($floorLevel) > 32) {
            throw new RuntimeException('floor_level_invalid');
        }
        $directionsHint = isset($input['directions_hint']) && is_string($input['directions_hint']) && trim($input['directions_hint']) !== '' ? trim($input['directions_hint']) : null;
        if ($directionsHint !== null && mb_strlen($directionsHint) > 2000) {
            throw new RuntimeException('directions_hint_invalid');
        }
        $evaluation = $this->evaluator->evaluate($room, $newCapacity, $newIsExamRoom);
        $checks = $evaluation->checks;
        if ($hasLatKey || $hasLonKey || $hasLat || $hasLon) {
            $checks[] = [
                'key' => 'room_coordinates_valid',
                'label' => 'Geographic coordinates',
                'status' => ($coordinatePairValid && $coordinateBoundsValid) ? 'pass' : 'fail',
                'detail' => ($coordinatePairValid && $coordinateBoundsValid)
                    ? ($lat !== null && $lon !== null ? "Coordinates: {$lat}, {$lon}" : 'Coordinates will be cleared')
                    : $coordinateError,
            ];
        }
        $outcome = ($evaluation->outcome === 'pass' && $coordinatePairValid && $coordinateBoundsValid) ? 'pass' : 'fail';

        if (! (bool) ($input['execute'] ?? false)) {
            $command = [
                'room_id' => (int) $room->id,
                'capacity' => (int) $newCapacity,
                'is_exam_room' => (bool) $newIsExamRoom,
            ];
            $coverage = [
                'room_id' => ['classification' => 'display', 'field' => 'room'],
                'capacity' => ['classification' => 'display', 'field' => 'capacity'],
                'is_exam_room' => ['classification' => 'display', 'field' => 'is_exam_room'],
            ];
            if ($rawSpaceKind !== null && $targetSpaceKind !== null) {
                $command['space_kind'] = $targetSpaceKind->value;
                $coverage['space_kind'] = ['classification' => 'display', 'field' => 'space_kind'];
            }
            $roomRecord = [
                'type' => 'record',
                'label' => (string) $room->name,
                'context' => [(string) $room->code],
            ];
            $fields = [
                ProposalViewContract::field('room', 'Room', $roomRecord, $roomRecord, 'context'),
                ProposalViewContract::field(
                    'capacity',
                    'Seating capacity',
                    ['type' => 'integer', 'value' => (int) $room->capacity, 'semantic' => 'count', 'unit' => 'seats'],
                    ['type' => 'integer', 'value' => (int) $newCapacity, 'semantic' => 'count', 'unit' => 'seats'],
                    'choice',
                ),
                ProposalViewContract::field(
                    'is_exam_room',
                    'Exam room suitability',
                    ['type' => 'boolean', 'value' => (bool) $room->isExamRoom(), 'true_label' => 'Suitable for exams', 'false_label' => 'Non-exam venue'],
                    ['type' => 'boolean', 'value' => (bool) $newIsExamRoom, 'true_label' => 'Suitable for exams', 'false_label' => 'Non-exam venue'],
                    'choice',
                ),
            ];
            if ($rawSpaceKind !== null && $targetSpaceKind !== null) {
                $fields[] = ProposalViewContract::field(
                    'space_kind',
                    'Space classification',
                    ['type' => 'text', 'value' => $room->space_kind?->label() ?? 'Exam / Testing Room', 'multiline' => false],
                    ['type' => 'text', 'value' => $targetSpaceKind->label(), 'multiline' => false],
                    'choice',
                );
            }

            if ($hasLatKey || $hasLonKey || $hasLat || $hasLon) {
                $command['latitude'] = $hasLat ? $rawLat : ($hasLatKey ? null : null);
                $command['longitude'] = $hasLon ? $rawLon : ($hasLonKey ? null : null);
                $coverage['latitude'] = ['classification' => 'display', 'field' => 'coordinates'];
                $coverage['longitude'] = ['classification' => 'display', 'field' => 'coordinates'];
                $beforeCoord = ($room->latitude !== null && $room->longitude !== null)
                    ? ['type' => 'text', 'value' => "{$room->latitude}, {$room->longitude}", 'multiline' => false]
                    : ['type' => 'null'];
                $afterCoord = ($coordinatePairValid && $coordinateBoundsValid && $lat !== null && $lon !== null)
                    ? ['type' => 'text', 'value' => "{$lat}, {$lon}", 'multiline' => false]
                    : (($coordinatePairValid && $coordinateBoundsValid && $lat === null && $lon === null)
                        ? ['type' => 'null']
                        : ['type' => 'text', 'value' => 'invalid', 'multiline' => false]);
                $fields[] = ProposalViewContract::field('coordinates', 'Coordinates', $beforeCoord, $afterCoord, 'choice');
            }
            if ($hasBuildingKey || $hasFloorKey || $buildingName !== null || $floorLevel !== null) {
                if ($hasBuildingKey || $buildingName !== null) {
                    $command['building_name'] = $buildingName;
                    $coverage['building_name'] = ['classification' => 'display', 'field' => 'building'];
                }
                if ($hasFloorKey || $floorLevel !== null) {
                    $command['floor_level'] = $floorLevel;
                    $coverage['floor_level'] = ['classification' => 'display', 'field' => 'building'];
                }
                $beforeBuildingText = match (true) {
                    $room->building_name !== null && $room->floor_level !== null => "{$room->building_name} (Floor {$room->floor_level})",
                    $room->building_name !== null => $room->building_name,
                    $room->floor_level !== null => "Floor {$room->floor_level}",
                    default => null,
                };
                $beforeBuilding = $beforeBuildingText !== null
                    ? ['type' => 'text', 'value' => $beforeBuildingText, 'multiline' => false]
                    : ['type' => 'null'];

                $effectiveBuilding = $hasBuildingKey ? $buildingName : $room->building_name;
                $effectiveFloor = $hasFloorKey ? $floorLevel : $room->floor_level;
                $afterBuildingText = match (true) {
                    $effectiveBuilding !== null && $effectiveFloor !== null => "{$effectiveBuilding} (Floor {$effectiveFloor})",
                    $effectiveBuilding !== null => $effectiveBuilding,
                    $effectiveFloor !== null => "Floor {$effectiveFloor}",
                    default => null,
                };
                $afterBuilding = $afterBuildingText !== null
                    ? ['type' => 'text', 'value' => $afterBuildingText, 'multiline' => false]
                    : ['type' => 'null'];

                $fields[] = ProposalViewContract::field('building', 'Building', $beforeBuilding, $afterBuilding, 'choice');
            }
            if ($hasDirectionsKey || $directionsHint !== null) {
                $command['directions_hint'] = $directionsHint;
                $coverage['directions_hint'] = ['classification' => 'internal', 'reason' => 'Spatial wayfinding direction hint stored for navigation'];
            }

            $result = [
                'mode' => 'propose',
                'ok' => $outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => $command,
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'update',
                    'title' => 'Update room capacity',
                    'sections' => [
                        ProposalViewContract::fieldsSection('room_update', 'Room configuration', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight($outcome, $checks),
                    'coverage' => $coverage,
                ],
                'diff' => $evaluation->diff,
                'evidence' => ['outcome' => $outcome, 'checks' => $checks],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }
        if ($outcome !== 'pass') {
            return ['mode' => 'denied', 'reason' => 'room_update_preflight_failed', 'evidence' => ['checks' => $checks]];
        }

        $spaceKind = $targetSpaceKind ?? ($newIsExamRoom
            ? SpaceKind::Room
            : ($room->allowsSeatingArrangement() ? SpaceKind::AssemblyVenue : $room->space_kind));
        $updateAttributes = [
            'name' => (string) $room->name,
            'capacity' => $newCapacity,
            'is_active' => (bool) $room->is_active,
            'space_kind' => $spaceKind ?? SpaceKind::Room,
        ];
        if ($hasLatKey && $hasLonKey) {
            $updateAttributes['latitude'] = $lat;
            $updateAttributes['longitude'] = $lon;
        } elseif ($lat !== null && $lon !== null) {
            $updateAttributes['latitude'] = $lat;
            $updateAttributes['longitude'] = $lon;
        }
        if ($hasBuildingKey) {
            $updateAttributes['building_name'] = $buildingName;
        } elseif ($buildingName !== null) {
            $updateAttributes['building_name'] = $buildingName;
        }
        if ($hasFloorKey) {
            $updateAttributes['floor_level'] = $floorLevel;
        } elseif ($floorLevel !== null) {
            $updateAttributes['floor_level'] = $floorLevel;
        }
        if ($hasDirectionsKey) {
            $updateAttributes['directions_hint'] = $directionsHint;
        } elseif ($directionsHint !== null) {
            $updateAttributes['directions_hint'] = $directionsHint;
        }
        $updated = $this->action->handle($room, $updateAttributes, $actor);

        return [
            'mode' => 'execute',
            'ok' => true,
            'room_id' => (int) $updated->id,
            'capacity' => (int) $updated->capacity,
            'is_exam_room' => $updated->isExamRoom(),
        ];
    }
}
