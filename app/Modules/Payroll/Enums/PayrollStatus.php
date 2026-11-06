<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Enums;

enum PayrollStatus: string
{
    case DRAFT = 'DRAFT';
    case PROCESSING = 'PROCESSING';
    case GENERATED = 'GENERATED';
    case APPROVED = 'APPROVED';
    case PUBLISHED = 'PUBLISHED';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';

    public function isImmutable(): bool
    {
        return in_array($this, [
            self::APPROVED,
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
