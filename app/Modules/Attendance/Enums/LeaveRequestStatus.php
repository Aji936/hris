<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

enum LeaveRequestStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case APPROVED_L1 = 'APPROVED_L1';
    case APPROVED_L2 = 'APPROVED_L2';
    case APPROVED_HRD = 'APPROVED_HRD';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function isPendingReview(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::APPROVED_L1,
            self::APPROVED_L2,
        ], true);
    }

    public function isFinalApproved(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::APPROVED_HRD,
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
