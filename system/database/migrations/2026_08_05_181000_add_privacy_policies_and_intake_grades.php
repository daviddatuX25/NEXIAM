<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_policies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('is_active');
        });

        // Catalog — Registrar can add/reorder/deactivate fields without code changes.
        Schema::create('intake_grade_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('label');
            $table->string('field_kind', 32)->default('subject'); // gwa|subject
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('min_value', 5, 2)->default(0);
            $table->decimal('max_value', 5, 2)->default(100);
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'is_active', 'sort_order']);
        });

        Schema::create('admission_attempt_grade_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->foreignId('intake_grade_field_id')->constrained('intake_grade_fields')->restrictOnDelete();
            $table->string('field_code', 32);
            $table->string('field_label');
            $table->decimal('value', 5, 2);
            $table->timestamps();

            $table->unique(['admission_attempt_id', 'intake_grade_field_id'], 'attempt_grade_field_unique');
            $table->index(['admission_attempt_id', 'field_code']);
        });

        Schema::table('admission_attempts', function (Blueprint $table) {
            $table->foreignId('privacy_policy_id')->nullable()->after('gwa')->constrained('privacy_policies')->nullOnDelete();
        });

        $hardCoded = array_values(array_filter(
            ['grade_english', 'grade_filipino', 'grade_mathematics', 'grade_science'],
            fn (string $column): bool => Schema::hasColumn('admission_attempts', $column),
        ));
        if ($hardCoded !== []) {
            Schema::table('admission_attempts', function (Blueprint $table) use ($hardCoded): void {
                $table->dropColumn($hardCoded);
            });
        }
    }

    public function down(): void
    {
        Schema::table('admission_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('admission_attempts', 'privacy_policy_id')) {
                $table->dropConstrainedForeignId('privacy_policy_id');
            }
        });

        Schema::dropIfExists('admission_attempt_grade_values');
        Schema::dropIfExists('intake_grade_fields');
        Schema::dropIfExists('privacy_policies');
    }
};
