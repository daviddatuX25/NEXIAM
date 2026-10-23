<?php

namespace App\Models\Foundation;

enum SpaceKind: string
{
    case Room = 'room';
    case AssemblyVenue = 'assembly_venue';
    case ServicePoint = 'service_point';
    case Office = 'office';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Exam / Testing Room',
            self::AssemblyVenue => 'Assembly Venue',
            self::ServicePoint => 'Service Window / Counter',
            self::Office => 'Office / Other Room',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Room => 'Exam Room',
            self::AssemblyVenue => 'Assembly Venue',
            self::ServicePoint => 'Service Window',
            self::Office => 'Office / Other',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Room => 'Has desks and requires seating arrangements.',
            self::AssemblyVenue => 'Covered court, gym, or hall for mass result release and academic advising.',
            self::ServicePoint => 'Admissions desk, cashier, or physical verification counter.',
            self::Office => 'Administrative or academic office, faculty room, or non-testing workroom.',
        };
    }

    public function allowsSeatingArrangement(): bool
    {
        return $this === self::Room;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Room => 'secondary',
            self::AssemblyVenue, self::ServicePoint, self::Office => 'outline',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Room => '',
            self::AssemblyVenue => 'border-blue-500/30 text-blue-600 dark:text-blue-400',
            self::ServicePoint => 'border-amber-500/30 text-amber-600 dark:text-amber-400',
            self::Office => 'border-emerald-500/30 text-emerald-600 dark:text-emerald-400',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }

    /**
     * @return list<array{value: string, label: string, short_label: string, description: string, allows_seating: bool, badge_variant: string, badge_class: string}>
     */
    public static function catalog(): array
    {
        return array_map(static fn (self $kind): array => [
            'value' => $kind->value,
            'label' => $kind->label(),
            'short_label' => $kind->shortLabel(),
            'description' => $kind->description(),
            'allows_seating' => $kind->allowsSeatingArrangement(),
            'badge_variant' => $kind->badgeVariant(),
            'badge_class' => $kind->badgeClass(),
        ], self::cases());
    }

    /**
     * Aggregate room counts per space kind for a given institution.
     *
     * @return array<string, int>
     */
    public static function countsForInstitution(int $institutionId): array
    {
        $counts = ['all' => 0];
        foreach (self::cases() as $case) {
            $counts[$case->value] = 0;
        }

        $grouped = Room::query()
            ->where('institution_id', $institutionId)
            ->selectRaw('space_kind, count(*) as count')
            ->groupBy('space_kind')
            ->pluck('count', 'space_kind');

        $total = 0;
        foreach ($grouped as $kind => $count) {
            $intCount = (int) $count;
            $total += $intCount;
            if ($kind !== null && array_key_exists((string) $kind, $counts)) {
                $counts[(string) $kind] = $intCount;
            }
        }
        $counts['all'] = $total;

        return $counts;
    }
}
