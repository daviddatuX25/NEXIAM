<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Model;

final class ApplicationSuggestionList extends Model
{
    protected $fillable = ['institution_id', 'category', 'entries', 'revision', 'updated_by_user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['entries' => 'array', 'revision' => 'integer'];
    }
}
