<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** Kullanıcıyı başka bir sayfaya göndermek gerektiğinde (ör. giriş yapılmamışsa /giris). Kernel yakalar. */
final class RedirectException extends RuntimeException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct('Yönlendirme: ' . $url);
    }
}
