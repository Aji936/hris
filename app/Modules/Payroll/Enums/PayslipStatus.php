<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Enums;

enum PayslipStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case PAID = 'PAID';

    public function isEmployeeVisible(): bool
    {
        return in_array($this, [
            self::PUBLISHED,
            self::PAID,
        ], true);
    }

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
