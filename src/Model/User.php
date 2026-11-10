<?php

declare(strict_types=1);

namespace App\Model;

use App\Model\Enum\Role;

/**
 * Kullanıcı (müşteri, personel veya yönetici). Alan adları ER diyagramındaki users tablosuyla aynı;
 * `surname` Aşama 2'de eklendi (kayıtta ad ve soyad ayrı alınıyor).
 */
final class User
{
    public function __construct(
        public ?int $id,
        public string $customerNo,
        public string $passwordHash,
        public Role $role,
        public string $name,
        public string $surname,
        public string $secretQuestion,
        public string $secretAnswerHash,
        public int $totalPoints = 0,
    ) {
    }

    public function fullName(): string
    {
        return trim($this->name . ' ' . $this->surname);
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['customer_no'] ?? ''),
            (string) ($row['password_hash'] ?? ''),
            Role::from((string) ($row['role'] ?? 'customer')),
            (string) ($row['name'] ?? ''),
            (string) ($row['surname'] ?? ''),
            (string) ($row['secret_question'] ?? ''),
            (string) ($row['secret_answer_hash'] ?? ''),
            (int) ($row['total_points'] ?? 0),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'customer_no' => $this->customerNo,
            'password_hash' => $this->passwordHash,
            'role' => $this->role->value,
            'name' => $this->name,
            'surname' => $this->surname,
            'secret_question' => $this->secretQuestion,
            'secret_answer_hash' => $this->secretAnswerHash,
            'total_points' => $this->totalPoints,
        ];
    }
}
