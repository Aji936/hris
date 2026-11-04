<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Enums;

enum EmploymentStatus: string
{
    case PROBATION = 'PROBATION';
    case PERMANENT = 'PERMANENT';
    case CONTRACT = 'CONTRACT';
    case TERMINATED = 'TERMINATED';

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
