<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** Kullanıcı girdisi geçersiz olduğunda; alan adı => Türkçe hata mesajı listesini taşır. */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
