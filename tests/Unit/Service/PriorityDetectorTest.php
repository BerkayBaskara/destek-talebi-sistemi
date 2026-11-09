<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Model\Enum\Priority;
use App\Service\PriorityDetector;
use PHPUnit\Framework\TestCase;

/** FR-03: başlık ve açıklamadaki anahtar kelimelere göre akıllı (gölge) öncelik. */
final class PriorityDetectorTest extends TestCase
{
    private static function detector(): PriorityDetector
    {
        return new PriorityDetector([
            4 => ['acil', 'çöktü', 'erişilemiyor', 'çalışmıyor', 'kritik', 'veri kaybı'],
            3 => ['hata', 'yavaş', 'bağlanamıyorum', 'açılmıyor'],
        ]);
    }

    public function test_detects_urgent_keyword(): void
    {
        self::assertSame(Priority::Urgent, self::detector()->detect('Muhasebe sunucusu çöktü, fatura kesemiyoruz'));
    }

    public function test_turkish_uppercase_is_detected(): void
    {
        self::assertSame(Priority::Urgent, self::detector()->detect('ACİL: SUNUCU ÇÖKTÜ'));
        self::assertSame(Priority::High, self::detector()->detect('VPN\'E BAĞLANAMIYORUM'));
    }

    public function test_ascii_capital_i_is_detected(): void
    {
        self::assertSame(Priority::Urgent, self::detector()->detect('ACIL: KRITIK sorun'));
    }

    public function test_highest_match_wins(): void
    {
        self::assertSame(Priority::Urgent, self::detector()->detect('Sistem yavaş ve artık tamamen çalışmıyor'));
    }

    public function test_effective_never_lowers(): void
    {
        self::assertSame(Priority::Urgent, self::detector()->effective(Priority::Urgent, 'Ekran yavaş', 'Biraz yavaş açılıyor.'));
    }

    public function test_effective_raises(): void
    {
        self::assertSame(Priority::Urgent, self::detector()->effective(Priority::Low, 'Yazıcı', 'Yazıcı hiç çalışmıyor.'));
    }

    public function test_no_keyword_returns_null_and_keeps_choice(): void
    {
        self::assertNull(self::detector()->detect('Yeni klavye talebi'));
        self::assertSame(Priority::Normal, self::detector()->effective(Priority::Normal, 'Yeni klavye talebi', 'Ofise bir klavye lazım.'));
    }

    public function test_missing_or_corrupt_file_means_no_detection(): void
    {
        self::assertNull(PriorityDetector::fromFile(__DIR__ . '/yok.json')->detect('acil'));
        self::assertNull(PriorityDetector::fromFile(__DIR__ . '/../../fixtures/calendar-bozuk.json')->detect('acil'));
    }

    public function test_project_keyword_file_loads(): void
    {
        $detector = PriorityDetector::fromFile(dirname(__DIR__, 3) . '/storage/config/priority_keywords.json');

        self::assertSame(Priority::Urgent, $detector->detect('Sunucuya erişilemiyor'));
    }
}
