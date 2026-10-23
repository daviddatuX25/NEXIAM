<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

final class IcatProductionTestKitSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException('The ICAT fixture seeder is disabled because its generated answer keys are not verified and must not be labeled institutional. Provide an explicitly synthetic fixture with an explicit answer key instead.');
    }
}
