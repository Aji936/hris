<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Enums;

enum LeavePermitType: string
{
    case SICK = 'SICK';
    case SPECIAL_PERMISSION = 'SPECIAL_PERMISSION';
    case ANNUAL_LEAVE = 'ANNUAL_LEAVE';
    case LATE_COMING = 'LATE_COMING';
    case EARLY_LEAVING = 'EARLY_LEAVING';
    case NO_ABSENT_RECORD = 'NO_ABSENT_RECORD';

    public function label(): string
    {
        return match ($this) {
            self::SICK => 'Sick / Sakit',
            self::SPECIAL_PERMISSION => 'Special Permission / Izin Khusus',
            self::ANNUAL_LEAVE => 'Annual Leave / Cuti Tahunan',
            self::LATE_COMING => 'Late Coming / Datang Terlambat',
            self::EARLY_LEAVING => 'Early Leaving / Pulang Awal',
            self::NO_ABSENT_RECORD => 'No Absent Record / Lupa Absen',
        };
    }

    public function usesTimeWindow(): bool
    {
        return in_array($this, [
            self::LATE_COMING,
            self::EARLY_LEAVING,
            self::NO_ABSENT_RECORD,
        ], true);
    }

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
