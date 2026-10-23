<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'code',
        'name',
        'short_name',
        'campus_name',
        'address',
        'location',
        'contact_number',
        'email',
        'website',
        'logo_path',
        'logo_mime',
        'logo_byte_size',
        'logo_sha256',
        'enable_seating_arrangements',
        'enable_multi_set_booklets',
    ];

    /**
     * @var array<string, bool|int>
     */
    protected $attributes = [
        'enable_seating_arrangements' => false,
        'enable_multi_set_booklets' => false,
        'exam_feature_policy_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logo_byte_size' => 'integer',
            'enable_seating_arrangements' => 'boolean',
            'enable_multi_set_booklets' => 'boolean',
            'exam_feature_policy_version' => 'integer',
        ];
    }

    public function usesSeatingArrangements(): bool
    {
        return (bool) ($this->enable_seating_arrangements ?? false);
    }

    public function usesMultiSetBooklets(): bool
    {
        return (bool) ($this->enable_multi_set_booklets ?? false);
    }

    public function examFeaturePolicyVersion(): int
    {
        return (int) ($this->exam_feature_policy_version ?? 1);
    }

    public function logoUrl(): ?string
    {
        if (empty($this->logo_path)) {
            if (file_exists(public_path('assets/branding/ispsc/logo/ispsc-seal.webp'))) {
                return '/assets/branding/ispsc/logo/ispsc-seal.webp';
            }
            if (file_exists(public_path('assets/branding/ispsc/logo/ispsc-seal.png'))) {
                return '/assets/branding/ispsc/logo/ispsc-seal.png';
            }

            return null;
        }

        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
            return $this->logo_path;
        }

        $version = $this->logo_sha256 ? substr($this->logo_sha256, 0, 8) : null;

        return '/institution/logo'.($version ? '?v='.$version : '');
    }

    public function fallbackMonogram(): string
    {
        $short = trim((string) ($this->short_name ?? ''));
        if ($short !== '') {
            return mb_strtoupper(mb_substr($short, 0, 1));
        }

        $name = trim((string) ($this->name ?? ''));
        if ($name !== '') {
            return mb_strtoupper(mb_substr($name, 0, 1));
        }

        return 'N';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        $config = config('public-site.institution', []);

        return [
            ...$config,
            'name' => (string) ($this->name ?: ($config['name'] ?? 'Ilocos Sur Polytechnic State College')),
            'short_name' => (string) ($this->short_name ?: ($config['short_name'] ?? 'ISPSC')),
            'campus' => (string) ($this->campus_name ?: ($config['campus'] ?? 'Tagudin Campus')),
            'address' => (string) ($this->address ?? ($config['address'] ?? 'Quirino, Tagudin, Ilocos Sur 2714')),
            'location' => (string) ($this->location ?: ($config['location'] ?? 'Tagudin, Ilocos Sur')),
            'contact_number' => (string) ($this->contact_number ?? ($config['contact_number'] ?? '+63 (77) 674-1474')),
            'email' => (string) ($this->email ?? ($config['email'] ?? 'tagudin@ispsc.edu.ph')),
            'website' => (string) ($this->website ?? ($config['website'] ?? 'https://ispsc.edu.ph/campuses/tagudin-campus')),
            'logo_url' => $this->logoUrl(),
            'fallback_monogram' => $this->fallbackMonogram(),
        ];
    }

    /**
     * The greenfield deployment is single-institution; catalog rows are scoped
     * to this institution for uniqueness. Shared by Setup controllers and
     * scoped-unique validation rules.
     */
    public static function current(): self
    {
        return static::query()->where('code', 'SYN-INST-001')->firstOrFail();
    }

    /**
     * @return HasMany<AcademicCycle, $this>
     */
    public function academicCycles(): HasMany
    {
        return $this->hasMany(AcademicCycle::class);
    }
}
