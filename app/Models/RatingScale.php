<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatingScale extends Model
{
    protected $fillable = ['name', 'ranges', 'is_default'];

    protected function casts(): array
    {
        return [
            'ranges' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function ratingFor(int $percentile): string
    {
        foreach ($this->ranges as $range) {
            if ($percentile >= $range['min'] && $percentile <= $range['max']) {
                return $range['label'];
            }
        }

        return '—';
    }

    public static function default(): ?self
    {
        return self::where('is_default', true)->first();
    }
}
