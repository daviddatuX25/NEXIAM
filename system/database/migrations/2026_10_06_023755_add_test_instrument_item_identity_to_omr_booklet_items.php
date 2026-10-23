<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('omr_booklet_items', function (Blueprint $table): void {
            $table->foreignId('test_instrument_item_id')
                ->nullable()
                ->constrained('test_instrument_items')
                ->restrictOnDelete();
            $table->index('test_instrument_item_id', 'omr_booklet_items_instrument_item_idx');
            $table->unique(
                ['booklet_version_id', 'test_instrument_item_id'],
                'omr_booklet_items_version_instrument_item_unique'
            );
        });

        Schema::table('omr_booklet_set_items', function (Blueprint $table): void {
            $table->foreignId('test_instrument_item_id')
                ->nullable()
                ->constrained('test_instrument_items')
                ->restrictOnDelete();
            $table->index('test_instrument_item_id', 'omr_booklet_set_items_instrument_item_idx');
            $table->unique(
                ['booklet_set_id', 'test_instrument_item_id'],
                'omr_booklet_set_items_set_instrument_item_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('omr_booklet_set_items', function (Blueprint $table): void {
            $table->dropUnique('omr_booklet_set_items_set_instrument_item_unique');
            $table->dropIndex('omr_booklet_set_items_instrument_item_idx');
            $table->dropConstrainedForeignId('test_instrument_item_id');
        });

        Schema::table('omr_booklet_items', function (Blueprint $table): void {
            $table->dropUnique('omr_booklet_items_version_instrument_item_unique');
            $table->dropIndex('omr_booklet_items_instrument_item_idx');
            $table->dropConstrainedForeignId('test_instrument_item_id');
        });
    }
};
