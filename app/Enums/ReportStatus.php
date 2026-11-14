<?php

namespace App\Enums;

/**
 * What the admin did with a report.
 */
enum ReportStatus: string
{
    case Open = 'open';
    case Actioned = 'actioned';
    case Dismissed = 'dismissed';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Actioned => 'Actioned',
            self::Dismissed => 'Dismissed',
        };
    }

    /**
     * All stored values, e.g. for validation rules.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
