<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** Kullanıcının bu işleme yetkisi olmadığında (HTTP 403). */
final class AuthorizationException extends RuntimeException
{
    public function __construct(string $message = 'Bu işlem için yetkiniz yok.')
    {
        parent::__construct($message);
    }
}
