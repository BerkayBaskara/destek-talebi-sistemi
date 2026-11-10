<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Text;
use App\Model\Enum\Priority;

/**
 * FR-03: talebin başlık ve açıklamasındaki anahtar kelimelere bakarak "gölge" öncelik belirler.
 * Müşterinin seçtiği öncelik hiçbir zaman düşürülmez; yalnız gerekiyorsa yükseltilir.
 * Kelimeler storage/config/priority_keywords.json dosyasından okunur (kod değişmeden güncellenebilir).
 */
final class PriorityDetector
{
    /** @var array<int, list<string>> öncelik değeri => küçük harfe çevrilmiş kelimeler */
    private array $keywords = [];

    /** @param array<int, list<string>> $keywords */
    public function __construct(array $keywords)
    {
        foreach ($keywords as $priority => $words) {
            $this->keywords[(int) $priority] = array_map(static fn (string $w): string => Text::fold($w), $words);
        }
        krsort($this->keywords); // en yüksek öncelik önce denenir
    }

    /** Dosya yoksa veya bozuksa algılama yapılmaz (sistem çökmez). */
    public static function fromFile(string $path): self
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($data)) {
            return new self([]);
        }

        $keywords = [];
        foreach ($data as $priority => $words) {
            if (is_array($words) && Priority::tryFrom((int) $priority) !== null) {
                $keywords[(int) $priority] = array_values(array_filter($words, 'is_string'));
            }
        }

        return new self($keywords);
    }

    /** Metinde geçen en yüksek önceliğin kelimesi; hiçbiri yoksa null. */
    public function detect(string $text): ?Priority
    {
        $text = Text::fold($text);
        foreach ($this->keywords as $priority => $words) {
            foreach ($words as $word) {
                if ($word !== '' && str_contains($text, $word)) {
                    return Priority::tryFrom($priority);
                }
            }
        }

        return null;
    }

    /** Müşterinin seçimi ile algılananın büyüğü. */
    public function effective(Priority $chosen, string $title, string $description): Priority
    {
        $detected = $this->detect($title . "\n" . $description);

        return $detected !== null && $detected->value > $chosen->value ? $detected : $chosen;
    }
}
