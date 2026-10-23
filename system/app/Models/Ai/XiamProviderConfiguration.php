<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

final class XiamProviderConfiguration extends Model
{
    protected $table = 'xiam_provider_configurations';

    protected $fillable = [
        'institution_id',
        'driver',
        'base_url',
        'key_mode',
        'custom_api_key',
        'active_model_id',
        'fallback_model_id',
        'timeout_seconds',
        'max_tokens',
        'temperature',
        'is_on_premises',
        'config_version',
        'discovered_models',
        'discovered_at',
    ];

    protected $hidden = ['custom_api_key'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_api_key' => 'encrypted',
            'discovered_models' => 'array',
            'discovered_at' => 'datetime',
            'timeout_seconds' => 'integer',
            'max_tokens' => 'integer',
            'temperature' => 'float',
            'is_on_premises' => 'boolean',
            'config_version' => 'integer',
        ];
    }

    public function hasCustomApiKey(): bool
    {
        return is_string($this->custom_api_key) && trim($this->custom_api_key) !== '';
    }
}
