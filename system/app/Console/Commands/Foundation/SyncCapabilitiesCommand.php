<?php

namespace App\Console\Commands\Foundation;

use App\Authorization\CapabilityCatalog;
use Database\Seeders\CapabilityCatalogSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Non-destructive capability catalog reconciliation (GF-21).
 *
 * The permission/role tables can drift from `CapabilityCatalog` when new
 * capabilities are added after the database was first seeded (e.g. a feature
 * adds `notify.read.own` but the dev DB predates it — every gated route then
 * 403s because the permission row does not exist). Run this after pulling a
 * catalog change; `foundation:reset --force` also covers it on fresh DBs.
 */
class SyncCapabilitiesCommand extends Command
{
    protected $signature = 'capabilities:sync';

    protected $description = 'Reconcile permission rows and role grants with CapabilityCatalog (idempotent, non-destructive)';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(CapabilityCatalogSeeder::class)->run();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(sprintf(
            'Capability catalog synced: %d permissions, %d role grant sets.',
            count(CapabilityCatalog::all()),
            count(CapabilityCatalog::roleHelperGrants()),
        ));

        return self::SUCCESS;
    }
}
