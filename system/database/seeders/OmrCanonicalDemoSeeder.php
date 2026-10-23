<?php

namespace Database\Seeders;

use App\Assessment\Omr\Canonical\RestoreCanonicalOmrFixture;
use App\Models\User;
use Illuminate\Database\Seeder;

class OmrCanonicalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()
            ->where('email', 'guidance.counselor@nexiam.test')
            ->firstOrFail();

        app(RestoreCanonicalOmrFixture::class)->handle($owner);
    }
}
