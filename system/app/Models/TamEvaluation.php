<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class TamEvaluation extends Model
{
    /**
     * Isolated database connection name for TAM responses.
     */
    protected $connection = 'tam';

    /**
     * The table associated with the model.
     */
    protected $table = 'tam_evaluations';

    /**
     * Mass assignable attributes.
     */
    protected $guarded = [];

    /**
     * Institutional Department Affiliations at ISPSC Tagudin Campus.
     *
     * @var array<string, string>
     */
    public const array DEPARTMENTS = [
        'CAS' => 'College of Arts and Sciences (CAS)',
        'CBME' => 'College of Business Management and Entrepreneurship (CBME)',
        'CTE' => 'College of Teacher Education (CTE)',
    ];

    /**
     * Attribute casts.
     */
    protected function casts(): array
    {
        return [
            'evaluation_date' => 'date:Y-m-d',
            'video_watched_confirmed' => 'boolean',
            'stu_pu_01' => 'integer',
            'stu_pu_02' => 'integer',
            'stu_pu_03' => 'integer',
            'stu_pu_04' => 'integer',
            'stu_pu_05' => 'integer',
            'stu_peou_01' => 'integer',
            'stu_peou_02' => 'integer',
            'stu_peou_03' => 'integer',
            'stu_peou_04' => 'integer',
        ];
    }

    /**
     * All evaluation item codes and statements.
     *
     * @return array<string, array{construct: string, title: string, statement: string}>
     */
    public static function itemDefinitions(): array
    {
        return [
            'STU-PU-01' => [
                'construct' => 'Perceived Usefulness',
                'field' => 'stu_pu_01',
                'statement' => 'The applicant portal provides useful information about my simulated application status.',
            ],
            'STU-PU-02' => [
                'construct' => 'Perceived Usefulness',
                'field' => 'stu_pu_02',
                'statement' => 'The inquiry reply provides useful information about admission requirements.',
            ],
            'STU-PU-03' => [
                'construct' => 'Perceived Usefulness',
                'field' => 'stu_pu_03',
                'statement' => 'The portal notices provide useful information and announcements regarding my application.',
            ],
            'STU-PU-04' => [
                'construct' => 'Perceived Usefulness',
                'field' => 'stu_pu_04',
                'statement' => 'The result sheet provides useful information about my examination results.',
            ],
            'STU-PU-05' => [
                'construct' => 'Perceived Usefulness',
                'field' => 'stu_pu_05',
                'statement' => 'The examination schedule provides useful information to help me prepare for testing.',
            ],
            'STU-PEOU-01' => [
                'construct' => 'Perceived Ease of Use',
                'field' => 'stu_peou_01',
                'statement' => 'Navigating the applicant portal is easy.',
            ],
            'STU-PEOU-02' => [
                'construct' => 'Perceived Ease of Use',
                'field' => 'stu_peou_02',
                'statement' => 'Using the inquiry function is easy.',
            ],
            'STU-PEOU-03' => [
                'construct' => 'Perceived Ease of Use',
                'field' => 'stu_peou_03',
                'statement' => 'Understanding the examination scores on the result sheet is easy.',
            ],
            'STU-PEOU-04' => [
                'construct' => 'Perceived Ease of Use',
                'field' => 'stu_peou_04',
                'statement' => 'Logging in with the assigned access key and birthdate is easy.',
            ],
        ];
    }

    /**
     * Get rated scores for Perceived Usefulness items.
     *
     * @return array<string, int>
     */
    public function puScores(): array
    {
        $scores = [];
        for ($i = 1; $i <= 5; $i++) {
            $field = sprintf('stu_pu_%02d', $i);
            if ($this->{$field} !== null) {
                $scores['STU-PU-'.sprintf('%02d', $i)] = (int) $this->{$field};
            }
        }

        return $scores;
    }

    /**
     * Get rated scores for Perceived Ease of Use items.
     *
     * @return array<string, int>
     */
    public function peouScores(): array
    {
        $scores = [];
        for ($i = 1; $i <= 4; $i++) {
            $field = sprintf('stu_peou_%02d', $i);
            if ($this->{$field} !== null) {
                $scores['STU-PEOU-'.sprintf('%02d', $i)] = (int) $this->{$field};
            }
        }

        return $scores;
    }

    /**
     * Calculate average score for Perceived Usefulness.
     */
    public function averagePu(): ?float
    {
        $scores = $this->puScores();
        if (empty($scores)) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * Calculate average score for Perceived Ease of Use.
     */
    public function averagePeou(): ?float
    {
        $scores = $this->peouScores();
        if (empty($scores)) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * Calculate overall average score across all answered items.
     */
    public function overallScore(): ?float
    {
        $all = array_merge(array_values($this->puScores()), array_values($this->peouScores()));
        if (empty($all)) {
            return null;
        }

        return round(array_sum($all) / count($all), 2);
    }

    /**
     * Ensure the dedicated SQLite database file and table exist.
     */
    public static function ensureTableExists(): void
    {
        $driver = config('database.connections.tam.driver');

        if ($driver === 'sqlite') {
            $dbPath = config('database.connections.tam.database');
            if ($dbPath && $dbPath !== ':memory:' && ! file_exists($dbPath)) {
                $dir = dirname($dbPath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                touch($dbPath);
            }
        }

        if (! Schema::connection('tam')->hasTable('tam_evaluations')) {
            Schema::connection('tam')->create('tam_evaluations', function (Blueprint $table) {
                $table->id();
                $table->string('evaluator_role')->default('First-Year College Student');
                $table->date('evaluation_date')->nullable();
                $table->string('evaluator_name')->nullable();
                $table->string('department')->nullable(); // CAS, CBME, CTE
                $table->string('academic_program')->nullable();
                $table->boolean('video_watched_confirmed')->default(true);

                $table->unsignedTinyInteger('stu_pu_01')->nullable();
                $table->unsignedTinyInteger('stu_pu_02')->nullable();
                $table->unsignedTinyInteger('stu_pu_03')->nullable();
                $table->unsignedTinyInteger('stu_pu_04')->nullable();
                $table->unsignedTinyInteger('stu_pu_05')->nullable();

                $table->unsignedTinyInteger('stu_peou_01')->nullable();
                $table->unsignedTinyInteger('stu_peou_02')->nullable();
                $table->unsignedTinyInteger('stu_peou_03')->nullable();
                $table->unsignedTinyInteger('stu_peou_04')->nullable();

                $table->text('useful_features')->nullable();
                $table->text('difficulties_encountered')->nullable();
                $table->text('recommendations')->nullable();
                $table->string('evaluator_signature')->nullable();

                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        } else {
            $schema = Schema::connection('tam');
            $schema->table('tam_evaluations', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('tam_evaluations', 'evaluation_date')) {
                    $table->date('evaluation_date')->nullable();
                }
                if (! $schema->hasColumn('tam_evaluations', 'evaluator_name')) {
                    $table->string('evaluator_name')->nullable();
                }
                if (! $schema->hasColumn('tam_evaluations', 'department')) {
                    $table->string('department')->nullable();
                }
                if (! $schema->hasColumn('tam_evaluations', 'academic_program')) {
                    $table->string('academic_program')->nullable();
                }
            });
        }
    }
}
