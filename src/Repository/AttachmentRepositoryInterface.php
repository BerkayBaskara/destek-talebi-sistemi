<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Attachment;

interface AttachmentRepositoryInterface
{
    public function save(Attachment $attachment): Attachment;

    public function findById(int $id): ?Attachment;

    /** @return list<Attachment> */
    public function findByTicket(int $ticketId): array;
}
