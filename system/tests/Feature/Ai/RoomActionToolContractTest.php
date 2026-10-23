<?php

use App\Ai\Adapters\LaravelAi\AssistantToolBridge;
use App\Ai\Context\InvocationContext;
use App\Ai\Conversation\XiamConversationSnapshotQuery;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Ai\Gateway\AssistantToolGateway;
use App\Ai\Proposals\ProposalService;
use App\Application\Capabilities\Handlers\Setup\SetupRoomCreateHandler;
use App\Application\Capabilities\Handlers\Setup\SetupRoomUpdateHandler;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Ai\XiamTurn;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Str;

test('setup.rooms.create_draft tool definition declares spatial fields and required exposure metadata', function (): void {
    $catalog = app(AssistantToolCatalog::class);
    $tool = $catalog->getById('setup.rooms.create_draft@1');

    expect($tool)->not->toBeNull();
    $schema = $tool->capability->inputSchema;
    $props = $schema['properties'] ?? [];

    expect($props)->toHaveKeys([
        'code',
        'name',
        'capacity',
        'is_exam_room',
        'latitude',
        'longitude',
        'building_name',
        'floor_level',
        'directions_hint',
    ]);
    expect($tool->capability->channels)->toContain('xiam_safe');
    expect($tool->capability->xiamExposure)->toBeArray();
    expect($tool->capability->xiamExposure['confirmation'])->toBe('explicit_proposal');
});

test('room create proposal accepts valid coordinates, validates preflight, and covers all review fields', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $handler = app(SetupRoomCreateHandler::class);

    $input = [
        'code' => 'TEST-RM-SPATIAL',
        'name' => 'Spatial Testing Laboratory',
        'capacity' => 40,
        'is_exam_room' => true,
        'latitude' => 16.9234567,
        'longitude' => 120.4512345,
        'building_name' => 'Science Complex',
        'floor_level' => '2nd Floor',
        'directions_hint' => 'Near the east elevator',
    ];

    $result = $handler->handle($input, $admin);

    expect($result['mode'])->toBe('propose');
    expect($result['ok'])->toBeTrue();
    expect($result['command_input']['latitude'])->toBe(16.9234567);
    expect($result['command_input']['longitude'])->toBe(120.4512345);
    expect($result['command_input']['building_name'])->toBe('Science Complex');

    $checks = collect($result['evidence']['checks']);
    $coordCheck = $checks->firstWhere('key', 'room_coordinates_valid');
    expect($coordCheck)->not->toBeNull();
    expect($coordCheck['status'])->toBe('pass');
    expect($coordCheck['detail'])->toContain('16.9234567');

    // Proposal review coverage test
    expect($result['proposal_review']['coverage'])->toHaveKeys([
        'code', 'name', 'capacity', 'is_exam_room',
        'latitude', 'longitude', 'building_name', 'floor_level', 'directions_hint',
    ]);
});

test('room create proposal rejects lone or out-of-bounds coordinates with failing preflight', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $handler = app(SetupRoomCreateHandler::class);

    // Lone latitude
    $loneLat = $handler->handle([
        'code' => 'RM-LONE',
        'name' => 'Lone Coord Room',
        'capacity' => 30,
        'latitude' => 16.5,
    ], $admin);
    expect($loneLat['ok'])->toBeFalse();
    $checks = collect($loneLat['evidence']['checks']);
    expect($checks->firstWhere('key', 'room_coordinates_valid')['detail'])
        ->toContain('Both latitude and longitude are required');

    // Out of bounds latitude (> 90)
    $outOfBounds = $handler->handle([
        'code' => 'RM-OOB',
        'name' => 'Out of Bounds Room',
        'capacity' => 30,
        'latitude' => 95.5,
        'longitude' => 120.5,
    ], $admin);
    expect($outOfBounds['ok'])->toBeFalse();
    $oobChecks = collect($outOfBounds['evidence']['checks']);
    expect($oobChecks->firstWhere('key', 'room_coordinates_valid')['detail'])
        ->toContain('out of valid range');
});

test('room create and update execution persists spatial coordinates to database with action parity', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $createHandler = app(SetupRoomCreateHandler::class);
    $updateHandler = app(SetupRoomUpdateHandler::class);

    // Create execution
    $created = $createHandler->handle([
        'execute' => true,
        'code' => 'SPATIAL-EXEC',
        'name' => 'Spatial Exec Room',
        'capacity' => 35,
        'is_exam_room' => true,
        'latitude' => 17.1234567,
        'longitude' => 120.9876543,
        'building_name' => 'Academic Hall',
        'floor_level' => '3',
    ], $admin);

    expect($created['ok'])->toBeTrue();
    $room = Room::query()->where('code', 'SPATIAL-EXEC')->firstOrFail();
    expect((float) $room->latitude)->toBe(17.1234567)
        ->and((float) $room->longitude)->toBe(120.9876543)
        ->and($room->building_name)->toBe('Academic Hall')
        ->and($room->floor_level)->toBe('3');

    // Update execution
    $updated = $updateHandler->handle([
        'execute' => true,
        'room_id' => $room->id,
        'capacity' => 50,
        'is_exam_room' => true,
        'latitude' => 17.5555555,
        'longitude' => 121.4444444,
        'building_name' => 'Annex Building',
    ], $admin);

    expect($updated['ok'])->toBeTrue();
    $refreshed = $room->fresh();
    expect((int) $refreshed->capacity)->toBe(50)
        ->and((float) $refreshed->latitude)->toBe(17.5555555)
        ->and((float) $refreshed->longitude)->toBe(121.4444444)
        ->and($refreshed->building_name)->toBe('Annex Building');
});

test('assistant tool bridge schema preserves nullable numeric coordinates and limits for providers', function (): void {
    $catalog = app(AssistantToolCatalog::class);
    $toolDef = $catalog->getById('setup.rooms.create_draft@1');
    expect($toolDef)->not->toBeNull();

    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->first() ?? User::factory()->create();
    $bridge = new AssistantToolBridge(
        definition: $toolDef,
        gateway: app(AssistantToolGateway::class),
        actor: $admin,
        context: new InvocationContext(
            actorId: (string) $admin->id,
            actorType: 'staff',
            institutionId: 1,
            surfaceProfile: 'test',
            mode: 'staff',
            contextEpoch: 'epoch',
            purpose: 'test',
            correlationId: 'test-bridge-schema',
        ),
    );

    $schemaFactory = new JsonSchemaTypeFactory;
    $fields = $bridge->schema($schemaFactory);

    expect($fields)->toHaveKeys(['code', 'name', 'capacity', 'latitude', 'longitude', 'building_name', 'floor_level']);

    $latArray = $fields['latitude']->toArray();
    expect($latArray['type'])->toBe(['number', 'null'])
        ->and($latArray['minimum'])->toBe(-90)
        ->and($latArray['maximum'])->toBe(90);

    $buildingArray = $fields['building_name']->toArray();
    expect($buildingArray['type'])->toBe(['string', 'null'])
        ->and($buildingArray['maxLength'])->toBe(100);
});

test('floor-only proposal without building name produces valid proposal view contract and coverage', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $createHandler = app(SetupRoomCreateHandler::class);
    $updateHandler = app(SetupRoomUpdateHandler::class);

    // Create with floor only
    $createResult = $createHandler->handle([
        'code' => 'RM-FLOOR-ONLY',
        'name' => 'Floor Only Room',
        'capacity' => 25,
        'floor_level' => '4th Floor',
    ], $admin);
    expect($createResult['ok'])->toBeTrue();
    expect($createResult['proposal_review']['coverage'])->toHaveKey('floor_level');

    // Execute create
    $createExec = $createHandler->handle([
        'execute' => true,
        'code' => 'RM-FLOOR-ONLY',
        'name' => 'Floor Only Room',
        'capacity' => 25,
        'floor_level' => '4th Floor',
    ], $admin);
    expect($createExec['ok'])->toBeTrue();

    // Update with floor only
    $room = Room::query()->where('code', 'RM-FLOOR-ONLY')->firstOrFail();
    $updateResult = $updateHandler->handle([
        'room_id' => $room->id,
        'capacity' => 30,
        'is_exam_room' => true,
        'floor_level' => '5th Floor',
    ], $admin);
    expect($updateResult['ok'])->toBeTrue();
    expect($updateResult['proposal_review']['coverage'])->toHaveKey('floor_level');
});

test('room update execution preserves explicit nulls and clears spatial database values', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $createHandler = app(SetupRoomCreateHandler::class);
    $updateHandler = app(SetupRoomUpdateHandler::class);

    // Create room with spatial data
    $created = $createHandler->handle([
        'execute' => true,
        'code' => 'RM-CLEAR-TEST',
        'name' => 'Clear Test Room',
        'capacity' => 20,
        'latitude' => 16.5,
        'longitude' => 120.5,
        'building_name' => 'Old Building',
        'floor_level' => '1',
    ], $admin);
    expect($created['ok'])->toBeTrue();

    $room = Room::query()->where('code', 'RM-CLEAR-TEST')->firstOrFail();
    expect($room->latitude)->not->toBeNull()
        ->and($room->building_name)->toBe('Old Building');

    // Update proposal verifies before values populated from existing room
    $proposeClear = $updateHandler->handle([
        'room_id' => $room->id,
        'capacity' => 20,
        'is_exam_room' => true,
        'latitude' => null,
        'longitude' => null,
        'building_name' => null,
    ], $admin);
    expect($proposeClear['ok'])->toBeTrue();

    // Execute clear
    $clearExec = $updateHandler->handle([
        'execute' => true,
        'room_id' => $room->id,
        'capacity' => 20,
        'is_exam_room' => true,
        'latitude' => null,
        'longitude' => null,
        'building_name' => null,
    ], $admin);
    expect($clearExec['ok'])->toBeTrue();

    $refreshed = $room->fresh();
    expect($refreshed->latitude)->toBeNull()
        ->and($refreshed->longitude)->toBeNull()
        ->and($refreshed->building_name)->toBeNull();
});

test('room handlers reject oversized spatial strings matching domain FormRequest boundaries', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $createHandler = app(SetupRoomCreateHandler::class);

    // Building name > 100 characters
    expect(fn () => $createHandler->handle([
        'code' => 'OVER-BUILDING',
        'name' => 'Oversized Building Room',
        'capacity' => 30,
        'building_name' => str_repeat('A', 101),
    ], $admin))->toThrow(RuntimeException::class, 'building_name_invalid');

    // Floor level > 32 characters
    expect(fn () => $createHandler->handle([
        'code' => 'OVER-FLOOR',
        'name' => 'Oversized Floor Room',
        'capacity' => 30,
        'floor_level' => str_repeat('B', 33),
    ], $admin))->toThrow(RuntimeException::class, 'floor_level_invalid');

    // Capacity > 500
    expect(fn () => $createHandler->handle([
        'code' => 'OVER-CAP',
        'name' => 'Oversized Capacity Room',
        'capacity' => 501,
    ], $admin))->toThrow(RuntimeException::class, 'room_capacity_invalid');
});

test('room handlers support modular space_kind including office classification', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $createHandler = app(SetupRoomCreateHandler::class);
    $updateHandler = app(SetupRoomUpdateHandler::class);

    // 1. Propose creation with space_kind => office
    $proposed = $createHandler->handle([
        'code' => 'DEAN-OFFICE-01',
        'name' => 'College of Science Dean Office',
        'capacity' => 12,
        'space_kind' => 'office',
    ], $admin);

    expect($proposed['ok'])->toBeTrue()
        ->and($proposed['command_input']['space_kind'])->toBe('office')
        ->and($proposed['command_input']['is_exam_room'])->toBeFalse();

    // 2. Execute creation with space_kind => office
    $created = $createHandler->handle([
        'execute' => true,
        'code' => 'DEAN-OFFICE-01',
        'name' => 'College of Science Dean Office',
        'capacity' => 12,
        'space_kind' => 'office',
    ], $admin);

    expect($created['ok'])->toBeTrue();
    $room = Room::query()->where('code', 'DEAN-OFFICE-01')->firstOrFail();
    expect($room->space_kind)->toBe(SpaceKind::Office)
        ->and($room->isExamRoom())->toBeFalse()
        ->and($room->allowsSeatingArrangement())->toBeFalse();

    // 3. Propose update changing an existing exam room to office
    $examRoom = Room::query()->where('space_kind', SpaceKind::Room)->firstOrFail();
    $updateProposal = $updateHandler->handle([
        'room_id' => $examRoom->id,
        'capacity' => 15,
        'space_kind' => 'office',
    ], $admin);

    expect($updateProposal['ok'])->toBeTrue()
        ->and($updateProposal['command_input']['space_kind'])->toBe('office')
        ->and($updateProposal['command_input']['is_exam_room'])->toBeFalse();

    // 4. Execute update
    $updateExecuted = $updateHandler->handle([
        'execute' => true,
        'room_id' => $examRoom->id,
        'capacity' => 15,
        'space_kind' => 'office',
    ], $admin);

    expect($updateExecuted['ok'])->toBeTrue();
    $refreshed = $examRoom->fresh();
    expect($refreshed->space_kind)->toBe(SpaceKind::Office)
        ->and($refreshed->isExamRoom())->toBeFalse()
        ->and($refreshed->default_seating_arrangement_id)->toBeNull();
});

test('assistant tool bridge schema forwards space_kind enum to provider schema', function (): void {
    $catalog = app(AssistantToolCatalog::class);
    $tool = $catalog->getById('setup.rooms.create_draft@1');
    $bridge = new AssistantToolBridge(
        $tool,
        app(AssistantToolGateway::class),
        new User,
        new InvocationContext('1', 'staff', 1, 'staff', 'assistant', 'epoch_1', 'staff_task', 'corr')
    );

    $factory = new JsonSchemaTypeFactory;
    $fields = $bridge->schema($factory);

    expect($fields)->toHaveKey('space_kind');
    $spaceKindSchema = $fields['space_kind']->toArray();

    expect($spaceKindSchema['enum'] ?? null)->toBe(['room', 'assembly_venue', 'service_point', 'office'])
        ->and($spaceKindSchema['description'] ?? '')->toContain('Venue/space classification');
});

test('conversation snapshot query preserves multiple proposals for a single run', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();

    $turnId = (string) Str::uuid();
    $runId = (string) Str::uuid();
    $conv = XiamConversation::query()->create([
        'id' => (string) Str::uuid(),
        'actor_id' => (string) $admin->id,
        'actor_type' => 'staff',
        'status' => 'open',
        'partition_key' => 'staff:'.(string) $admin->id.':staff',
        'context_epoch' => 'epoch_1',
        'surface_key' => 'staff',
    ]);

    $turn = XiamTurn::query()->create([
        'id' => $turnId,
        'conversation_id' => $conv->id,
        'seq' => 1,
        'input_text' => 'Propose 2 rooms',
        'mode' => 'assistant',
        'context_epoch' => 'epoch_1',
    ]);

    $run = XiamRun::query()->create([
        'id' => $runId,
        'turn_id' => $turnId,
        'conversation_id' => $conv->id,
        'status' => 'succeeded',
        'publication_version' => 'openspec3.v1',
        'gateway_route' => 'laravel',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $createHandler = app(SetupRoomCreateHandler::class);
    $res1 = $createHandler->handle(['code' => 'ROOM-A', 'name' => 'Room A', 'capacity' => 10], $admin);
    $res2 = $createHandler->handle(['code' => 'ROOM-B', 'name' => 'Room B', 'capacity' => 20], $admin);

    $proposalService = app(ProposalService::class);
    $p1 = $proposalService->create(
        actor: $admin,
        runId: $runId,
        conversationId: $conv->id,
        capabilityId: 'setup.rooms.create_draft@1',
        summary: [...$res1['impact'], '_command_input' => $res1['command_input']],
        evidence: $res1['evidence'],
        proposalView: $res1['proposal_review'],
    );

    $p2 = $proposalService->create(
        actor: $admin,
        runId: $runId,
        conversationId: $conv->id,
        capabilityId: 'setup.rooms.create_draft@1',
        summary: [...$res2['impact'], '_command_input' => $res2['command_input']],
        evidence: $res2['evidence'],
        proposalView: $res2['proposal_review'],
    );

    XiamRunEvent::query()->create([
        'id' => (string) Str::uuid(),
        'run_id' => $runId,
        'conversation_id' => $conv->id,
        'seq' => 1,
        'type' => 'assistant.message',
        'payload' => [
            'role' => 'assistant',
            'content' => 'I have prepared 2 room proposals for you.',
            'proposal_id' => $p2->id,
            'proposal_ids' => [$p1->id, $p2->id],
        ],
    ]);

    $query = app(XiamConversationSnapshotQuery::class);
    $snapshot = $query->handle($admin, $conv->id);

    expect($snapshot)->not->toBeNull();
    $messages = $snapshot['messages'] ?? [];
    $assistantMsg = collect($messages)->firstWhere('role', 'assistant');

    expect($assistantMsg)->not->toBeNull()
        ->and($assistantMsg['proposal_ids'] ?? [])->toBe([$p1->id, $p2->id])
        ->and($assistantMsg['proposals'] ?? [])->toHaveCount(2)
        ->and($assistantMsg['proposal']['id'] ?? $assistantMsg['proposal_id'])->toBe($p2->id);
});
