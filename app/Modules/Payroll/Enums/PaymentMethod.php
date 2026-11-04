<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Enums;

enum PaymentMethod: string
{
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CASH = 'CASH';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $method): string => $method->value,
            self::cases(),
        );
    }
}
