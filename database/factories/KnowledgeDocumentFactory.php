<?php

namespace Database\Factories;

use App\Models\KnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(3),
            'metadata' => ['category' => fake()->word()],
            'source' => KnowledgeDocument::SOURCE_MANUAL,
            'is_active' => true,
            'mxb_file_id' => null,
            'mxb_sync_status' => KnowledgeDocument::SYNC_PENDING,
            'mxb_synced_at' => null,
        ];
    }
}
