<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Enums;

enum MaritalStatus: string
{
    case SINGLE = 'SINGLE';
    case MARRIED = 'MARRIED';
    case DIVORCED = 'DIVORCED';
    case WIDOWED = 'WIDOWED';

    /**
     * Derive Indonesian PPh 21 PTKP tax status code (TK/0..TK/3 or K/0..K/3).
     */
    public function ptkpCode(int $dependentsCount = 0): string
    {
        $cappedDependents = max(0, min(3, $dependentsCount));
        $prefix = $this === self::MARRIED ? 'K' : 'TK';

        return sprintf('%s/%d', $prefix, $cappedDependents);
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
