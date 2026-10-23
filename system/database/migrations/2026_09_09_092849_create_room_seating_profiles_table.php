<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_seating_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->unsignedSmallInteger('rows');
            $table->unsignedSmallInteger('columns');
            $table->string('booklet_pattern', 32);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['room_id', 'normalized_name'], 'room_seating_profiles_room_name_unique');
            $table->index(['room_id', 'is_active'], 'room_seating_profiles_room_active_idx');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX room_seating_profiles_active_default_unique
                ON room_seating_profiles (room_id)
                WHERE is_active = true AND is_default = true
        SQL);

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
                ALTER TABLE room_seating_profiles
                    ADD CONSTRAINT room_seating_profiles_rows_check CHECK (rows >= 1),
                    ADD CONSTRAINT room_seating_profiles_columns_check CHECK (columns >= 1),
                    ADD CONSTRAINT room_seating_profiles_pattern_check
                        CHECK (booklet_pattern IN ('uniform', 'checkerboard', 'latin_four', 'linear_alternating')),
                    ADD CONSTRAINT room_seating_profiles_default_active_check
                        CHECK (NOT is_default OR is_active)
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_seating_profiles');
    }
};
