<?php

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $storage = storage_path('app/private/manuscript-annotations.json');
    if (File::exists($storage)) {
        File::delete($storage);
    }
});

afterEach(function (): void {
    $storage = storage_path('app/private/manuscript-annotations.json');
    if (File::exists($storage)) {
        File::delete($storage);
    }
});

test('it lists annotations and creates a new review note', function (): void {
    $response = $this->getJson('/api/dev/manuscript-annotations');
    $response->assertOk()
        ->assertJsonStructure(['version', 'updated_at', 'annotations'])
        ->assertJsonCount(0, 'annotations');

    $createResponse = $this->postJson('/api/dev/manuscript-annotations', [
        'document_id' => 'chapter-2-1',
        'author' => 'Matt',
        'category' => 'methodology',
        'comment' => 'Please verify the RAD cycle references in this section.',
        'quote' => 'Software Development Model',
    ]);

    $createResponse->assertCreated()
        ->assertJsonPath('author', 'Matt')
        ->assertJsonPath('category', 'methodology')
        ->assertJsonPath('document_id', 'chapter-2-1');

    $annId = $createResponse->json('id');

    $listResponse = $this->getJson('/api/dev/manuscript-annotations?document_id=chapter-2-1');
    $listResponse->assertOk()
        ->assertJsonCount(1, 'annotations');

    $updateResponse = $this->patchJson("/api/dev/manuscript-annotations/{$annId}", [
        'status' => 'resolved',
        'reply' => [
            'author' => 'Amelia',
            'comment' => 'Updated and cited Pressman & Maxim (2020).',
        ],
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('status', 'resolved')
        ->assertJsonCount(1, 'replies');

    $deleteResponse = $this->deleteJson("/api/dev/manuscript-annotations/{$annId}");
    $deleteResponse->assertOk()
        ->assertJson(['deleted' => true]);

    $emptyResponse = $this->getJson('/api/dev/manuscript-annotations');
    $emptyResponse->assertOk()
        ->assertJsonCount(0, 'annotations');
});
