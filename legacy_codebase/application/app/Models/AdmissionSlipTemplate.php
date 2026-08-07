<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionSlipTemplate extends Model
{
    protected $fillable = [
        'name',
        'mode',
        'paper_size',
        'orientation',
        'logical_unit',
        'content',
        'docx_path',
        'is_active',
    ];

    public const MODE_HTML = 'html';

    public const MODE_DOCX = 'docx';

    public const PAPER_A4 = 'a4';

    public const PAPER_LEGAL = 'legal';

    public const PAPER_LETTER = 'letter';

    public const ORIENTATION_PORTRAIT = 'portrait';

    public const ORIENTATION_LANDSCAPE = 'landscape';

    public const LOGICAL_FULL = 'full';

    public const LOGICAL_HALF_A4 = 'half_a4';

    public const LOGICAL_HALF_LEGAL = 'half_legal';

    public const LOGICAL_HALF_LETTER = 'half_letter';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
