<?php

declare(strict_types=1);

namespace App\Repository\Json;

use App\Core\Storage\JsonStore;
use App\Model\Attachment;
use App\Repository\AttachmentRepositoryInterface;

/** attachments.json üzerinde ek dosya kayıtları (dosyaların kendisi storage/uploads altında). */
final class JsonAttachmentRepository implements AttachmentRepositoryInterface
{
    private const TABLE = 'attachments';

    public function __construct(private readonly JsonStore $store)
    {
    }

    public function save(Attachment $attachment): Attachment
    {
        $data = $attachment->toArray();
        unset($data['id']);
        $attachment->id = $this->store->insert(self::TABLE, $data);

        return $attachment;
    }

    public function findById(int $id): ?Attachment
    {
        $row = $this->store->find(self::TABLE, $id);

        return $row === null ? null : Attachment::fromArray($row);
    }

    public function findByTicket(int $ticketId): array
    {
        $items = [];
        foreach ($this->store->all(self::TABLE) as $row) {
            if (($row['ticket_id'] ?? null) === $ticketId) {
                $items[] = Attachment::fromArray($row);
            }
        }

        return $items;
    }
}
