<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::find($key);

        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }

        $v = $row->value;
        if (in_array($key, ['ai_exam_companion_enabled', 'notify_on_publish', 'allow_direct_assessment', 'enable_normalized_scores', 'admission_slip_enabled', 'allow_flexible_applications'], true)) {
            return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        }

        return $v;
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );
    }

    /**
     * Whether the AI exam companion feature is enabled.
     */
    public static function aiCompanionEnabled(): bool
    {
        return (bool) self::get('ai_exam_companion_enabled', false);
    }

    /**
     * Returns the result release mode: 'online' or 'f2f'. Default: 'online'.
     */
    public static function releaseMode(): string
    {
        return (string) self::get('release_mode', 'online');
    }

    /**
     * Whether to email applicants when their exam session is published. Default: true.
     */
    public static function notifyOnPublish(): bool
    {
        return (bool) self::get('notify_on_publish', true);
    }

    /**
     * Default system prompt / persona when none is set (T3.2).
     */
    public static function defaultPersonaPrompt(): string
    {
        return 'You are Cat-Bot, a helpful academic counselor for SecureCAT exam applicants. Base your advice only on the data provided. Do not invent statistics. If the data does not cover a question, say so. Be friendly, encouraging, and clear.';
    }

    /**
     * Get the AI companion persona prompt (system instructions). Returns default when empty.
     */
    public static function personaPrompt(): string
    {
        $value = self::get('ai_companion_persona', null);
        if ($value === null || trim((string) $value) === '') {
            return self::defaultPersonaPrompt();
        }

        return trim((string) $value);
    }

    /**
     * Whether direct assessment (walk-in scoring without scheduling) is enabled. Default: true.
     */
    public static function allowDirectAssessment(): bool
    {
        return self::get('allow_direct_assessment', true);
    }

    /**
     * Whether normalized score auto-computation is enabled. Default: false.
     */
    public static function enableNormalizedScores(): bool
    {
        return (bool) self::get('enable_normalized_scores', false);
    }

    public static function admissionSlipEnabled(): bool
    {
        return (bool) self::get('admission_slip_enabled', false);
    }

    /**
     * Whether to allow flexible/optional application details. Default: false.
     */
    public static function allowFlexibleApplications(): bool
    {
        return (bool) self::get('allow_flexible_applications', false);
    }

    public static function admissionSlipTemplate(): ?string
    {
        $value = self::get('admission_slip_html_template', null);
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * Resolve an institution config value: admin override → .env default.
     *
     * Supports dot-notation for nested personnel keys:
     *   SystemSetting::institution('name')
     *   SystemSetting::institution('personnel.guidance_counselor.name')
     */
    public static function institution(string $key, mixed $default = null): mixed
    {
        return self::get("institution.{$key}") ?? config("institution.{$key}", $default);
    }
}
