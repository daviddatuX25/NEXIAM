<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

uses(RefreshDatabase::class)->in('Feature');

require_once __DIR__.'/Support/E2Helpers.php';
require_once __DIR__.'/Support/GradingHelpers.php';
