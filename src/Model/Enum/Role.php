<?php

declare(strict_types=1);

namespace App\Model\Enum;

/** Kullanıcı rolleri (ER: users.role ENUM). */
enum Role: string
{
    case Customer = 'customer';
    case Agent = 'agent';
    case Admin = 'admin';

    /** Müşteri numarasının öneki: M123456, P000001, Y000001. */
    public function prefix(): string
    {
        return match ($this) {
            self::Customer => 'M',
            self::Agent => 'P',
            self::Admin => 'Y',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Müşteri',
            self::Agent => 'Personel',
            self::Admin => 'Yönetici',
        };
    }
}
