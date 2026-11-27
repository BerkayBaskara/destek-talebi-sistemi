<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Talep önceliği (ER: priorities). Liste yönetici tarafından düzenlenir.
 * rank: büyüklük sırası (1 = en düşük). Akıllı öncelik bu sırayla karşılaştırır.
 * keywords: başlık/açıklamada geçerse önceliği bu seviyeye yükselten kelimeler (FR-03).
 */
final class Priority
{
    /** @param list<string> $keywords */
    public function __construct(
        public ?int $id,
        public string $name,
        public int $rank,
        public int $defaultSlaHours,
        public bool $active = true,
        public array $keywords = [],
    ) {
    }

    public function label(): string
    {
        return $this->name;
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        $keywords = is_array($row['keywords'] ?? null) ? array_values(array_filter($row['keywords'], 'is_string')) : [];

        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['name'] ?? ''),
            (int) ($row['rank'] ?? 0),
            (int) ($row['default_sla_hours'] ?? 0),
            (bool) ($row['active'] ?? true),
            $keywords,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rank' => $this->rank,
            'default_sla_hours' => $this->defaultSlaHours,
            'active' => $this->active,
            'keywords' => $this->keywords,
        ];
    }
}
