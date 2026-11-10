<?php

declare(strict_types=1);

namespace App\Model\State;

use RuntimeException;

/** İzin verilmeyen durum geçişi; mesajı kullanıcıya gösterilir. */
final class InvalidTransitionException extends RuntimeException
{
}
