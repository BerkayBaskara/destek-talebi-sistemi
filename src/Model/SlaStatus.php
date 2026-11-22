<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Bir talebin SLA göstergesi (arayüzdeki renkli rozet).
 * level: normal | warning (%80+, sarı) | breached (süre aşıldı, kırmızı)
 *        | met (süresinde çözüldü) | missed (geç çözüldü) | none (kapalı/iptal, gösterilmez)
 *        | paused (personel soru sordu, müşteri yanıtı bekleniyor; FR-22)
 */
final class SlaStatus
{
    public function __construct(
        public readonly string $level,
        public readonly int $percent,
        public readonly int $remainingMinutes,
        public readonly string $label,
    ) {
    }

    public function cssClass(): string
    {
        return 'sla-' . $this->level;
    }
}
