<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Text;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Priority;
use App\Model\User;
use App\Repository\PriorityRepositoryInterface;

/** Yöneticinin öncelik listesini yönetmesi: ekleme, düzenleme, aktif/pasif, anahtar kelimeler. */
final class PriorityService
{
    public function __construct(
        private readonly PriorityRepositoryInterface $repo,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Varsayılan öncelikler. Kimlik sırası eski sabit önceliklerle uyumludur
     * (1 Düşük, 2 Normal, 3 Yüksek, 4 Çok Yüksek); Çok Düşük sonradan eklendiği için 5'tir.
     *
     * @return list<Priority>
     */
    public static function defaults(): array
    {
        return [
            new Priority(1, 'Düşük', 2, 24),
            new Priority(2, 'Normal', 3, 16),
            new Priority(3, 'Yüksek', 4, 8, true, ['hata', 'yavaş', 'bağlanamıyorum', 'açılmıyor']),
            new Priority(4, 'Çok Yüksek', 5, 4, true, ['acil', 'çöktü', 'erişilemiyor', 'çalışmıyor', 'kritik', 'veri kaybı']),
            new Priority(5, 'Çok Düşük', 1, 32),
        ];
    }

    /**
     * @param array<string, mixed> $data name, rank, default_sla_hours, active, keywords
     * @throws ValidationException
     */
    public function create(User $admin, array $data): Priority
    {
        $priority = $this->build(null, $data);
        $this->repo->save($priority);
        $this->audit->record($admin, 'create', 'priority', $priority->id, null, $priority->toArray());

        return $priority;
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public function update(User $admin, int $id, array $data): Priority
    {
        $current = $this->repo->findById($id) ?? throw new NotFoundException('Öncelik bulunamadı.');
        $priority = $this->build($id, $data);

        if ($current->active && !$priority->active) {
            $othersActive = array_filter($this->repo->findActive(), static fn (Priority $p): bool => $p->id !== $id);
            if ($othersActive === []) {
                throw new ValidationException(['active' => 'En az bir aktif öncelik olmalı.']);
            }
        }

        $this->repo->save($priority);
        $this->audit->record($admin, 'update', 'priority', $id, $current->toArray(), $priority->toArray());

        return $priority;
    }

    /** @param array<string, mixed> $data */
    private function build(?int $id, array $data): Priority
    {
        $name = trim((string) ($data['name'] ?? ''));
        $rank = (string) ($data['rank'] ?? '');
        $hours = (string) ($data['default_sla_hours'] ?? '');
        $errors = [];

        $length = mb_strlen($name);
        if ($length < 2 || $length > 40) {
            $errors['name'] = 'Öncelik adı 2-40 karakter olmalı.';
        }
        if (preg_match('/^\d+$/', $rank) !== 1 || (int) $rank < 1 || (int) $rank > 99) {
            $errors['rank'] = 'Sıra 1 ile 99 arasında bir tam sayı olmalı.';
        }
        if (preg_match('/^\d+$/', $hours) !== 1 || (int) $hours < 1 || (int) $hours > 720) {
            $errors['default_sla_hours'] = 'SLA süresi 1 ile 720 iş saati arasında olmalı.';
        }

        foreach ($this->repo->findAll() as $other) {
            if ($other->id === $id) {
                continue;
            }
            if (!isset($errors['name']) && Text::fold($other->name) === Text::fold($name)) {
                $errors['name'] = 'Bu adla bir öncelik zaten var.';
            }
            if (!isset($errors['rank']) && $other->rank === (int) $rank) {
                $errors['rank'] = 'Bu sıra numarası başka bir öncelikte kullanılıyor.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $keywords = array_values(array_filter(
            array_map('trim', explode(',', (string) ($data['keywords'] ?? ''))),
            static fn (string $w): bool => $w !== '',
        ));

        return new Priority($id, $name, (int) $rank, (int) $hours, !in_array((string) ($data['active'] ?? '1'), ['0', ''], true), $keywords);
    }
}
