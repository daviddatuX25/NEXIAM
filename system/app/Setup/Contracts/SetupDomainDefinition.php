<?php

namespace App\Setup\Contracts;

use App\Models\Foundation\Institution;

interface SetupDomainDefinition
{
    public function key(): string;

    public function stage(): int;

    public function stageTitle(): string;

    public function desk(): string;

    public function label(): string;

    public function description(): string;

    public function icon(): string;

    public function href(): string;

    public function capability(): string;

    public function contributesToReadiness(): bool;

    public function summaryMetric(Institution $institution): string;

    public function status(Institution $institution): string;

    /**
     * @return list<array{label: string, passed: bool, severity: string, detail: ?string, action_href: ?string, action_label: ?string}>
     */
    public function checks(Institution $institution): array;
}
