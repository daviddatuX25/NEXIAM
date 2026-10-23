<?php

namespace App\Setup\Definitions;

use App\Models\Foundation\Institution;
use App\Setup\Contracts\SetupDomainDefinition;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractSetupDomainDefinition implements SetupDomainDefinition
{
    /** @var class-string<Model> */
    protected const MODEL = Model::class;

    protected const INSTITUTION_SCOPED = true;

    protected const KEY = '';

    protected const STAGE = 1;

    protected const STAGE_TITLE = '';

    protected const DESK = 'admin';

    protected const LABEL = '';

    protected const DESCRIPTION = '';

    protected const ICON = '';

    protected const HREF = '';

    protected const CAPABILITY = '';

    protected const CONTRIBUTES_TO_READINESS = true;

    protected function configuredCount(Institution $institution): int
    {
        $model = static::MODEL;
        $query = $model::query();

        if (static::INSTITUTION_SCOPED) {
            $query->where('institution_id', $institution->getKey());
        }

        return $query->count();
    }

    public function key(): string
    {
        return static::KEY;
    }

    public function stage(): int
    {
        return static::STAGE;
    }

    public function stageTitle(): string
    {
        return static::STAGE_TITLE;
    }

    public function desk(): string
    {
        return static::DESK;
    }

    public function label(): string
    {
        return static::LABEL;
    }

    public function description(): string
    {
        return static::DESCRIPTION;
    }

    public function icon(): string
    {
        return static::ICON;
    }

    public function href(): string
    {
        return static::HREF;
    }

    public function capability(): string
    {
        return static::CAPABILITY;
    }

    public function contributesToReadiness(): bool
    {
        return static::CONTRIBUTES_TO_READINESS;
    }

    public function summaryMetric(Institution $institution): string
    {
        return number_format($this->configuredCount($institution)).' configured';
    }

    public function status(Institution $institution): string
    {
        $checks = $this->checks($institution);

        foreach ($checks as $check) {
            if (! $check['passed'] && $check['severity'] === 'critical') {
                return 'critical';
            }
        }

        foreach ($checks as $check) {
            if (! $check['passed']) {
                return 'attention';
            }
        }

        return 'ready';
    }

    /**
     * @return list<array{label: string, passed: bool, severity: string, detail: ?string, action_href: ?string, action_label: ?string}>
     */
    public function checks(Institution $institution): array
    {
        $count = $this->configuredCount($institution);
        $configured = $count > 0;

        return [[
            'label' => 'Setup items are configured',
            'passed' => $configured,
            'severity' => 'critical',
            'detail' => $configured
                ? number_format($count).' item(s) configured.'
                : 'Configure at least one item to make this setup area available.',
            'action_href' => $configured ? null : static::HREF,
            'action_label' => $configured ? null : 'Configure '.static::LABEL,
        ]];
    }
}
