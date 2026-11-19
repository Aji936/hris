<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Enums;

enum AnnouncementCategory: string
{
    case INFO = 'INFO';
    case POLICY = 'POLICY';
    case EVENT = 'EVENT';
    case URGENT = 'URGENT';

    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Informasi Umum',
            self::POLICY => 'Kebijakan HR',
            self::EVENT => 'Acara & Kegiatan',
            self::URGENT => 'Penting / Urgent',
        };
    }
}
