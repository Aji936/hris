<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'PRESENT';
    case LATE = 'LATE';
    case ABSENT = 'ABSENT';
    case ON_LEAVE = 'ON_LEAVE';
    case SICK = 'SICK';
    case HOLIDAY = 'HOLIDAY';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
