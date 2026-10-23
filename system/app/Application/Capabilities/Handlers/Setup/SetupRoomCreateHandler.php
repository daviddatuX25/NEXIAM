<?php

namespace App\Application\Capabilities\Handlers\Setup;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Foundation\Actions\CreateRoom;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class SetupRoomCreateHandler
{
    private const CAPABILITY_ID = 'setup.rooms.create_draft@1';

    public function __construct(
        private readonly CreateRoom $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $code = $input['code'] ?? null;
        $name = $input['name'] ?? null;
        $capacity = $input['capacity'] ?? null;
        $rawSpaceKind = $input['space_kind'] ?? null;
        $isExamRoom = $input['is_exam_room'] ?? true;
        if ($rawSpaceKind !== null && $rawSpaceKind !== '') {
            if (! is_string($rawSpaceKind) || SpaceKind::tryFrom($rawSpaceKind) === null) {
                throw new RuntimeException('space_kind_invalid');
            }
            $targetSpaceKind = SpaceKind::from($rawSpaceKind);
            $isExamRoom = ($targetSpaceKind === SpaceKind::Room);
        } else {
            $targetSpaceKind = $isExamRoom ? SpaceKind::Room : SpaceKind::AssemblyVenue;
        }
        if (! is_string($code) || trim($code) === '' || mb_strlen(trim($code)) > 32) {
            throw new RuntimeException('room_code_invalid');
        }
        if (! is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 255) {
            throw new RuntimeException('room_name_invalid');
        }
        if (! is_int($capacity) || $capacity < 1 || $capacity > 500) {
            throw new RuntimeException('room_capacity_invalid');
        }
        if (! is_bool($isExamRoom)) {
            throw new RuntimeException('is_exam_room_invalid');
        }

        $rawLat = $input['latitude'] ?? null;
        $rawLon = $input['longitude'] ?? null;
        $hasLat = $rawLat !== null && $rawLat !== '';
        $hasLon = $rawLon !== null && $rawLon !== '';

        $coordinatePairValid = true;
        $coordinateBoundsValid = true;
        $coordinateError = null;
        $lat = null;
        $lon = null;

        if ($hasLat || $hasLon) {
            if (! ($hasLat && $hasLon)) {
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
        $roomAttributes = [
            'code' => $code,
            'name' => $name,
            'capacity' => $capacity,
            'space_kind' => $targetSpaceKind,
            'latitude' => $lat,
            'longitude' => $lon,
            'building_name' => $buildingName,
            'floor_level' => $floorLevel,
            'directions_hint' => $directionsHint,
        ];
        $normalizedCode = strtoupper(trim($code));
        $normalizedName = trim($name);

        if (! (bool) ($input['execute'] ?? false)) {
            $command = [
                'code' => $code,
                'name' => $name,
                'capacity' => (int) $capacity,
                'is_exam_room' => (bool) $isExamRoom,
            ];
            $coverage = [
                'code' => ['classification' => 'display', 'field' => 'code'],
                'name' => ['classification' => 'display', 'field' => 'name'],
                'capacity' => ['classification' => 'display', 'field' => 'capacity'],
                'is_exam_room' => ['classification' => 'display', 'field' => 'is_exam_room'],
            ];
            if ($rawSpaceKind !== null) {
                $command['space_kind'] = $targetSpaceKind->value;
                $coverage['space_kind'] = ['classification' => 'display', 'field' => 'space_kind'];
            }
            $fields = [
                ProposalViewContract::field('code', 'Room code', ['type' => 'missing'], ['type' => 'code', 'value' => $normalizedCode], 'choice'),
                ProposalViewContract::field('name', 'Room name', ['type' => 'missing'], ['type' => 'text', 'value' => $normalizedName, 'multiline' => false], 'choice'),
                ProposalViewContract::field(
                    'capacity',
                    'Seating capacity',
                    ['type' => 'missing'],
                    ['type' => 'integer', 'value' => (int) $capacity, 'semantic' => 'count', 'unit' => 'seats'],
                    'choice',
                ),
                ProposalViewContract::field(
                    'is_exam_room',
                    'Exam room suitability',
                    ['type' => 'missing'],
                    ['type' => 'boolean', 'value' => (bool) $isExamRoom, 'true_label' => 'Suitable for exams', 'false_label' => 'Non-exam venue'],
                    'choice',
                ),
            ];
            if ($rawSpaceKind !== null) {
                $fields[] = ProposalViewContract::field('space_kind', 'Space classification', ['type' => 'missing'], ['type' => 'text', 'value' => $targetSpaceKind->label(), 'multiline' => false], 'choice');
            }

            if ($hasLat || $hasLon) {
                $command['latitude'] = $hasLat ? $rawLat : null;
                $command['longitude'] = $hasLon ? $rawLon : null;
                $coverage['latitude'] = ['classification' => 'display', 'field' => 'coordinates'];
                $coverage['longitude'] = ['classification' => 'display', 'field' => 'coordinates'];
                $coordText = ($coordinatePairValid && $coordinateBoundsValid)
                    ? "{$lat}, {$lon}"
                    : 'invalid: '.($hasLat ? (string) $rawLat : 'missing').', '.($hasLon ? (string) $rawLon : 'missing');
                $fields[] = ProposalViewContract::field('coordinates', 'Coordinates', ['type' => 'missing'], ['type' => 'text', 'value' => $coordText, 'multiline' => false], 'choice');
            }
            if ($buildingName !== null || $floorLevel !== null) {
                if ($buildingName !== null) {
                    $command['building_name'] = $buildingName;
                    $coverage['building_name'] = ['classification' => 'display', 'field' => 'building'];
                }
                if ($floorLevel !== null) {
                    $command['floor_level'] = $floorLevel;
                    $coverage['floor_level'] = ['classification' => 'display', 'field' => 'building'];
                }
                $displayBuilding = match (true) {
                    $buildingName !== null && $floorLevel !== null => "{$buildingName} (Floor {$floorLevel})",
                    $buildingName !== null => $buildingName,
                    default => "Floor {$floorLevel}",
                };
                $fields[] = ProposalViewContract::field('building', 'Building', ['type' => 'missing'], ['type' => 'text', 'value' => $displayBuilding, 'multiline' => false], 'choice');
            }
            if ($directionsHint !== null) {
                $command['directions_hint'] = $directionsHint;
                $coverage['directions_hint'] = ['classification' => 'internal', 'reason' => 'Spatial wayfinding direction hint stored for navigation'];
            }

            $codeAvailable = ! Room::query()->where('code', $normalizedCode)->exists();
            $checks = [
                [
                    'key' => 'room_code_available',
                    'label' => 'Room code availability',
                    'status' => $codeAvailable ? 'pass' : 'fail',
                    'detail' => $codeAvailable ? "Room code {$normalizedCode} is available" : "Room code {$normalizedCode} already in use",
                ],
                [
                    'key' => 'room_capacity_valid',
                    'label' => 'Room capacity',
                    'status' => 'pass',
                    'detail' => "Capacity set to {$capacity} seats",
                ],
                [
                    'key' => 'exam_room_suitability',
                    'label' => 'Exam suitability',
                    'status' => 'pass',
                    'detail' => $isExamRoom ? 'Configured as suitable for exam sessions' : "Configured as {$targetSpaceKind->label()}",
                ],
            ];
            if ($hasLat || $hasLon) {
                $checks[] = [
                    'key' => 'room_coordinates_valid',
                    'label' => 'Geographic coordinates',
                    'status' => ($coordinatePairValid && $coordinateBoundsValid) ? 'pass' : 'fail',
                    'detail' => ($coordinatePairValid && $coordinateBoundsValid)
                        ? "Coordinates: {$lat}, {$lon}"
                        : $coordinateError,
                ];
            }
            if ($buildingName !== null || $floorLevel !== null) {
                $checks[] = [
                    'key' => 'building_supplied',
                    'label' => 'Building & Floor',
                    'status' => 'pass',
                    'detail' => match (true) {
                        $buildingName !== null && $floorLevel !== null => "{$buildingName}, Floor {$floorLevel}",
                        $buildingName !== null => $buildingName,
                        default => "Floor {$floorLevel}",
                    },
                ];
            }
            $outcome = ($codeAvailable && $coordinatePairValid && $coordinateBoundsValid) ? 'pass' : 'fail';
            $result = [
                'mode' => 'propose',
                'ok' => $outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => $command,
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'create',
                    'title' => 'Create room',
                    'sections' => [
                        ProposalViewContract::fieldsSection('room_create', 'Room configuration', $fields),
                    ],
                    'preflight' => ProposalViewContract::preflight($outcome, $checks),
                    'coverage' => $coverage,
                ],
                'diff' => [[
                    'key' => 'room',
                    'label' => 'Room',
                    'before' => 'None',
                    'after' => $normalizedName.' ('.$normalizedCode.')',
                ]],
                'evidence' => [
                    'outcome' => $outcome,
                    'checks' => $checks,
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }
        if (! $coordinatePairValid || ! $coordinateBoundsValid) {
            throw new RuntimeException($coordinateError ?? 'room_coordinates_invalid');
        }

        $room = $this->action->handle($roomAttributes, $actor);

        return [
            'mode' => 'execute',
            'ok' => true,
            'room_id' => (int) $room->id,
            'code' => $room->code,
            'name' => $room->name,
        ];
    }
}
