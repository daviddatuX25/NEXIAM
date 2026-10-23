<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Communications\Announcement;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'institution_id' => static function (): int {
                $institution = Institution::query()->first();
                if ($institution === null) {
                    $institution = Institution::query()->create(['code' => 'SYN-INST-001', 'name' => 'Synthetic Institution']);
                }

                return (int) $institution->getKey();
            },
            'publisher_key' => 'office:registrar',
            'publisher_label' => 'Registrar Office',
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(12),
            'body' => fake()->paragraphs(2, true),
            'priority' => 'normal',
            'is_featured' => false,
            'status' => 'draft',
            'revision' => 1,
            'starts_at' => null,
            'ends_at' => null,
            'published_at' => null,
            'created_by' => User::factory(),
            'published_by' => null,
            'withdrawn_at' => null,
            'withdrawn_by' => null,
            'withdrawal_reason' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'starts_at' => now()->subMinute(),
            'published_at' => now()->subMinute(),
            'published_by' => User::factory(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->published()->state(fn (): array => ['starts_at' => now()->addDay()]);
    }
}
