<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** İstenen kayıt bulunamadığında (HTTP 404). */
final class NotFoundException extends RuntimeException
{
    public function __construct(string $message = 'Kayıt bulunamadı.')
    {
        parent::__construct($message);
    }
}
