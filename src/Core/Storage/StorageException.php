<?php

declare(strict_types=1);

namespace App\Core\Storage;

use RuntimeException;

/** JSON veri dosyası okunamadığında, yazılamadığında veya kayıt bulunamadığında atılır. */
final class StorageException extends RuntimeException
{
}
