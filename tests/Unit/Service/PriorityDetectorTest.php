<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Priority;
use App\Service\PriorityDetector;
use App\Service\PriorityService;
use PHPUnit\Framework\TestCase;

/** FR-03: başlık ve açıklamadaki anahtar kelimelere göre akıllı (gölge) öncelik. */
final class PriorityDetectorTest extends TestCase
{
    /** @return array<int, Priority> id => öncelik */
    private static function priorities(): array
    {
        $byId = [];
        foreach (PriorityService::defaults() as $p) {
            $byId[(int) $p->id] = $p;
        }

        return $byId;
    }

    private static function detector(): PriorityDetector
    {
        return new PriorityDetector(array_values(self::priorities()));
    }

    private static function p(int $id): Priority
    {
        return self::priorities()[$id];
    }

    public function test_detects_urgent_keyword(): void
    {
        self::assertSame('Çok Yüksek', self::detector()->detect('Muhasebe sunucusu çöktü, fatura kesemiyoruz')?->name);
    }

    public function test_turkish_uppercase_is_detected(): void
    {
        self::assertSame('Çok Yüksek', self::detector()->detect('ACİL: SUNUCU ÇÖKTÜ')?->name);
        self::assertSame('Yüksek', self::detector()->detect('VPN\'E BAĞLANAMIYORUM')?->name);
    }

    public function test_dotless_capital_i_is_detected(): void
    {
        self::assertSame('Çok Yüksek', self::detector()->detect('ACIL: KRITIK sorun')?->name);
    }

    public function test_highest_level_wins(): void
    {
        self::assertSame('Çok Yüksek', self::detector()->detect('Sistem yavaş ve artık tamamen çalışmıyor')?->name);
    }

    public function test_effective_never_lowers(): void
    {
        self::assertSame('Çok Yüksek', self::detector()->effective(self::p(4), 'Ekran yavaş', 'Biraz yavaş açılıyor.')->name);
    }

    public function test_effective_raises(): void
    {
        self::assertSame('Çok Yüksek', self::detector()->effective(self::p(1), 'Yazıcı', 'Yazıcı hiç çalışmıyor.')->name);
    }

    public function test_no_keyword_returns_null_and_keeps_choice(): void
    {
        self::assertNull(self::detector()->detect('Yeni klavye talebi'));
        self::assertSame('Normal', self::detector()->effective(self::p(2), 'Yeni klavye talebi', 'Ofise bir klavye lazım.')->name);
    }

    public function test_inactive_priorities_and_empty_list_detect_nothing(): void
    {
        self::assertNull((new PriorityDetector([]))->detect('acil'));

        $inactive = self::p(4);
        $inactive->active = false;
        self::assertSame('Yüksek', (new PriorityDetector([$inactive, self::p(3)]))->detect('acil ve yavaş')?->name);
    }
}
