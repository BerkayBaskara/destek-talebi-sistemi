<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Reply;

interface ReplyRepositoryInterface
{
    public function save(Reply $reply): Reply;

    /** @return list<Reply> eskiden yeniye */
    public function findByTicket(int $ticketId): array;
}
