<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Text;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Category;
use App\Model\SlaPolicy;
use App\Model\User;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\PriorityRepositoryInterface;
use App\Repository\SlaPolicyRepositoryInterface;

/**
 * Yöneticinin kategori ve SLA kuralı yönetimi. Kategoriler silinmez, pasif yapılır (eski talepler bozulmaz).
 * SLA değişikliği yalnız yeni taleplere uygulanır; açılmış talebin bitiş zamanı yeniden hesaplanmaz.
 */
final class CatalogService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly PriorityRepositoryInterface $priorities,
        private readonly SlaPolicyRepositoryInterface $policies,
        private readonly AuditLogger $audit,
    ) {
    }

    /** @throws ValidationException */
    public function createCategory(User $admin, string $name): Category
    {
        $name = $this->validName(null, $name);
        $category = $this->categories->save(new Category(null, $name));
        $this->audit->record($admin, 'create', 'category', $category->id, null, $category->toArray());

        return $category;
    }

    /** @throws ValidationException */
    public function updateCategory(User $admin, int $id, string $name, bool $active): Category
    {
        $current = $this->categories->findById($id) ?? throw new NotFoundException('Kategori bulunamadı.');
        $name = $this->validName($id, $name);

        if ($current->active && !$active) {
            $othersActive = array_filter($this->categories->findActive(), static fn (Category $c): bool => $c->id !== $id);
            if ($othersActive === []) {
                throw new ValidationException(['active' => 'En az bir aktif kategori olmalı.']);
            }
        }

        $old = $current->toArray();
        $category = $this->categories->save(new Category($id, $name, $active));
        $this->audit->record($admin, $active ? 'update' : 'deactivate', 'category', $id, $old, $category->toArray());

        return $category;
    }

    /**
     * Kategori × öncelik SLA tablosu (iş saati). null: kural yok, önceliğin varsayılan süresi kullanılır.
     *
     * @return array<int, array<int, ?int>> kategori id → öncelik id → saat
     */
    public function slaGrid(): array
    {
        $grid = [];
        foreach ($this->categories->findAll() as $category) {
            foreach ($this->priorities->findAll() as $priority) {
                $grid[(int) $category->id][(int) $priority->id] = $this->policies->findFor((int) $category->id, (int) $priority->id)?->durationHours;
            }
        }

        return $grid;
    }

    /** @throws ValidationException */
    public function setSla(User $admin, int $categoryId, int $priorityId, ?int $hours): void
    {
        if ($this->categories->findById($categoryId) === null || $this->priorities->findById($priorityId) === null) {
            throw new NotFoundException('Kategori veya öncelik bulunamadı.');
        }
        if ($hours !== null && ($hours < 1 || $hours > 720)) {
            throw new ValidationException(['hours' => 'SLA süresi 1 ile 720 iş saati arasında olmalı.']);
        }

        $current = $this->policies->findFor($categoryId, $priorityId);
        if ($current?->durationHours === $hours) {
            return;
        }

        if ($hours === null) {
            $this->policies->remove($categoryId, $priorityId);
        } else {
            $this->policies->save(new SlaPolicy($current?->id, $categoryId, $priorityId, $hours));
        }

        $this->audit->record($admin, 'update', 'sla_policy', null,
            ['category_id' => $categoryId, 'priority_id' => $priorityId, 'hours' => $current?->durationHours],
            ['category_id' => $categoryId, 'priority_id' => $priorityId, 'hours' => $hours]);
    }

    private function validName(?int $id, string $name): string
    {
        $name = trim($name);
        $length = mb_strlen($name);
        if ($length < 2 || $length > 60) {
            throw new ValidationException(['name' => 'Kategori adı 2-60 karakter olmalı.']);
        }
        foreach ($this->categories->findAll() as $other) {
            if ($other->id !== $id && Text::fold($other->name) === Text::fold($name)) {
                throw new ValidationException(['name' => 'Bu adla bir kategori zaten var.']);
            }
        }

        return $name;
    }
}
