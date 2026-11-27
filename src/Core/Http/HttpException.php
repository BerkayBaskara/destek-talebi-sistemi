<?php

declare(strict_types=1);

namespace App\Core\Http;

use RuntimeException;

/** Belirli bir HTTP durum koduyla sonuçlanması gereken hata (404, 403, 405 ...). */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
