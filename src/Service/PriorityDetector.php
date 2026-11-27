<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Text;
use App\Model\Priority;

/**
 * FR-03: talebin başlık ve açıklamasındaki anahtar kelimelere bakarak "gölge" öncelik belirler.
 * Müşterinin seçtiği öncelik hiçbir zaman düşürülmez; yalnız gerekiyorsa yükseltilir.
 * Kelimeler her önceliğin kaydında tutulur; yönetici Sistem Yapılandırması'ndan değiştirir.
 */
final class PriorityDetector
{
    /** @var list<array{priority: Priority, words: list<string>}> en yüksek öncelik önce */
    private array $levels = [];

    /** @param list<Priority> $priorities */
    public function __construct(array $priorities)
    {
        foreach ($priorities as $priority) {
            if (!$priority->active) {
                continue;
            }
            $words = array_values(array_filter(
                array_map(static fn (string $w): string => Text::fold($w), $priority->keywords),
                static fn (string $w): bool => $w !== '',
            ));
            $this->levels[] = ['priority' => $priority, 'words' => $words];
        }
        usort($this->levels, static fn (array $a, array $b): int => $b['priority']->rank <=> $a['priority']->rank);
    }

    /** Metinde geçen en yüksek önceliğin kelimesi; hiçbiri yoksa null. */
    public function detect(string $text): ?Priority
    {
        $text = Text::fold($text);
        foreach ($this->levels as $level) {
            foreach ($level['words'] as $word) {
                if (str_contains($text, $word)) {
                    return $level['priority'];
                }
            }
        }

        return null;
    }

    /** Müşterinin seçimi ile algılananın büyüğü. */
    public function effective(Priority $chosen, string $title, string $description): Priority
    {
        $detected = $this->detect($title . "\n" . $description);

        return $detected !== null && $detected->rank > $chosen->rank ? $detected : $chosen;
    }
}
