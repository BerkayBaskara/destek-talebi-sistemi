<?php

declare(strict_types=1);

namespace App\Model\Enum;

/** Talep önceliği (ER: tickets.priority INT). */
enum Priority: int
{
    case Low = 1;
    case Normal = 2;
    case High = 3;
    case Urgent = 4;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Düşük',
            self::Normal => 'Normal',
            self::High => 'Yüksek',
            self::Urgent => 'Acil',
        };
    }

    /** Kategoriye özel SLA kuralı yoksa kullanılan süre (iş saati). */
    public function defaultSlaHours(): int
    {
        return match ($this) {
            self::Low => 24,
            self::Normal => 16,
            self::High => 8,
            self::Urgent => 4,
        };
    }
}
