<?php

namespace App\Models\Foundation;

enum IntakeRequirementLevel: string
{
    case SystemMandatory = 'system_mandatory';
    case InstitutionRequired = 'institution_required';
    case Optional = 'optional';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::SystemMandatory => 'System Mandatory (Locked Floor)',
            self::InstitutionRequired => 'Institutionally Required',
            self::Optional => 'Optional',
            self::Disabled => 'Disabled',
        };
    }

    public function isMandatoryFloor(): bool
    {
        return $this === self::SystemMandatory;
    }

    public function isRequired(): bool
    {
        return $this === self::SystemMandatory || $this === self::InstitutionRequired;
    }

    public function isEnabled(): bool
    {
        return $this !== self::Disabled;
    }
}
