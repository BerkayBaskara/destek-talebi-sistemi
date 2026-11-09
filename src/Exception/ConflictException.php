<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** Aynı kaynağa eşzamanlı işlemde geç kalan isteğe verilir (ör. talebi başka personel aldı). */
final class ConflictException extends RuntimeException
{
}
