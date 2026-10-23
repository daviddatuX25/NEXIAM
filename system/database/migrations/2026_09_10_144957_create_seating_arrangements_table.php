<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seating_arrangements', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 190);
            $table->string('normalized_name', 190);
            $table->unsignedSmallInteger('rows');
            $table->unsignedSmallInteger('columns');
            $table->string('booklet_pattern', 32);
            $table->string('availability_mode', 24)->default('selected_rooms');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('legacy_room_seating_profile_id')
                ->nullable()
                ->unique()
                ->constrained('room_seating_profiles')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique('normalized_name', 'seating_arrangements_normalized_name_unique');
            $table->index(['availability_mode', 'is_active'], 'seating_arrangements_availability_active_idx');
        });

        Schema::create('seating_arrangement_rooms', function (Blueprint $table): void {
            $table->foreignId('seating_arrangement_id')
                ->constrained('seating_arrangements')
                ->restrictOnDelete();
            $table->foreignId('room_id')
                ->constrained('rooms')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['seating_arrangement_id', 'room_id'],
                'seating_arrangement_rooms_arrangement_room_unique',
            );
            $table->index(['room_id', 'seating_arrangement_id'], 'seating_arrangement_rooms_room_arrangement_idx');
        });

        Schema::table('rooms', function (Blueprint $table): void {
            $table->foreignId('default_seating_arrangement_id')
                ->nullable()
                ->after('capacity')
                ->constrained('seating_arrangements')
                ->restrictOnDelete();
            $table->index('default_seating_arrangement_id', 'rooms_default_seating_arrangement_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                ALTER TABLE seating_arrangements
                    ADD CONSTRAINT seating_arrangements_rows_check CHECK (rows >= 1),
                    ADD CONSTRAINT seating_arrangements_columns_check CHECK (columns >= 1),
                    ADD CONSTRAINT seating_arrangements_pattern_check
                        CHECK (booklet_pattern IN ('uniform', 'checkerboard', 'latin_four', 'linear_alternating')),
                    ADD CONSTRAINT seating_arrangements_availability_check
                        CHECK (availability_mode IN ('all_rooms', 'selected_rooms')),
                    ADD CONSTRAINT seating_arrangements_lock_version_check CHECK (lock_version >= 1),
                    ADD CONSTRAINT seating_arrangements_name_check CHECK (char_length(trim(name)) > 0),
                    ADD CONSTRAINT seating_arrangements_normalized_name_check
                        CHECK (char_length(trim(normalized_name)) > 0)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropForeign(['default_seating_arrangement_id']);
            $table->dropIndex('rooms_default_seating_arrangement_idx');
            $table->dropColumn('default_seating_arrangement_id');
        });

        Schema::dropIfExists('seating_arrangement_rooms');
        Schema::dropIfExists('seating_arrangements');
    }
};
