<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class NexiDisclosureLedger extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'nexi_disclosure_ledgers';

    protected $fillable = [
        'id',
        'context_epoch',
        'task_grant_id',
        'run_id',
        'unique_targets',
        'sensitive_fields',
        'rows_pages',
    ];
}
