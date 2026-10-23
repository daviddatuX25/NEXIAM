<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->foreignId('program_id')->nullable()->after('institution_id')->constrained('programs')->restrictOnDelete();
            $table->index(['institution_id', 'program_id', 'status', 'is_featured', 'priority', 'published_at', 'id'], 'announcements_program_public_idx');
            $table->index(['institution_id', 'program_id', 'status', 'starts_at', 'ends_at', 'id'], 'announcements_program_window_idx');
        });

        Schema::table('announcement_revisions', function (Blueprint $table): void {
            $table->foreignId('program_id')->nullable()->after('announcement_id')->constrained('programs')->restrictOnDelete();
            $table->index(['announcement_id', 'program_id', 'revision'], 'announcement_revisions_program_idx');
        });
    }

    public function down(): void
    {
        Schema::table('announcement_revisions', function (Blueprint $table): void {
            $table->dropIndex('announcement_revisions_program_idx');
            $table->dropConstrainedForeignId('program_id');
        });

        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropIndex('announcements_program_public_idx');
            $table->dropIndex('announcements_program_window_idx');
            $table->dropConstrainedForeignId('program_id');
        });
    }
};
