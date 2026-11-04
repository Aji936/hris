<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Enums;

enum PayrollComponentType: string
{
    case ALLOWANCE = 'ALLOWANCE';
    case DEDUCTION = 'DEDUCTION';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases(),
        );
    }
}
