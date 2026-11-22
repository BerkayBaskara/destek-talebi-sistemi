<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Text;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Model\Category;
use App\Model\SlaPolicy;
use App\Model\User;
use App\Repository\CategoryFieldRepositoryInterface;
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
        private readonly ?CategoryFieldRepositoryInterface $fields = null,
    ) {
    }

    public const FIELD_TYPES = ['text' => 'Metin', 'number' => 'Sayı', 'date' => 'Tarih', 'select' => 'Seçim listesi', 'email' => 'E-posta'];

    /** @return array<int, string> kategori id → ekranda gösterilen ad ("Ana › Alt") */
    public function labels(): array
    {
        $byId = [];
        foreach ($this->categories->findAll() as $category) {
            $byId[(int) $category->id] = $category;
        }
        $labels = [];
        foreach ($byId as $id => $category) {
            $labels[$id] = $category->pathLabel($byId);
        }

        return $labels;
    }

    /** Kategoriye ek talep alanı ekler (FR-01). @throws ValidationException */
    public function addField(User $admin, int $categoryId, string $label, string $type, bool $required, string $options = '', string $maxLength = ''): int
    {
        if ($this->categories->findById($categoryId) === null) {
            throw new NotFoundException('Kategori bulunamadı.');
        }
        $label = trim($label);
        $errors = [];
        if (mb_strlen($label) < 2 || mb_strlen($label) > 60) {
            $errors['field_label'] = 'Alan adı 2-60 karakter olmalı.';
        }
        if (!isset(self::FIELD_TYPES[$type])) {
            $errors['field_type'] = 'Geçerli bir alan türü seçin.';
        }
        $choices = array_values(array_unique(array_filter(array_map('trim', explode(',', $options)), static fn (string $o): bool => $o !== '')));
        if ($type === 'select' && $choices === []) {
            $errors['field_options'] = 'Seçim alanı için en az bir seçenek yazın.';
        }
        $max = trim($maxLength);
        if ($max !== '' && (preg_match('/^\d+$/', $max) !== 1 || (int) $max < 1 || (int) $max > 200)) {
            $errors['field_max_length'] = 'En fazla uzunluk 1 ile 200 arasında olmalı.';
        }
        if ($errors !== [] || $this->fields === null) {
            throw new ValidationException($errors);
        }
        $id = $this->fields->add($categoryId, $label, $type, $required, $type === 'select' ? $choices : [], $max !== '' ? (int) $max : null);
        $this->audit->record($admin, 'create', 'category_field', $id, null, ['category_id' => $categoryId, 'label' => $label, 'type' => $type, 'required' => $required, 'options' => $choices, 'max_length' => $max]);

        return $id;
    }

    public function removeField(User $admin, int $id): void
    {
        foreach ($this->fields?->all() ?? [] as $field) {
            if ($field['id'] === $id) {
                $this->fields?->remove($id);
                $this->audit->record($admin, 'delete', 'category_field', $id, $field, null);

                return;
            }
        }
    }

    /** @throws ValidationException */
    public function createCategory(User $admin, string $name, ?int $parentId = null): Category
    {
        $name = $this->validName(null, $name);
        if ($parentId !== null) {
            $parent = $this->categories->findById($parentId);
            if ($parent === null || $parent->parentId !== null) {
                throw new ValidationException(['parent_id' => 'Alt kategori yalnız ana kategorinin altına eklenebilir.']);
            }
        }
        $category = $this->categories->save(new Category(null, $name, true, $parentId));
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
        $category = $this->categories->save(new Category($id, $name, $active, $current->parentId));
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
