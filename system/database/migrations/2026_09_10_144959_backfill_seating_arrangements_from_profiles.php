<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            DB::table('room_seating_profiles')
                ->orderBy('id')
                ->get()
                ->each(function (object $profile) use ($now): void {
                    $existing = DB::table('seating_arrangements')
                        ->where('legacy_room_seating_profile_id', $profile->id)
                        ->first();

                    if ($existing !== null) {
                        return;
                    }

                    $name = trim((string) $profile->name);
                    $normalizedName = self::normalizeName($name);
                    if ($normalizedName === '') {
                        $name = 'Legacy arrangement '.$profile->id;
                        $normalizedName = self::normalizeName($name);
                    }

                    if (DB::table('seating_arrangements')->where('normalized_name', $normalizedName)->exists()) {
                        $roomCode = (string) (DB::table('rooms')->where('id', $profile->room_id)->value('code') ?? $profile->room_id);
                        $name .= ' · '.$roomCode;
                        $normalizedName = self::normalizeName($name);
                    }

                    if (DB::table('seating_arrangements')->where('normalized_name', $normalizedName)->exists()) {
                        $name .= ' · '.$profile->id;
                        $normalizedName = self::normalizeName($name);
                    }

                    $arrangementId = DB::table('seating_arrangements')->insertGetId([
                        'name' => $name,
                        'normalized_name' => $normalizedName,
                        'rows' => (int) $profile->rows,
                        'columns' => (int) $profile->columns,
                        'booklet_pattern' => (string) $profile->booklet_pattern,
                        'availability_mode' => 'selected_rooms',
                        'is_active' => (bool) $profile->is_active,
                        'lock_version' => 1,
                        'legacy_room_seating_profile_id' => (int) $profile->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('seating_arrangement_rooms')->insert([
                        'seating_arrangement_id' => $arrangementId,
                        'room_id' => (int) $profile->room_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ((bool) $profile->is_default) {
                        DB::table('rooms')
                            ->where('id', (int) $profile->room_id)
                            ->whereNull('default_seating_arrangement_id')
                            ->update([
                                'default_seating_arrangement_id' => $arrangementId,
                                'updated_at' => $now,
                            ]);
                    }
                });
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $arrangementIds = DB::table('seating_arrangements')
                ->whereNotNull('legacy_room_seating_profile_id')
                ->pluck('id');

            if ($arrangementIds->isEmpty()) {
                return;
            }

            DB::table('rooms')
                ->whereIn('default_seating_arrangement_id', $arrangementIds)
                ->update([
                    'default_seating_arrangement_id' => null,
                    'updated_at' => now(),
                ]);

            DB::table('seating_arrangement_rooms')
                ->whereIn('seating_arrangement_id', $arrangementIds)
                ->delete();

            DB::table('seating_arrangements')
                ->whereIn('id', $arrangementIds)
                ->delete();
        });
    }

    private static function normalizeName(string $name): string
    {
        return Str::of($name)
            ->squish()
            ->lower()
            ->toString();
    }
};
